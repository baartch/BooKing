<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}
if (!function_exists('renderPageStart')) {
    function renderPageStart(string $title, array $options = []): void
    {
        $GLOBALS['dashboardCalendarPageOptions'] = $options;
    }
    function renderPageEnd(array $options = []): void {}
}
require_once __DIR__ . '/../app/models/dashboard/calendar_helpers.php';

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

$team = ['id' => 7, 'name' => 'Brass Ensemble'];
$events = buildDashboardCalendarEvents(
    [
        ['id' => 11, 'name' => 'Spring Concert', 'show_date' => '2027-05-14', 'show_time' => '19:30:00', 'state' => 'definitely', 'notes' => 'Doors at seven'],
        ['id' => 12, 'name' => 'Canceled Concert', 'show_date' => '2027-05-15', 'show_time' => null, 'state' => 'canceled', 'notes' => 'Canceled'],
        ['id' => 13, 'name' => null, 'show_date' => '2027-05-16', 'show_time' => null, 'state' => 'provisional', 'notes' => null],
    ],
    [
        ['id' => 21, 'title' => 'Book venue', 'due_date' => '2027-05-12', 'description' => 'Call the hall', 'is_template' => 0],
        ['id' => 22, 'title' => 'No due date', 'due_date' => null, 'description' => null, 'is_template' => 0],
        ['id' => 23, 'title' => 'Task template', 'due_date' => '2027-05-13', 'description' => null, 'is_template' => 1],
    ],
    $team
);

$check(count($events) === 3, 'omits canceled shows, tasks without due dates, and templates');
$check($events[0]['id'] === 'show-11' && $events[0]['start'] === '2027-05-14T19:30:00', 'show event uses show date and time');
$check($events[0]['title'] === 'Spring Concert' && $events[0]['extendedProps']['description'] === 'Doors at seven', 'show event includes name and notes for its preview');
$check($events[0]['extendedProps']['detailUrl'] === '/app/controllers/team/index.php?tab=shows&team_id=7&show_id=11', 'show event links to team show detail');
$check($events[1]['start'] === '2027-05-16' && $events[1]['allDay'] === true && $events[1]['title'] === 'Show', 'untimed show is an all-day event with a fallback name');
$check($events[2]['start'] === '2027-05-12' && $events[2]['allDay'] === true, 'task event uses all-day due date');
$check($events[2]['title'] === 'Book venue' && $events[2]['extendedProps']['description'] === 'Call the hall', 'task event includes title and description for its preview');
$check($events[2]['extendedProps']['detailUrl'] === '/app/controllers/team/index.php?tab=tasks&team_id=7&task_id=21', 'task event links to team task detail');
$check(resolveDashboardCalendarTeam([['id' => 7, 'name' => 'Brass Ensemble']], 7)['name'] === 'Brass Ensemble', 'accepts a team in the user membership list');
$check(resolveDashboardCalendarTeam([['id' => 7, 'name' => 'Brass Ensemble']], 8) === null, 'rejects a team outside the user membership list');
$check(parseDashboardCalendarDateRange('2027-05-01T00:00:00Z', '2027-06-01T00:00:00Z') === ['2027-05-01', '2027-06-01'], 'parses FullCalendar date range as an inclusive start and exclusive end');
$check(parseDashboardCalendarDateRange('2027-02-30', '2027-03-01') === null, 'rejects invalid calendar dates');
$check(parseDashboardCalendarDateRange('2027-06-01', '2027-05-01') === null, 'rejects reversed date ranges');

$dashboardController = (string) file_get_contents(__DIR__ . '/../app/controllers/dashboard/index.php');
$eventsController = (string) file_get_contents(__DIR__ . '/../app/controllers/dashboard/calendar-events.php');
$calendarScript = (string) file_get_contents(__DIR__ . '/../src/dashboard-calendar.ts');
$renderDashboard = static function (array $teams, string $error = ''): array {
    $calendarTeams = $teams;
    $calendarError = $error;
    $currentUser = ['display_name' => 'Calendar Test', 'username' => 'calendar-test'];
    ob_start();
    require __DIR__ . '/../app/views/dashboard/index.php';
    return [(string) ob_get_clean(), $GLOBALS['dashboardCalendarPageOptions'] ?? []];
};
$singleTeamDashboard = $renderDashboard([['id' => 7, 'name' => 'Brass']]);
$check(str_contains($dashboardController, 'fetchUserTeams'), 'dashboard loads only teams the current user belongs to');
$check(str_contains($singleTeamDashboard[0], 'data-calendar-events-url') && str_contains(implode(' ', $singleTeamDashboard[1]['extraScripts'] ?? []), 'fullcalendar@7.1.4'), 'dashboard renders the calendar shell and pinned FullCalendar scripts');
$check(str_contains($calendarScript, "initialView: 'dayGridMonth'") && str_contains($calendarScript, 'timeGridWeek') && str_contains($calendarScript, 'timeGridDay'), 'calendar provides month, week, and day views with month default');
$check(str_contains($calendarScript, 'eventSources') && str_contains($calendarScript, 'data-calendar-team-toggle'), 'calendar uses per-team event sources and visibility toggles');
$check(str_contains($eventsController, 'fetchUserTeams') && str_contains($eventsController, 'resolveDashboardCalendarTeam'), 'event feed restricts requests to user team membership');
$check(str_contains($eventsController, 'show_date >= :start_date') && str_contains($eventsController, 'show_date < :end_date') && str_contains($eventsController, 'due_date >= :start_date') && str_contains($eventsController, 'due_date < :end_date'), 'event feed limits both event types to requested date range');

if ($failures > 0) {
    echo "{$failures} dashboard calendar test(s) failed\n";
    exit(1);
}
echo "All dashboard calendar tests passed\n";
