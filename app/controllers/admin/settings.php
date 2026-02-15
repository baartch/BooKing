<?php
/**
 * Admin settings tabs controller (API keys + global SMTP).
 * Expects: $currentUser, &$errors, &$notice
 * Provides: $settings, $settingsStatus, $smtpMailbox
 */

$smtpMailbox = null;
$settings = [
    'brave_search_api_key' => '',
    'brave_spellcheck_api_key' => '',
    'mapbox_api_key_public' => '',
    'mapbox_api_key_server' => ''
];
$settingsStatus = [
    'brave_search_api_key' => false,
    'brave_spellcheck_api_key' => false,
    'mapbox_api_key_public' => false,
    'mapbox_api_key_server' => false
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_api_keys') {
        $settings = [
            'brave_search_api_key' => trim((string) ($_POST['brave_search_api_key'] ?? '')),
            'brave_spellcheck_api_key' => trim((string) ($_POST['brave_spellcheck_api_key'] ?? '')),
            'mapbox_api_key_public' => trim((string) ($_POST['mapbox_api_key_public'] ?? '')),
            'mapbox_api_key_server' => trim((string) ($_POST['mapbox_api_key_server'] ?? ''))
        ];

        try {
            $pdo = getDatabaseConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value)
                 VALUES (:setting_key, :setting_value)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );

            foreach ($settings as $key => $value) {
                if ($value === '') {
                    continue;
                }

                $stmt->execute([
                    ':setting_key' => $key,
                    ':setting_value' => encryptSettingValue($value)
                ]);
            }

            logAction($currentUser['user_id'] ?? null, 'settings_updated', 'Updated API keys');
            $notice = 'API keys saved successfully.';
        } catch (Throwable $error) {
            $errors[] = 'Failed to save API keys.';
            logAction($currentUser['user_id'] ?? null, 'settings_update_error', $error->getMessage());
        }
    }

    if ($action === 'save_global_smtp') {
        $smtpMailboxId = (int) ($_POST['mailbox_id'] ?? 0);
        $formResult = buildMailboxFormInput($_POST, [
            'allowed_encryptions' => ['ssl', 'tls', 'none'],
            'default_imap_port' => 993,
            'default_smtp_port' => 587,
            'require_team' => false,
            'require_imap' => false,
            'require_smtp' => true,
            'is_create' => $smtpMailboxId <= 0
        ]);

        $formValues = $formResult['values'];
        $formValues['name'] = 'Global SMTP';
        $imapPassword = $formResult['imap_password'];
        $smtpPassword = $formResult['smtp_password'];
        $errors = array_merge($errors, $formResult['errors']);

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $existingMailbox = fetchGlobalMailbox($pdo);
                if ($existingMailbox && $smtpMailboxId > 0 && $smtpMailboxId !== (int) $existingMailbox['id']) {
                    $errors[] = 'Another global SMTP mailbox already exists.';
                } else {
                    $mailboxId = persistMailbox($pdo, $formValues, $imapPassword, $smtpPassword, $existingMailbox ?: null);
                    logAction($currentUser['user_id'] ?? null, 'admin_smtp_saved', sprintf('Saved global SMTP mailbox %d', $mailboxId));
                    $notice = 'Global SMTP settings saved successfully.';
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to save SMTP settings.';
                logAction($currentUser['user_id'] ?? null, 'admin_smtp_save_error', $error->getMessage());
            }
        }
    }
}

try {
    $settings = loadSettingValues([
        'brave_search_api_key',
        'brave_spellcheck_api_key',
        'mapbox_api_key_public',
        'mapbox_api_key_server'
    ]);

    $settingsStatus = [
        'brave_search_api_key' => $settings['brave_search_api_key'] !== '',
        'brave_spellcheck_api_key' => $settings['brave_spellcheck_api_key'] !== '',
        'mapbox_api_key_public' => $settings['mapbox_api_key_public'] !== '',
        'mapbox_api_key_server' => $settings['mapbox_api_key_server'] !== ''
    ];

    $pdo = getDatabaseConnection();
    $smtpMailbox = fetchGlobalMailbox($pdo);
    $smtpMailbox = $smtpMailbox ?: null;
} catch (Throwable $error) {
    $settings = $settings ?? [
        'brave_search_api_key' => '',
        'brave_spellcheck_api_key' => '',
        'mapbox_api_key_public' => '',
        'mapbox_api_key_server' => ''
    ];
    $smtpMailbox = $smtpMailbox ?? null;
    $settingsStatus = $settingsStatus ?? [
        'brave_search_api_key' => false,
        'brave_spellcheck_api_key' => false,
        'mapbox_api_key_public' => false,
        'mapbox_api_key_server' => false
    ];
    $errors[] = 'Failed to load settings.';
    logAction($currentUser['user_id'] ?? null, 'admin_settings_load_error', $error->getMessage());
}
