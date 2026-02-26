<?php
require_once __DIR__ . '/../core/database.php';

function fetchTeamTasks(PDO $pdo, int $teamId, ?string $search = null): array
{
    if ($teamId <= 0) {
        return [];
    }

    $params = [':team_id' => $teamId];
    $where = 'WHERE team_id = :team_id';

    if ($search !== null) {
        $search = trim($search);
    }

    if ($search !== null && $search !== '') {
        $where .= ' AND (title LIKE :like_title OR description LIKE :like_description)';
        $like = '%' . $search . '%';
        $params[':like_title'] = $like;
        $params[':like_description'] = $like;
    }

    $where .= ' AND is_template = 0';

    $stmt = $pdo->prepare(
        'SELECT id, title, priority, due_date, updated_at
         FROM team_tasks
         ' . $where . '
         ORDER BY FIELD(priority, "A", "B", "C"), due_date IS NULL, due_date, id'
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetchTeamTask(PDO $pdo, int $teamId, int $taskId): ?array
{
    if ($teamId <= 0 || $taskId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM team_tasks WHERE id = :id AND team_id = :team_id LIMIT 1');
    $stmt->execute([':id' => $taskId, ':team_id' => $teamId]);
    $task = $stmt->fetch();

    return $task ?: null;
}
