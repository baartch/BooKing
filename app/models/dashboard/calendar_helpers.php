<?php

function resolveDashboardCalendarTeam(array $teams, int $requestedTeamId): ?array
{
    if ($requestedTeamId <= 0) {
        return null;
    }

    foreach ($teams as $team) {
        if ((int) ($team['id'] ?? 0) === $requestedTeamId) {
            return $team;
        }
    }

    return null;
}

function parseDashboardCalendarDateRange(string $start, string $end): ?array
{
    $startDate = substr($start, 0, 10);
    $endDate = substr($end, 0, 10);
    foreach ([$start, $end] as $value) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}(?:T.*)?$/', $value)) {
            return null;
        }
        $date = substr($value, 0, 10);
        if (!checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            return null;
        }
        if (strlen($value) > 10) {
            try {
                new DateTimeImmutable($value);
            } catch (Throwable $error) {
                return null;
            }
        }
    }

    if ($endDate <= $startDate) {
        return null;
    }

    return [$startDate, $endDate];
}

function parseDashboardCalendarRequest(array $query): ?array
{
    $teamValue = $query['team_id'] ?? null;
    $startValue = $query['start'] ?? null;
    $endValue = $query['end'] ?? null;
    if ((!is_string($teamValue) && !is_int($teamValue)) || !is_string($startValue) || !is_string($endValue)) {
        return null;
    }

    $teamId = filter_var($teamValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $range = parseDashboardCalendarDateRange($startValue, $endValue);
    if ($teamId === false || $range === null) {
        return null;
    }

    return ['teamId' => (int) $teamId, 'range' => $range];
}

function fetchDashboardCalendarEvents(PDO $pdo, array $teams, int $requestedTeamId, array $range): ?array
{
    $team = resolveDashboardCalendarTeam($teams, $requestedTeamId);
    if ($team === null || count($range) !== 2) {
        return null;
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
        ':team_id' => $requestedTeamId,
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
        ':team_id' => $requestedTeamId,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    return buildDashboardCalendarEvents($showStmt->fetchAll(), $taskStmt->fetchAll(), $team);
}

function buildDashboardCalendarFeedResponse(PDO $pdo, array $teams, int $requestedTeamId, array $range): array
{
    try {
        $events = fetchDashboardCalendarEvents($pdo, $teams, $requestedTeamId, $range);
    } catch (Throwable $error) {
        return ['status' => 500, 'body' => ['error' => 'Failed to load calendar events.']];
    }

    if ($events === null) {
        return ['status' => 403, 'body' => ['error' => 'You do not have access to this team.']];
    }

    return ['status' => 200, 'body' => $events];
}

function buildDashboardCalendarEvents(array $shows, array $tasks, array $team): array
{
    $teamId = (int) ($team['id'] ?? 0);
    $teamName = (string) ($team['name'] ?? '');
    $events = [];

    foreach ($shows as $show) {
        if (($show['state'] ?? '') === 'canceled' || empty($show['show_date'])) {
            continue;
        }

        $showId = (int) ($show['id'] ?? 0);
        $name = trim((string) ($show['name'] ?? ''));
        $start = (string) $show['show_date'];
        $showTime = trim((string) ($show['show_time'] ?? ''));
        if ($showTime !== '') {
            $start .= 'T' . $showTime;
        }

        $events[] = [
            'id' => 'show-' . $showId,
            'title' => $name !== '' ? $name : 'Show',
            'start' => $start,
            'allDay' => $showTime === '',
            'extendedProps' => [
                'kind' => 'show',
                'teamId' => $teamId,
                'teamName' => $teamName,
                'description' => (string) ($show['notes'] ?? ''),
                'detailUrl' => BASE_PATH . '/app/controllers/team/index.php?' . http_build_query([
                    'tab' => 'shows',
                    'team_id' => $teamId,
                    'show_id' => $showId,
                ]),
            ],
        ];
    }

    foreach ($tasks as $task) {
        if (!empty($task['is_template']) || empty($task['due_date'])) {
            continue;
        }

        $taskId = (int) ($task['id'] ?? 0);
        $events[] = [
            'id' => 'task-' . $taskId,
            'title' => (string) ($task['title'] ?? ''),
            'start' => (string) $task['due_date'],
            'allDay' => true,
            'extendedProps' => [
                'kind' => 'task',
                'teamId' => $teamId,
                'teamName' => $teamName,
                'description' => (string) ($task['description'] ?? ''),
                'detailUrl' => BASE_PATH . '/app/controllers/team/index.php?' . http_build_query([
                    'tab' => 'tasks',
                    'team_id' => $teamId,
                    'task_id' => $taskId,
                ]),
            ],
        ];
    }

    return $events;
}
