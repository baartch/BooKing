<?php
/**
 * Daily release check state.
 *
 * The latest-release lookup result is cached in the settings table so page
 * renders and the sidebar dot never call GitHub; only the scheduled task
 * (or a manual admin request) performs network calls.
 */

require_once __DIR__ . '/../core/version.php';
require_once __DIR__ . '/version_compare.php';
require_once __DIR__ . '/github_client.php';

const UPDATE_CHECK_INTERVAL_SECONDS = 86400;

const UPDATE_SETTING_KEYS = [
    'update_latest_version',
    'update_latest_zip_url',
    'update_latest_notes_url',
    'update_last_check_at',
    'update_check_error',
];

/**
 * True when the last successful check is older than the check interval
 * (or missing/unparseable, in which case a check is always due).
 */
function releaseCheckDue(array $state, string $nowIso): bool
{
    $lastCheck = trim((string) ($state['last_check_at'] ?? ''));
    if ($lastCheck === '') {
        return true;
    }

    $lastCheckTime = strtotime($lastCheck);
    $nowTime = strtotime($nowIso);
    if ($lastCheckTime === false || $nowTime === false) {
        return true;
    }

    return ($nowTime - $lastCheckTime) >= UPDATE_CHECK_INTERVAL_SECONDS;
}

/**
 * The cached release-check state for UI rendering.
 *
 * @return array{installed_version: string, latest_version: string, zip_url: string,
 *               notes_url: string, last_check_at: string, check_error: string, available: bool}
 */
function fetchUpdateCheckState(): array
{
    $settings = loadSettingValues(UPDATE_SETTING_KEYS);
    $installed = installedAppVersion();
    $latest = (string) ($settings['update_latest_version'] ?? '');

    return [
        'installed_version' => $installed,
        'latest_version' => $latest,
        'zip_url' => (string) ($settings['update_latest_zip_url'] ?? ''),
        'notes_url' => (string) ($settings['update_latest_notes_url'] ?? ''),
        'last_check_at' => (string) ($settings['update_last_check_at'] ?? ''),
        'check_error' => (string) ($settings['update_check_error'] ?? ''),
        'available' => $latest !== '' && isNewerReleaseVersion($latest, $installed),
    ];
}

/**
 * True when a newer stable release is known (cached state, no network).
 */
function isUpdateAvailable(): bool
{
    return fetchUpdateCheckState()['available'];
}

/**
 * Scheduled task: refresh the cached latest-release state at most once a
 * day. Failures are recorded and never break the remaining cron tasks.
 */
function runReleaseCheckTask(PDO $pdo, bool $force = false): void
{
    try {
        $settings = loadSettingValues(UPDATE_SETTING_KEYS);
        $nowIso = gmdate('c');

        if (!$force && !releaseCheckDue($settings, $nowIso)) {
            return;
        }

        $release = fetchLatestStableRelease();
        if ($release['ok']) {
            saveSettingValue('update_latest_version', $release['tag']);
            saveSettingValue('update_latest_zip_url', $release['zip_url']);
            saveSettingValue('update_latest_notes_url', $release['notes_url']);
            saveSettingValue('update_check_error', '');
            saveSettingValue('update_last_check_at', $nowIso);
            logAction(null, 'update_check_done', 'Latest release: ' . $release['tag']);
            return;
        }

        // Keep the last known release; surface the failure to the admin UI.
        saveSettingValue('update_check_error', $release['error']);
        saveSettingValue('update_last_check_at', $nowIso);
        logAction(null, 'update_check_error', $release['error']);
    } catch (Throwable $error) {
        try {
            logAction(null, 'update_check_error', $error->getMessage());
        } catch (Throwable $ignored) {
            // Never let the check kill the cron run.
        }
    }
}
