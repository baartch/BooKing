<?php
/**
 * Installer Flow Helpers (database + mail dependent)
 *
 * Loaded by the installer controller ONLY after config/config.php has
 * been written, because these require the app's core database, OTP and
 * mail helpers (which themselves require config.php).
 *
 * The OTP logic mirrors app/controllers/auth/otp_request.php and
 * otp_verify.php: 8-digit code, 10-minute expiry, 3 attempts,
 * 60-second resend throttle. Rate-limit tables are not used here —
 * the whole flow is bound to the locked installer session.
 */

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../auth/otp_helpers.php';
require_once __DIR__ . '/../communication/mailbox_helpers.php';
require_once __DIR__ . '/../communication/mail_delivery.php';

function databaseHasTables(PDO $pdo): bool
{
    $result = $pdo->query('SHOW TABLES');
    return $result !== false && $result->fetch() !== false;
}

function importInstallerSchema(PDO $pdo, ?string $schemaPath = null): array
{
    $path = $schemaPath ?? rtrim(dirname(__DIR__, 3), '/') . '/sql/schema.sql';
    $sql = file_get_contents($path);
    if ($sql === false) {
        return ['ok' => false, 'error' => 'Schema file not readable.'];
    }

    try {
        foreach (splitSqlStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    } catch (Throwable $error) {
        return ['ok' => false, 'error' => $error->getMessage()];
    }

    return ['ok' => true, 'error' => null];
}

function createInstallerAdminUser(PDO $pdo, string $email, string $displayName): array
{
    $email = strtolower(trim($email));
    $displayName = trim($displayName);
    $errors = [];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    // users.username is VARCHAR(50); a longer email would be truncated by
    // the database and could then never be found at login.
    if (mb_strlen($email) > 50) {
        $errors[] = 'Email address must be at most 50 characters.';
    }

    if ($displayName !== '' && mb_strlen($displayName) > 120) {
        $errors[] = 'Display name must be at most 120 characters.';
    }

    if ($errors) {
        return ['id' => 0, 'errors' => $errors];
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username');
        $stmt->execute([':username' => $email]);
        if ($stmt->fetch()) {
            return ['id' => 0, 'errors' => ['A user with this email already exists.']];
        }

        // Login is OTP-based; the password column is not used, so store an
        // unguessable random hash exactly like adminCreateUser() does.
        $randomPassword = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, display_name, password_hash, role)
             VALUES (:username, :display_name, :password_hash, :role)'
        );
        $stmt->execute([
            ':username' => $email,
            ':display_name' => $displayName !== '' ? $displayName : null,
            ':password_hash' => password_hash($randomPassword, PASSWORD_DEFAULT),
            ':role' => 'admin'
        ]);

        return ['id' => (int) $pdo->lastInsertId(), 'errors' => []];
    } catch (Throwable $error) {
        return ['id' => 0, 'errors' => ['Failed to create the admin user: ' . $error->getMessage()]];
    }
}

function issueInstallerOtp(PDO $pdo, string $email): array
{
    $email = strtolower(trim($email));

    $user = findUserByEmail($pdo, $email);
    if (!$user) {
        return ['ok' => false, 'error' => 'Admin user not found.'];
    }

    $existing = fetchOtpRecord($pdo, $email);
    $now = time();
    $attempts = 0;

    if ($existing) {
        $lastSentAt = strtotime((string) ($existing['last_sent_at'] ?? ''));
        $existingExpiresAt = strtotime((string) ($existing['expires_at'] ?? ''));
        $attempts = (int) ($existing['attempts'] ?? 0);

        if ($lastSentAt && ($now - $lastSentAt) < 60) {
            return [
                'ok' => false,
                'error' => sprintf('Please wait %d seconds before requesting another code.', 60 - ($now - $lastSentAt))
            ];
        }

        if ($existingExpiresAt && $existingExpiresAt < $now) {
            $attempts = 0;
        } elseif ($attempts >= 3) {
            return ['ok' => false, 'error' => 'Too many invalid attempts. Please request a new code.'];
        }
    }

    $code = (string) random_int(10000000, 99999999);
    saveOtpRecord(
        $pdo,
        (int) $user['id'],
        $email,
        password_hash($code, PASSWORD_DEFAULT),
        $now + 600,
        $attempts,
        $now
    );

    $mailbox = fetchGlobalMailbox($pdo);
    if (!$mailbox) {
        return ['ok' => false, 'error' => 'Global SMTP mailbox not configured.'];
    }

    $smtpPassword = decryptSettingValue($mailbox['smtp_password'] ?? '');
    $missingFields = [];
    if (trim((string) ($mailbox['smtp_host'] ?? '')) === '') {
        $missingFields[] = 'smtp_host';
    }
    if ((int) ($mailbox['smtp_port'] ?? 0) <= 0) {
        $missingFields[] = 'smtp_port';
    }
    if (trim((string) ($mailbox['smtp_username'] ?? '')) === '') {
        $missingFields[] = 'smtp_username';
    }
    if ($smtpPassword === '') {
        $missingFields[] = 'smtp_password';
    }

    if ($missingFields) {
        return ['ok' => false, 'error' => 'Global SMTP config missing: ' . implode(', ', $missingFields)];
    }

    $sent = sendEmailViaMailbox($pdo, $mailbox, [
        'to_emails' => $email,
        'subject' => 'BooKing login code',
        'body' => sprintf('Your BooKing login code is %s. It expires in 10 minutes.', $code),
        'from_email' => (string) ($mailbox['smtp_username'] ?? ''),
        'from_name' => $mailbox['display_name'] ?? ''
    ]);

    if (!$sent) {
        return ['ok' => false, 'error' => 'SMTP send failed. Check your SMTP settings and try again.'];
    }

    return ['ok' => true, 'error' => null];
}

function verifyInstallerOtp(PDO $pdo, string $email, string $code): array
{
    $email = strtolower(trim($email));
    $code = trim($code);

    $record = fetchOtpRecord($pdo, $email);
    if (!$record) {
        return ['ok' => false, 'error' => 'No code has been requested.'];
    }

    $user = findUserByEmail($pdo, $email);
    if (!$user) {
        deleteOtpRecord($pdo, $email);
        return ['ok' => false, 'error' => 'Admin user not found.'];
    }

    $expiresAt = strtotime((string) ($record['expires_at'] ?? ''));
    if (!$expiresAt || $expiresAt < time()) {
        deleteOtpRecord($pdo, $email);
        return ['ok' => false, 'error' => 'Your code has expired. Please request a new one.'];
    }

    $attempts = (int) ($record['attempts'] ?? 0);
    if ($attempts >= 3) {
        return ['ok' => false, 'error' => 'Too many invalid attempts. Please request a new code.'];
    }

    if (!preg_match('/^\d{8}$/', $code) || !password_verify($code, (string) ($record['code_hash'] ?? ''))) {
        $attempts = incrementOtpAttempts($pdo, $email);
        if ($attempts >= 3) {
            return ['ok' => false, 'error' => 'Too many invalid attempts. Please request a new code.'];
        }
        return [
            'ok' => false,
            'error' => sprintf('Invalid code. %d attempt(s) remaining.', max(0, 3 - $attempts))
        ];
    }

    deleteOtpRecord($pdo, $email);

    return ['ok' => true, 'error' => null, 'user_id' => (int) $user['id']];
}

function saveInstallerSetting(PDO $pdo, string $key, string $value): bool
{
    if (trim($value) === '') {
        return true;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value)
             VALUES (:setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => encryptSettingValue(trim($value))
        ]);
        return true;
    } catch (Throwable $error) {
        return false;
    }
}
