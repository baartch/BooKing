<?php
/**
 * BooKing Installer Controller (entry: /install.php)
 *
 * Browser-based fresh-install wizard for FTP/Plesk hosting users:
 *  preflight checks -> database -> config.php -> schema import ->
 *  first admin -> global SMTP -> OTP verification -> optional API keys.
 *
 * The process is locked to the browser session that started it
 * (config/installer.lock) and permanently disabled once completed.
 */

require_once __DIR__ . '/../../models/core/defaults.php';
require_once __DIR__ . '/../../models/install/install_helpers.php';

// Installer session, separate from the app auth session.
$installerIsSecure = isSecureConnection();
if (session_status() === PHP_SESSION_NONE) {
    session_name($installerIsSecure ? '__Host-booking_install' : 'booking_install');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $installerIsSecure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

$errors = [];
$notice = '';

// Flash messages across redirects (within the same installer session).
if (!empty($_SESSION['install_flash_error'])) {
    $errors[] = (string) $_SESSION['install_flash_error'];
    unset($_SESSION['install_flash_error']);
}
if (!empty($_SESSION['install_flash_notice'])) {
    $notice = (string) $_SESSION['install_flash_notice'];
    unset($_SESSION['install_flash_notice']);
}

$sessionHash = installerSessionIdHash();
$action = (string) ($_POST['action'] ?? '');
$requestedStep = (string) ($_GET['step'] ?? 'welcome');
$installerUrl = BASE_PATH . '/install.php';
$lockFileExists = is_file(installerLockPath());
$lock = fetchInstallerLock();
$step = 'welcome';
$blockReason = '';

if ($lock === null && installerConfigFileExists()) {
    // An installation already exists; the installer must never start.
    $step = 'blocked';
    $blockReason = 'existing';
}

if ($step !== 'blocked' && $lock === null && $lockFileExists) {
    // The lock file exists but cannot be decoded — refuse rather than
    // treat the installation as unclaimed.
    $step = 'blocked';
    $blockReason = 'invalid';
}

if ($step !== 'blocked' && $lock !== null) {
    $claim = claimInstallerLock($sessionHash);
    if (!$claim['ok']) {
        $step = 'blocked';
        $blockReason = (string) $claim['error'];
    } else {
        $lock = $claim['lock'];
        // POST actions resolve their step from the action itself (forms do
        // not carry a ?step= query); GET requests use the ?step= query.
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $actionStep = $isPost ? installerActionStep($action) : null;
        $step = resolveInstallerStep($lock, $actionStep !== null ? $actionStep : $requestedStep);
        if (
            !$isPost
            && ($requestedStep === 'welcome')
            && ($lock['step'] ?? 'welcome') !== 'welcome'
        ) {
            header('Location: ' . $installerUrl . '?step=' . urlencode((string) $lock['step']));
            exit;
        }
    }
}

if ($step !== 'blocked') {
    // Steps from admin onward need config.php + database/mail helpers.
    // The database step loads them itself: config.php may only come into
    // existence during its POST action.
    if (in_array($step, ['admin', 'smtp', 'otp', 'keys', 'done'], true)) {
        if (!installerConfigFileExists()) {
            $step = 'database';
            $errors[] = 'config/config.php is missing. Complete the database step first.';
        } else {
            require_once dirname(__DIR__, 3) . '/config/config.php';
            require_once __DIR__ . '/../../models/install/install_flow_helpers.php';
        }
    }
}

// Config download fallback (session-bound; only while on the database step).
if (
    $requestedStep === 'config_download'
    && $step === 'database'
    && !empty($_SESSION['install_config_content'])
) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="config.php"');
    header('Cache-Control: private, no-store');
    echo (string) $_SESSION['install_config_content'];
    exit;
}

