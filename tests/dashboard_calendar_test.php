<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', (string) (getenv('DASHBOARD_CALENDAR_TEST_BASE_PATH') ?: ''));
}
if (!function_exists('renderPageStart')) {
    function renderPageStart(string $title, array $options = []): void
    {
        $GLOBALS['dashboardCalendarPageOptions'] = $options;
    }
    function renderPageEnd(array $options = []): void {}
}
require_once __DIR__ . '/../app/models/dashboard/calendar_helpers.php';

final class DashboardCalendarTestPDO extends PDO
{
    public array $queries = [];
    public array $showRows = [];
    public array $taskRows = [];
    public bool $throwOnPrepare = false;

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->throwOnPrepare) {
            throw new RuntimeException('sensitive database details');
        }
        return new DashboardCalendarTestPDOStatement($this, $query);
    }

    public function rowsFor(string $query): array
    {
        return str_contains($query, 'FROM team_shows') ? $this->showRows : $this->taskRows;
    }
}

final class DashboardCalendarTestPDOStatement extends PDOStatement
{
    public function __construct(private DashboardCalendarTestPDO $pdo, private string $query) {}

    public function execute(?array $params = null): bool
    {
        $this->pdo->queries[] = ['sql' => $this->query, 'params' => $params ?? []];
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->pdo->rowsFor($this->query);
    }
}

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
$check($events[0]['extendedProps']['detailUrl'] === BASE_PATH . '/app/controllers/team/index.php?tab=shows&team_id=7&show_id=11', 'show event links to team show detail');
$check($events[1]['start'] === '2027-05-16' && $events[1]['allDay'] === true && $events[1]['title'] === 'Show', 'untimed show is an all-day event with a fallback name');
$check($events[1]['extendedProps']['description'] === '', 'normalizes nullable show notes to an empty preview value');
$check($events[2]['start'] === '2027-05-12' && $events[2]['allDay'] === true, 'task event uses all-day due date');
$check($events[2]['title'] === 'Book venue' && $events[2]['extendedProps']['description'] === 'Call the hall', 'task event includes title and description for its preview');
$check($events[2]['extendedProps']['detailUrl'] === BASE_PATH . '/app/controllers/team/index.php?tab=tasks&team_id=7&task_id=21', 'task event links to team task detail');
$check(resolveDashboardCalendarTeam([['id' => 7, 'name' => 'Brass Ensemble']], 7)['name'] === 'Brass Ensemble', 'accepts a team in the user membership list');
$check(resolveDashboardCalendarTeam([['id' => 7, 'name' => 'Brass Ensemble']], 8) === null, 'rejects a team outside the user membership list');
$check(parseDashboardCalendarDateRange('2027-05-01T00:00:00Z', '2027-06-01T00:00:00Z') === ['2027-05-01', '2027-06-01'], 'parses FullCalendar date range as an inclusive start and exclusive end');
$check(parseDashboardCalendarDateRange('2027-02-30', '2027-03-01') === null, 'rejects invalid calendar dates');
$check(parseDashboardCalendarDateRange('2027-05-01T99:00:00Z', '2027-06-01') === null, 'rejects malformed datetime values');
$check(parseDashboardCalendarDateRange('2027-06-01', '2027-05-01') === null, 'rejects reversed date ranges');
$request = parseDashboardCalendarRequest(['team_id' => '7', 'start' => '2027-05-01T00:00:00-07:00', 'end' => '2027-06-01T00:00:00-07:00']);
$check($request === ['teamId' => 7, 'range' => ['2027-05-01', '2027-06-01']], 'parses scalar team and range request params without shifting date-only bounds');
$check(parseDashboardCalendarRequest(['team_id' => ['7'], 'start' => '2027-05-01', 'end' => '2027-06-01']) === null, 'rejects array-valued team IDs');
$check(parseDashboardCalendarRequest(['team_id' => '7junk', 'start' => '2027-05-01', 'end' => '2027-06-01']) === null, 'rejects malformed team IDs rather than coercing them');
$check(parseDashboardCalendarRequest(['team_id' => '7', 'start' => ['2027-05-01'], 'end' => '2027-06-01']) === null, 'rejects array-valued date params');
$check(parseDashboardCalendarRequest(['team_id' => '0', 'start' => '2027-05-01', 'end' => '2027-06-01']) === null, 'rejects non-positive team IDs');

