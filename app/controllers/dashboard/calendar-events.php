<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/security_headers.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/dashboard/calendar_helpers.php';

setSecurityHeaders();
setNoCacheHeaders();
header('Content-Type: application/json; charset=utf-8');

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
};

$request = parseDashboardCalendarRequest($_GET);
if ($request === null) {
    $respond(400, ['error' => 'Invalid calendar request.']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $teams = fetchUserTeams($pdo, (int) ($currentUser['user_id'] ?? 0));
    $result = buildDashboardCalendarFeedResponse($pdo, $teams, $request['teamId'], $request['range']);
    if ($result['status'] === 500) {
        try {
            logAction($currentUser['user_id'] ?? null, 'dashboard_calendar_load_error', 'Calendar event query failed.');
        } catch (Throwable $loggingError) {
            // The generic JSON response must remain available if logging also fails.
        }
    }
    $respond($result['status'], $result['body']);
} catch (Throwable $error) {
    try {
        logAction($currentUser['user_id'] ?? null, 'dashboard_calendar_load_error', $error->getMessage());
    } catch (Throwable $loggingError) {
        // Keep the JSON error response available when the database is unreachable.
    }
    $respond(500, ['error' => 'Failed to load calendar events.']);
}
