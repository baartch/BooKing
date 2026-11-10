<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../views/core/layout.php';

$calendarTeams = [];
$calendarError = '';
try {
    $calendarTeams = fetchUserTeams(getDatabaseConnection(), (int) ($currentUser['user_id'] ?? 0));
} catch (Throwable $error) {
    $calendarError = 'Unable to load your teams for the calendar.';
    logAction($currentUser['user_id'] ?? null, 'dashboard_calendar_teams_error', $error->getMessage());
}
require __DIR__ . '/../../views/dashboard/index.php';
