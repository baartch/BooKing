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
