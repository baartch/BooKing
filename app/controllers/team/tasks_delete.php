<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/team/tasks_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/controllers/team/index.php';
$searchQuery = trim((string) ($_POST['q'] ?? ''));
$teamId = (int) ($_POST['team_id'] ?? 0);
$taskId = (int) ($_POST['task_id'] ?? 0);

$redirectParams = ['tab' => 'tasks'];
if ($teamId > 0) {
    $redirectParams['team_id'] = $teamId;
}
if ($searchQuery !== '') {
    $redirectParams['q'] = $searchQuery;
}

if ($taskId <= 0) {
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'task_error'])));
    exit;
}

try {
    $pdo = getDatabaseConnection();

    if ($teamId <= 0 || !userHasTeamAccess($pdo, $userId, $teamId)) {
        logAction($userId, 'team_task_access_denied', sprintf('Denied team access for task delete. team_id=%d', $teamId));
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'task_error'])));
        exit;
    }

    $task = fetchTeamTask($pdo, $teamId, $taskId);
    if (!$task) {
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'task_error'])));
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM team_tasks WHERE id = :id AND team_id = :team_id');
    $stmt->execute([':id' => $taskId, ':team_id' => $teamId]);
    logAction($userId, 'team_task_deleted', sprintf('Deleted task %d', $taskId));

    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'task_deleted'])));
    exit;
} catch (Throwable $error) {
    logAction($userId, 'team_task_delete_error', $error->getMessage());
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'task_error'])));
    exit;
}
