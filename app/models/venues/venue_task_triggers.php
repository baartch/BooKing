<?php
require_once __DIR__ . '/../core/database.php';

function fetchVenueTaskTriggers(PDO $pdo, int $venueId, int $teamId): array
{
    if ($venueId <= 0 || $teamId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT tt.*
         FROM team_tasks tt
         JOIN object_links ol
           ON ol.left_type = "task"
          AND ol.left_id = tt.id
          AND ol.right_type = "venue"
          AND ol.right_id = :venue_id
          AND ol.team_id = :link_team_id
         WHERE tt.team_id = :task_team_id
           AND tt.is_template = 1
         ORDER BY tt.trigger_month, tt.trigger_day, tt.title'
    );
    $stmt->execute([
        ':venue_id' => $venueId,
        ':link_team_id' => $teamId,
        ':task_team_id' => $teamId
    ]);

    return $stmt->fetchAll();
}

function fetchVenueTaskTrigger(PDO $pdo, int $venueId, int $teamId, int $taskId): ?array
{
    if ($venueId <= 0 || $teamId <= 0 || $taskId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT tt.*
         FROM team_tasks tt
         JOIN object_links ol
           ON ol.left_type = "task"
          AND ol.left_id = tt.id
          AND ol.right_type = "venue"
          AND ol.right_id = :venue_id
          AND ol.team_id = :link_team_id
         WHERE tt.team_id = :task_team_id
           AND tt.is_template = 1
           AND tt.id = :task_id
         LIMIT 1'
    );
    $stmt->execute([
        ':venue_id' => $venueId,
        ':link_team_id' => $teamId,
        ':task_team_id' => $teamId,
        ':task_id' => $taskId
    ]);
    $trigger = $stmt->fetch();

    return $trigger ?: null;
}
