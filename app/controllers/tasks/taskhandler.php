<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../models/core/defaults.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../models/communication/email_schedule.php';
require_once __DIR__ . '/../../models/core/object_links.php';
require_once __DIR__ . '/../../models/communication/mail_fetch_helpers.php';
require_once __DIR__ . '/schedule_send.php';
require_once __DIR__ . '/fetch_emails.php';
require_once __DIR__ . '/venue_task_triggers.php';
require_once __DIR__ . '/cleanup.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

try {
    $pdo = getDatabaseConnection();
    runScheduleSendTask($pdo);
    runFetchEmailsTask($pdo);
    runVenueTaskTriggerTask($pdo);

    $now = new DateTime('now');
    $cleanupTime = $now->format('H:i');
    if ($cleanupTime === '03:00') {
        runCleanupTask($pdo, $argv ?? []);
    }
} catch (Throwable $error) {
    echo "Task handler failed: " . $error->getMessage() . "\n";
    exit(1);
}
