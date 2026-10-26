<?php
/**
 * Admin maintenance tab controller.
 * Expects: $currentUser, &$errors, &$notice, $pdo
 * Provides: $maintenanceState
 */

require_once __DIR__ . '/../../models/update/release_check.php';
require_once __DIR__ . '/../../models/update/updater.php';

$buildMaintenanceState = static function (?string $baseDir = null): array {
    $state = fetchUpdateCheckState();
    $state['lock'] = fetchUpdateLockState($baseDir);
    $state['running'] = isUpdateRunning($baseDir);
    $state['requirements'] = updateRequirements($baseDir);
    return $state;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_check_now') {
        try {
            runReleaseCheckTask($pdo, true);
            $notice = 'Release check completed.';
        } catch (Throwable $error) {
            $errors[] = 'Release check failed.';
            logAction($currentUser['user_id'] ?? null, 'update_check_error', $error->getMessage());
        }
    }

    if ($action === 'update_run') {
        $state = fetchUpdateCheckState();
        if (!$state['available'] || $state['zip_url'] === '') {
            $errors[] = 'No newer release available.';
        } elseif (isUpdateRunning()) {
            $errors[] = 'An update is already in progress.';
        } else {
            $result = runApplicationUpdate(
                ['tag' => $state['latest_version'], 'zip_url' => $state['zip_url']],
                'httpGetJson'
            );
            if ($result['ok']) {
                $notice = 'Updated to ' . $result['target_version'] . '.';
                logAction($currentUser['user_id'] ?? null, 'update_applied', 'Updated to ' . $result['target_version']);
                runReleaseCheckTask($pdo, true);
            } else {
                $errors[] = 'Update failed: ' . $result['error'];
                logAction($currentUser['user_id'] ?? null, 'update_failed', (string) $result['error']);
            }
        }
    }
}

$maintenanceState = $buildMaintenanceState();
$updateAvailable = (bool) ($maintenanceState['available'] ?? false);