// ------------------------------------------------------------------ actions
if ($step !== 'blocked' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verifyInstallerCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $errors[] = 'Invalid or expired form token. Please go back and try again.';
    } else {
        switch ($action) {
            case 'start':
                if ($lock !== null) {
                    header('Location: ' . $installerUrl . '?step=' . urlencode((string) $lock['step']));
                    exit;
                }

                $extResults = checkInstallerExtensionRequirements(get_loaded_extensions());
                $envResults = checkInstallerEnvironmentRequirements($_SERVER);
                $preflightFailed = false;
                foreach (array_merge($extResults, $envResults) as $result) {
                    if (!$result['ok']) {
                        $preflightFailed = true;
                        break;
                    }
                }

                if ($preflightFailed) {
                    $errors[] = 'Requirements not met. Fix the failed checks above and try again.';
                    break;
                }

                // Fresh session id for the newly claimed setup, then bind
                // the lock to it.
                session_regenerate_id(true);
                $sessionHash = installerSessionIdHash();
                $claim = claimInstallerLock($sessionHash);
                if (!$claim['ok']) {
                    $step = 'blocked';
                    $blockReason = (string) $claim['error'];
                    break;
                }

                header('Location: ' . $installerUrl . '?step=database');
                exit;

            case 'save_database':
                if ($step !== 'database') {
                    break;
                }

                if (!empty($lock['config_written'])) {
                    // Resuming with config.php already in place (FTP upload
                    // fallback or a retry after a failed schema import).
                    require_once dirname(__DIR__, 3) . '/config/config.php';
                    require_once __DIR__ . '/../../models/install/install_flow_helpers.php';
                }

                if (empty($lock['config_written'])) {
                    $host = trim((string) ($_POST['db_host'] ?? ''));
                    $port = (int) ($_POST['db_port'] ?? 3306);
                    $name = trim((string) ($_POST['db_name'] ?? ''));
                    $user = trim((string) ($_POST['db_user'] ?? ''));
                    $password = (string) ($_POST['db_password'] ?? '');

                    $errors = validateDatabaseInput($host, $port, $name, $user);
                    if ($errors) {
                        break;
                    }

                    try {
                        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
                        $testPdo = new PDO($dsn, $user, $password, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                        ]);
                    } catch (Throwable $error) {
                        $errors[] = 'Could not connect to the database: ' . $error->getMessage();
                        break;
                    }

                    if (databaseHasTables($testPdo)) {
                        $errors[] = 'The database is not empty. Create an empty database in your hosting panel '
                            . '(or drop all its tables) and try again.';
                        break;
                    }

                    if (empty($_SESSION['install_encryption_key'])) {
                        $_SESSION['install_encryption_key'] = generateEncryptionKey();
                    }

                    $configContent = buildConfigFileContent(
                        $host,
                        $port,
                        $name,
                        $user,
                        $password,
                        (string) $_SESSION['install_encryption_key']
                    );

                    if (writeInstallerConfigFile($configContent)) {
                        $lock['config_written'] = true;
                        unset($_SESSION['install_config_content']);
                    } else {
                        $_SESSION['install_config_content'] = $configContent;
                        $errors[] = 'PHP cannot write config/config.php. Download the generated file below, '
                            . 'upload it to the config/ directory via FTP, then press "I uploaded the file".';
                        break;
                    }

                    require_once dirname(__DIR__, 3) . '/config/config.php';
                    require_once __DIR__ . '/../../models/install/install_flow_helpers.php';
                }

                $pdo = getDatabaseConnection();

                if (empty($lock['schema_imported'])) {
                    $import = importInstallerSchema($pdo);
                    if (!$import['ok']) {
                        $errors[] = 'Schema import failed: ' . (string) $import['error']
                            . ' — the database may be partially initialized. Drop all its tables in your hosting '
                            . 'panel and try again.';
                        break;
                    }
                    $lock['schema_imported'] = true;
                }

                $lock['step'] = 'admin';
                if (!saveInstallerLock($lock)) {
                    $errors[] = 'Could not update config/installer.lock. Check that the config/ directory '
                        . 'is writable and try again.';
                    break;
                }
                header('Location: ' . $installerUrl . '?step=admin');
                exit;

            case 'check_config_upload':
                if ($step !== 'database' || !empty($lock['config_written'])) {
                    break;
                }

                if (!installerConfigFileExists()) {
                    $errors[] = 'config/config.php was not found yet. Upload the downloaded file into the '
                        . 'config/ directory via FTP and try again.';
                    break;
                }

                $lock['config_written'] = true;
                unset($_SESSION['install_config_content']);
                if (!saveInstallerLock($lock)) {
                    $errors[] = 'Could not update config/installer.lock. Check that the config/ directory '
                        . 'is writable and try again.';
                    break;
                }
                header('Location: ' . $installerUrl . '?step=database');
                exit;

            case 'create_admin':
                if ($step !== 'admin') {
                    break;
                }

                $email = trim((string) ($_POST['admin_email'] ?? ''));
                $displayName = trim((string) ($_POST['display_name'] ?? ''));

                $result = createInstallerAdminUser(getDatabaseConnection(), $email, $displayName);
                if (!empty($result['errors'])) {
                    $errors = array_merge($errors, $result['errors']);
                    break;
                }

                logAction((int) $result['id'], 'install_admin_created', sprintf('Installer created admin user %s', strtolower($email)));

                $lock['admin_email'] = strtolower(trim($email));
                $lock['step'] = 'smtp';
                if (!saveInstallerLock($lock)) {
                    $errors[] = 'Could not update config/installer.lock. Check that the config/ directory '
                        . 'is writable and try again.';
                    break;
                }
                header('Location: ' . $installerUrl . '?step=smtp');
                exit;

            case 'save_smtp':
                if ($step !== 'smtp') {
                    break;
                }

                $pdo = getDatabaseConnection();
                $existing = fetchGlobalMailbox($pdo);

                $formResult = buildMailboxFormInput($_POST, [
                    'allowed_encryptions' => ['ssl', 'tls', 'none'],
                    'default_imap_port' => 993,
                    'default_smtp_port' => 587,
                    'require_team' => false,
                    'require_imap' => false,
                    'require_smtp' => true,
                    'is_create' => $existing === null
                ]);

                $errors = array_merge($errors, $formResult['errors']);
                if ($errors) {
                    break;
                }

                $values = $formResult['values'];
                $values['name'] = 'Global SMTP';
                $mailboxId = persistMailbox(
                    $pdo,
                    $values,
                    $formResult['imap_password'],
                    $formResult['smtp_password'],
                    $existing
                );
                logAction(null, 'install_smtp_saved', sprintf('Installer saved global SMTP mailbox %d', $mailboxId));

                $lock['step'] = 'otp';
                if (!saveInstallerLock($lock)) {
                    $errors[] = 'Could not update config/installer.lock. Check that the config/ directory '
                        . 'is writable and try again.';
                    break;
                }

                $issue = issueInstallerOtp($pdo, (string) $lock['admin_email']);
                if (!$issue['ok']) {
                    $_SESSION['install_flash_error'] = 'SMTP settings saved, but sending the verification code '
                        . 'failed: ' . (string) $issue['error'];
                } else {
                    $_SESSION['install_flash_notice'] = 'A verification code was sent to '
                        . (string) $lock['admin_email'] . '.';
                }

                header('Location: ' . $installerUrl . '?step=otp');
                exit;

            case 'resend_otp':
                if ($step !== 'otp') {
                    break;
                }

                $issue = issueInstallerOtp(getDatabaseConnection(), (string) $lock['admin_email']);
                if (!$issue['ok']) {
                    $_SESSION['install_flash_error'] = (string) $issue['error'];
                } else {
                    $_SESSION['install_flash_notice'] = 'A new verification code was sent to '
                        . (string) $lock['admin_email'] . '.';
                }

                header('Location: ' . $installerUrl . '?step=otp');
                exit;

            case 'verify_otp':
                if ($step !== 'otp') {
                    break;
                }

                $code = trim((string) ($_POST['otp_code'] ?? ''));
                $verify = verifyInstallerOtp(getDatabaseConnection(), (string) $lock['admin_email'], $code);

                if (!$verify['ok']) {
                    $_SESSION['install_flash_error'] = (string) $verify['error'];
                    header('Location: ' . $installerUrl . '?step=otp');
                    exit;
                }

                logAction((int) $verify['user_id'], 'install_verified', 'Installer OTP verified — installation completed.');

                $lock['step'] = 'keys';
                if (!saveInstallerLock($lock)) {
                    $errors[] = 'Could not update config/installer.lock. Check that the config/ directory '
                        . 'is writable and try again.';
                    break;
                }
                header('Location: ' . $installerUrl . '?step=keys');
                exit;

            case 'save_keys':
                if ($step !== 'keys') {
                    break;
                }

                $pdo = getDatabaseConnection();
                $optionalKeys = [
                    'mapbox_api_key_public' => (string) ($_POST['mapbox_api_key_public'] ?? ''),
                    'mapbox_api_key_server' => (string) ($_POST['mapbox_api_key_server'] ?? ''),
                    'brave_search_api_key' => (string) ($_POST['brave_search_api_key'] ?? ''),
                    'brave_spellcheck_api_key' => (string) ($_POST['brave_spellcheck_api_key'] ?? '')
                ];

                foreach ($optionalKeys as $key => $value) {
                    if (!saveInstallerSetting($pdo, $key, $value)) {
                        $errors[] = 'Failed to save ' . $key . '.';
                    }
                }

                if ($errors) {
                    break;
                }

                if (!completeInstallerLock()) {
                    $errors[] = 'The installation is complete, but the lock file could not be written. '
                        . 'Delete install.php from the server via FTP now to disable the installer.';
                }
                logAction(null, 'install_completed', 'Installation completed via installer.');
                $step = 'done';
                break;
        }
    }
}

// -------------------------------------------------------------------- views
if ($step === 'blocked') {
    require __DIR__ . '/../../views/install/blocked.php';
    exit;
}

if ($step === 'welcome') {
    $extResults = checkInstallerExtensionRequirements(get_loaded_extensions());
    $envResults = checkInstallerEnvironmentRequirements($_SERVER);
}

require __DIR__ . '/../../views/install/installer.php';
