<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../src-php/core/defaults.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../models/auth/csrf.php';
require_once __DIR__ . '/../../models/auth/rate_limit.php';
require_once __DIR__ . '/../../models/auth/otp_helpers.php';
require_once __DIR__ . '/../../src-php/communication/mailbox_helpers.php';
require_once __DIR__ . '/../../src-php/communication/mail_delivery.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$clientIp = getClientIdentifier();

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=invalid');
    exit;
}

$rateLimit = checkRateLimit($clientIp, 'otp_request', 10, 3600);
if (!$rateLimit['allowed']) {
    $error = sprintf(
        'Too many OTP requests from your IP address. Please try again in %s.',
        formatRateLimitReset($rateLimit['reset_at'])
    );
    header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=' . urlencode($error));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $user = findUserByEmail($pdo, $email);
    if (!$user) {
        recordRateLimitAttempt($clientIp, 'otp_request', false);
        header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=unknown');
        exit;
    }

    $existing = fetchOtpRecord($pdo, $email);
    $now = time();
    $attempts = 0;
    if ($existing) {
        $lastSentAt = strtotime((string) ($existing['last_sent_at'] ?? ''));
        $existingExpiresAt = strtotime((string) ($existing['expires_at'] ?? ''));
        $attempts = (int) ($existing['attempts'] ?? 0);

        if ($lastSentAt && ($now - $lastSentAt) < 60) {
            $waitSeconds = 60 - ($now - $lastSentAt);
            $error = sprintf('Please wait %d seconds before requesting another code.', $waitSeconds);
            header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=' . urlencode($error));
            exit;
        }

        if ($existingExpiresAt && $existingExpiresAt < $now) {
            $attempts = 0;
        } elseif ($attempts >= 3) {
            header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=locked');
            exit;
        }
    }

    $code = (string) random_int(10000000, 99999999);
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    $expiresAt = $now + 600;

    saveOtpRecord(
        $pdo,
        (int) $user['id'],
        $email,
        $codeHash,
        $expiresAt,
        $attempts,
        $now
    );

    $mailbox = fetchGlobalMailbox($pdo);
    if (!$mailbox) {
        recordRateLimitAttempt($clientIp, 'otp_request', false);
        logAction((int) $user['id'], 'otp_send_error', 'Global SMTP mailbox not configured');
        header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=send');
        exit;
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
        recordRateLimitAttempt($clientIp, 'otp_request', false);
        logAction(
            (int) $user['id'],
            'otp_send_error',
            'Global SMTP config missing: ' . implode(', ', $missingFields)
        );
        header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=send');
        exit;
    }

    $subject = 'BooKing login code';
    $body = sprintf("Your BooKing login code is %s. It expires in 10 minutes.", $code);
    $sent = sendEmailViaMailbox($pdo, $mailbox, [
        'to_emails' => $email,
        'subject' => $subject,
        'body' => $body,
        'from_email' => (string) ($mailbox['smtp_username'] ?? ''),
        'from_name' => $mailbox['display_name'] ?? ''
    ]);

    if (!$sent) {
        recordRateLimitAttempt($clientIp, 'otp_request', false);
        logAction((int) $user['id'], 'otp_send_error', 'SMTP send failed');
        header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=send');
        exit;
    }

    recordRateLimitAttempt($clientIp, 'otp_request', false);
    logAction((int) $user['id'], 'otp_sent', sprintf('OTP sent to %s', $email));

    $notice = $existing ? '&notice=resent' : '';
    header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=otp&email=' . urlencode($email) . $notice);
    exit;
} catch (Throwable $error) {
    logAction(null, 'otp_request_error', $error->getMessage());
    header('Location: ' . BASE_PATH . '/app/controllers/auth/login.php?step=email&error=send');
    exit;
}
