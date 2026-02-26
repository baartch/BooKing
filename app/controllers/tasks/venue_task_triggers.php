<?php
require_once __DIR__ . '/../../models/core/object_links.php';

function runVenueTaskTriggerTask(PDO $pdo): void
{
    $today = new DateTime('now');
    $day = (int) $today->format('j');
    $month = (int) $today->format('n');
    $year = (int) $today->format('Y');

    $stmt = $pdo->prepare(
        'SELECT tt.*, ol.right_id AS venue_id
         FROM team_tasks tt
         JOIN object_links ol
           ON ol.left_type = "task"
          AND ol.left_id = tt.id
          AND ol.right_type = "venue"
          AND ol.team_id = tt.team_id
         WHERE tt.is_template = 1
           AND tt.trigger_day = :trigger_day
           AND tt.trigger_month = :trigger_month
           AND (tt.trigger_last_year IS NULL OR tt.trigger_last_year < :current_year)'
    );
    $stmt->execute([
        ':trigger_day' => $day,
        ':trigger_month' => $month,
        ':current_year' => $year
    ]);

    $templates = $stmt->fetchAll();
    if (!$templates) {
        return;
    }

    foreach ($templates as $template) {
        $taskTitle = trim((string) ($template['title'] ?? ''));
        $title = $taskTitle !== '' ? $taskTitle . ' – ' . $year : 'Follow up – ' . $year;
        $teamId = (int) ($template['team_id'] ?? 0);
        $venueId = (int) ($template['venue_id'] ?? 0);

        if ($teamId <= 0 || $venueId <= 0) {
            continue;
        }

        try {
            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare(
                'INSERT INTO team_tasks
                    (team_id, title, description, priority, due_date, is_template, trigger_day, trigger_month, trigger_last_year, created_by)
                 VALUES
                    (:team_id, :title, :description, :priority, NULL, 0, NULL, NULL, NULL, :created_by)'
            );
            $insertStmt->execute([
                ':team_id' => $teamId,
                ':title' => $title,
                ':description' => $template['description'] ?? null,
                ':priority' => $template['priority'] ?? 'B',
                ':created_by' => $template['created_by'] ?? null
            ]);
            $newTaskId = (int) $pdo->lastInsertId();

            createObjectLink($pdo, 'task', $newTaskId, 'venue', $venueId, $teamId, null);

            $updateStmt = $pdo->prepare(
                'UPDATE team_tasks
                 SET trigger_last_year = :current_year
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':current_year' => $year,
                ':id' => (int) $template['id']
            ]);

            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            logAction(null, 'venue_task_trigger_error', $error->getMessage());
        }
    }
}
