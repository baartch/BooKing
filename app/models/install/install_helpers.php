<?php
/**
 * Installer Helpers
 *
 * Pure helper logic for the fresh-install installer (install.php).
 * MUST NOT require config/config.php or any file that does — the
 * installer runs before the config file exists.
 *
 * Database- and mail-dependent flow helpers live in
 * app/models/install/install_flow_helpers.php and are loaded by the
 * installer controller only once config.php has been written.
 */

const INSTALLER_STEPS = ['welcome', 'database', 'admin', 'smtp', 'otp', 'keys', 'done'];

/**
 * Absolute path of the installer lock file.
 *
 * The lock is stored inside config/ because that directory is already
 * denied to web access by config/.htaccess. It never contains secrets:
 * only state, the session hash, progress markers and the admin email.
 */
function installerLockPath(?string $baseDir = null): string
{
    $base = $baseDir !== null ? rtrim($baseDir, '/') : rtrim(dirname(__DIR__, 3), '/');
    return $base . '/config/installer.lock';
}

/**
 * Read and decode the installer lock file. Returns null if absent or corrupt.
 */
function fetchInstallerLock(?string $lockPath = null): ?array
{
    $path = $lockPath ?? installerLockPath();
    if (!is_file($path)) {
        return null;
    }

    $raw = (string) file_get_contents($path);
    $lock = json_decode($raw, true);
    if (!is_array($lock)) {
        return null;
    }

    return $lock;
}

/**
 * Atomically claim the installer lock for a setup session.
 *
 * Returns one of:
 *  - ['ok' => true, 'lock' => array]  claimed or resumed by this session
 *  - ['ok' => false, 'error' => 'locked']     claimed by another session
 *  - ['ok' => false, 'error' => 'completed']  installation already completed
 *  - ['ok' => false, 'error' => 'invalid']    lock file unreadable/corrupt
 */
function claimInstallerLock(string $sessionHash, ?string $lockPath = null): array
{
    $path = $lockPath ?? installerLockPath();

    if (file_exists($path)) {
        $lock = fetchInstallerLock($path);
        if ($lock === null) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        if (($lock['state'] ?? '') === 'completed') {
            return ['ok' => false, 'error' => 'completed'];
        }

        if (!hash_equals((string) ($lock['session'] ?? ''), $sessionHash)) {
            return ['ok' => false, 'error' => 'locked'];
        }

        return ['ok' => true, 'lock' => $lock];
    }

    $lock = [
        'state' => 'started',
        'session' => $sessionHash,
        'created_at' => gmdate('c'),
        'step' => 'database',
        'config_written' => false,
        'schema_imported' => false,
        'admin_email' => null
    ];

    $handle = @fopen($path, 'x');
    if ($handle === false) {
        if (!is_dir(dirname($path)) || !is_writable(dirname($path))) {
            return ['ok' => false, 'error' => 'unwritable'];
        }
        // Lost the race against another request that created the file.
        return ['ok' => false, 'error' => 'locked'];
    }

    fwrite($handle, (string) json_encode($lock));
    fclose($handle);

    return ['ok' => true, 'lock' => $lock];
}

/**
 * Persist (updated) lock state atomically via a temp file + rename.
 */
function saveInstallerLock(array $lock, ?string $lockPath = null): bool
{
    $path = $lockPath ?? installerLockPath();
    $tmpPath = $path . '.tmp';

    if (file_put_contents($tmpPath, (string) json_encode($lock)) === false) {
        return false;
    }

    return rename($tmpPath, $path);
}

/**
 * Mark the installation as permanently completed.
 */
function completeInstallerLock(?string $lockPath = null): bool
{
    $path = $lockPath ?? installerLockPath();
    $lock = fetchInstallerLock($path) ?? [
        'state' => 'started',
        'session' => '',
        'created_at' => gmdate('c'),
        'step' => 'database',
        'config_written' => false,
        'schema_imported' => false,
        'admin_email' => null
    ];

    $lock['state'] = 'completed';
    $lock['completed_at'] = gmdate('c');
    $lock['step'] = 'done';

    return saveInstallerLock($lock, $path);
}

/**
 * Clamp the requested step to the furthest step the lock allows.
 * Completed locks clamp everything to 'done'.
 */
function resolveInstallerStep(array $lock, string $requested): string
{
    $steps = INSTALLER_STEPS;

    if (($lock['state'] ?? '') === 'completed') {
        return 'done';
    }

    $current = in_array($lock['step'] ?? '', $steps, true) ? $lock['step'] : 'welcome';
    if (!in_array($requested, $steps, true)) {
        return $current;
    }

    if (array_search($requested, $steps, true) > array_search($current, $steps, true)) {
        return $current;
    }

    return $requested;
}

/**
 * Hash of the current PHP session id, stored in the lock to bind setup
 * to the browser session that started it.
 */
function installerSessionIdHash(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return hash('sha256', session_id());
}

/**
 * CSRF token for installer forms (kept in the installer's own session;
 * the app-wide csrf.php helpers require config.php which may not exist yet).
 */
function getInstallerCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['install_csrf_token'])) {
        $_SESSION['install_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['install_csrf_token'];
}

function verifyInstallerCsrfToken(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['install_csrf_token'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['install_csrf_token'], $token);
}

/**
 * The step a POST action belongs to, or null for 'start' (which creates
 * the lock) and unknown actions. Used to resolve the step on POST
 * requests, where forms do not carry a ?step= query.
 */
function installerActionStep(string $action): ?string
{
    $map = [
        'save_database' => 'database',
        'check_config_upload' => 'database',
        'create_admin' => 'admin',
        'save_smtp' => 'smtp',
        'resend_otp' => 'otp',
        'verify_otp' => 'otp',
        'save_keys' => 'keys'
    ];

    return $map[$action] ?? null;
}

/**
 * Cryptographically random encryption key (64 hex chars = 32 bytes).
 */
function generateEncryptionKey(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Render the config.php file content. Values are escaped with var_export.
 */
function buildConfigFileContent(string $host, int $port, string $name, string $user, string $password, string $encryptionKey): string
{
    $lines = [
        '<?php',
        '// Configuration file for BooKing',
        '// Generated by the BooKing installer on ' . gmdate('Y-m-d\TH:i:s\Z') . '.',
        '// Keep a backup of this file — the ENCRYPTION_KEY cannot be regenerated',
        '// without losing access to all encrypted settings.',
        '',
        "define('DB_HOST', " . var_export($host, true) . ");",
        "define('DB_PORT', " . var_export($port, true) . ");",
        "define('DB_NAME', " . var_export($name, true) . ");",
        "define('DB_USER', " . var_export($user, true) . ");",
        "define('DB_PASSWORD', " . var_export($password, true) . ");",
        '',
        '// Encryption key for settings values (32+ chars recommended)',
        "define('ENCRYPTION_KEY', " . var_export($encryptionKey, true) . ");",
        '',
        '// Session configuration',
        '// Managed in the database using the sessions table',
        '// Sessions expire after 24 hours of inactivity, capped at 7 days from creation',
        '// (Idle timeout is refreshed on each authenticated request).',
        "define('SESSION_IDLE_LIFETIME', 86400); // 24 hours in seconds",
        "define('SESSION_REFRESH_INTERVAL', 21600); // Refresh DB expiry at most once every 6 hours",
        "define('SESSION_MAX_LIFETIME', 604800); // 7 days in seconds"
    ];

    return implode("\n", $lines) . "\n";
}

/**
 * Validate database connection input. Returns a list of error strings.
 */
function validateDatabaseInput(string $host, int $port, string $name, string $user): array
{
    $errors = [];

    if (trim($host) === '') {
        $errors[] = 'Database host is required.';
    }

    if ($port < 1 || $port > 65535) {
        $errors[] = 'Database port must be between 1 and 65535.';
    }

    if (trim($name) === '') {
        $errors[] = 'Database name is required.';
    }

    if (trim($user) === '') {
        $errors[] = 'Database user is required.';
    }

    return $errors;
}

/**
 * Write config.php. Returns false when the web server cannot write it
 * (installer then offers the download/FTP-upload fallback).
 */
function writeInstallerConfigFile(string $content, ?string $baseDir = null): bool
{
    $base = $baseDir !== null ? rtrim($baseDir, '/') : rtrim(dirname(__DIR__, 3), '/');
    return file_put_contents($base . '/config/config.php', $content) !== false;
}

function installerConfigFileExists(?string $baseDir = null): bool
{
    $base = $baseDir !== null ? rtrim($baseDir, '/') : rtrim(dirname(__DIR__, 3), '/');
    return is_file($base . '/config/config.php');
}

/**
 * Split an SQL dump into individual statements:
 * strips `-- ...` line comments, splits on `;`, drops empties.
 *
 * Not string-literal aware: a semicolon or `--` inside a VALUES string
 * would be misinterpreted. sql/schema.sql contains none (verified by
 * tests/install_test.php); revisit this if the schema grows literals
 * containing either.
 */
function splitSqlStatements(string $sql): array
{
    $withoutComments = preg_replace('/--[^\r\n]*/', '', $sql);
    $parts = explode(';', (string) $withoutComments);

    $statements = [];
    foreach ($parts as $part) {
        $statement = trim($part);
        if ($statement !== '') {
            $statements[] = $statement;
        }
    }

    return $statements;
}

/**
 * Required PHP extension checks against a list of loaded extension names.
 * Each result: ['label' => string, 'ok' => bool].
 */
function checkInstallerExtensionRequirements(array $loadedExtensions): array
{
    $loaded = array_map('strtolower', $loadedExtensions);
    $required = ['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'imap'];

    $results = [
        ['label' => 'php_version', 'ok' => PHP_VERSION_ID >= 80000]
    ];

    foreach ($required as $extension) {
        $results[] = ['label' => $extension, 'ok' => in_array($extension, $loaded, true)];
    }

    return $results;
}

/**
 * Environment checks: HTTPS, writable config dir, readable schema,
 * compiled JS assets present. Each result: ['label' => string, 'ok' => bool].
 */
function checkInstallerEnvironmentRequirements(array $server, ?string $baseDir = null): array
{
    $base = $baseDir !== null ? rtrim($baseDir, '/') : rtrim(dirname(__DIR__, 3), '/');

    $isHttps = (isset($server['HTTPS']) && $server['HTTPS'] === 'on')
        || (isset($server['HTTP_X_FORWARDED_PROTO']) && $server['HTTP_X_FORWARDED_PROTO'] === 'https');

    return [
        ['label' => 'https', 'ok' => $isHttps],
        ['label' => 'config_writable', 'ok' => is_dir($base . '/config') && is_writable($base . '/config')],
        ['label' => 'schema_readable', 'ok' => is_file($base . '/sql/schema.sql')],
        [
            'label' => 'compiled_assets',
            'ok' => count((array) glob($base . '/app/public/js/*.js')) > 0
        ]
    ];
}
