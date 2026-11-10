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
    try {
        logAction($currentUser['user_id'] ?? null, 'dashboard_calendar_teams_error', $error->getMessage());
    } catch (Throwable $loggingError) {
        // Keep the dashboard's team-loading error visible when the database is unavailable.
    }
}
require __DIR__ . '/../../views/dashboard/index.php';
