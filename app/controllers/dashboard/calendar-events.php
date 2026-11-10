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

$range = parseDashboardCalendarDateRange(
    (string) ($_GET['start'] ?? ''),
    (string) ($_GET['end'] ?? '')
);
$teamId = (int) ($_GET['team_id'] ?? 0);

if ($range === null) {
    $respond(400, ['error' => 'A valid calendar date range is required.']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $teams = fetchUserTeams($pdo, (int) ($currentUser['user_id'] ?? 0));
    $team = resolveDashboardCalendarTeam($teams, $teamId);
    if ($team === null) {
        $respond(403, ['error' => 'You do not have access to this team.']);
        exit;
    }

    [$startDate, $endDate] = $range;
    $showStmt = $pdo->prepare(
        'SELECT id, name, show_date, show_time, state, notes
         FROM team_shows
         WHERE team_id = :team_id
           AND state <> \'canceled\'
           AND show_date >= :start_date
           AND show_date < :end_date
         ORDER BY show_date, show_time, id'
    );
    $showStmt->execute([
        ':team_id' => $teamId,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    $taskStmt = $pdo->prepare(
        'SELECT id, title, due_date, description, is_template
         FROM team_tasks
         WHERE team_id = :team_id
           AND is_template = 0
           AND due_date IS NOT NULL
           AND due_date >= :start_date
           AND due_date < :end_date
         ORDER BY due_date, id'
    );
    $taskStmt->execute([
        ':team_id' => $teamId,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    $respond(200, buildDashboardCalendarEvents($showStmt->fetchAll(), $taskStmt->fetchAll(), $team));
} catch (Throwable $error) {
    logAction($currentUser['user_id'] ?? null, 'dashboard_calendar_load_error', $error->getMessage());
    $respond(500, ['error' => 'Failed to load calendar events.']);
}