$unauthorizedPdo = new DashboardCalendarTestPDO();
$check(fetchDashboardCalendarEvents($unauthorizedPdo, [['id' => 7, 'name' => 'Brass']], 8, ['2027-05-01', '2027-06-01']) === null, 'does not return events for an unauthorized team');
$check($unauthorizedPdo->queries === [], 'rejects unauthorized teams before issuing event queries');

$authorizedPdo = new DashboardCalendarTestPDO();
$authorizedPdo->showRows = [['id' => 11, 'name' => 'Spring Concert', 'show_date' => '2027-05-14', 'show_time' => '19:30:00', 'state' => 'definitely', 'notes' => 'Doors at seven']];
$authorizedPdo->taskRows = [['id' => 21, 'title' => 'Book venue', 'due_date' => '2027-05-12', 'description' => 'Call the hall', 'is_template' => 0]];
$authorizedEvents = fetchDashboardCalendarEvents($authorizedPdo, [['id' => 7, 'name' => 'Brass']], 7, ['2027-05-01', '2027-06-01']);
$check(count($authorizedEvents ?? []) === 2, 'loads the authorized team show and task events');
$check(count($authorizedPdo->queries) === 2 && array_reduce($authorizedPdo->queries, static fn(bool $ok, array $query): bool => $ok && $query['params'] === [':team_id' => 7, ':start_date' => '2027-05-01', ':end_date' => '2027-06-01'], true), 'binds membership-approved team and inclusive/exclusive range in both queries');
$check(str_contains($authorizedPdo->queries[0]['sql'], "state <> 'canceled'") && str_contains($authorizedPdo->queries[1]['sql'], 'is_template = 0') && str_contains($authorizedPdo->queries[1]['sql'], 'due_date IS NOT NULL'), 'queries exclude canceled shows, templates, and undated tasks');
$emptyPdo = new DashboardCalendarTestPDO();
$check(fetchDashboardCalendarEvents($emptyPdo, [['id' => 7, 'name' => 'Brass']], 7, ['2027-05-01', '2027-06-01']) === [], 'returns an empty event list for a valid team with no dated entries');
$failingPdo = new DashboardCalendarTestPDO();
$failingPdo->throwOnPrepare = true;
$failedFeed = buildDashboardCalendarFeedResponse($failingPdo, [['id' => 7, 'name' => 'Brass']], 7, ['2027-05-01', '2027-06-01']);
$check($failedFeed === ['status' => 500, 'body' => ['error' => 'Failed to load calendar events.']], 'converts database exceptions into a generic feed error without leaking details');

$dashboardController = (string) file_get_contents(__DIR__ . '/../app/controllers/dashboard/index.php');
$eventsController = (string) file_get_contents(__DIR__ . '/../app/controllers/dashboard/calendar-events.php');
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
$check(!str_contains($singleTeamDashboard[0], 'data-calendar-team-toggle') && str_contains($singleTeamDashboard[0], 'data-calendar-team'), 'single-team view has no team toggle control');
$check(str_contains($singleTeamDashboard[0], 'data-calendar-dialog') && str_contains($singleTeamDashboard[0], 'data-calendar-dialog-link'), 'dashboard renders the event preview dialog and detail link');
$multipleTeamDashboard = $renderDashboard([['id' => 7, 'name' => 'Brass <One>'], ['id' => 8, 'name' => 'Strings']]);
$check(substr_count($multipleTeamDashboard[0], 'data-calendar-team-toggle') === 2, 'multiple-team view renders one toggle per team');
$check(str_contains($multipleTeamDashboard[0], 'Brass &lt;One&gt;'), 'team labels and attributes are HTML escaped');
$emptyDashboard = $renderDashboard([]);
$check(!str_contains($emptyDashboard[0], 'data-dashboard-calendar') && str_contains($emptyDashboard[0], 'Join a team'), 'zero-team dashboard renders a useful empty state without calendar controls');
$check(empty($emptyDashboard[1]['extraScripts'] ?? []) && empty($emptyDashboard[1]['extraStyles'] ?? []), 'dashboard does not load calendar assets when there are no teams');
$errorDashboard = $renderDashboard([], 'Unable to load your teams for the calendar.');
$check(str_contains($errorDashboard[0], 'role="alert"') && str_contains($errorDashboard[0], 'Unable to load your teams'), 'dashboard renders a visible team-loading error');
$check(str_contains($eventsController, 'fetchUserTeams') && str_contains($eventsController, 'buildDashboardCalendarFeedResponse'), 'event feed applies the current user membership before event queries');

if ($failures > 0) {
    echo "{$failures} dashboard calendar test(s) failed\n";
    exit(1);
}
echo "All dashboard calendar tests passed\n";
