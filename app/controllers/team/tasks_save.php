<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/team/tasks_helpers.php';
require_once __DIR__ . '/../../models/core/object_links.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/controllers/team/index.php';
$searchQuery = trim((string) ($_POST['q'] ?? ''));
$teamId = (int) ($_POST['team_id'] ?? 0);

$redirectParams = ['tab' => 'tasks'];
if ($teamId > 0) {
    $redirectParams['team_id'] = $teamId;
}
if ($searchQuery !== '') {
    $redirectParams['q'] = $searchQuery;
}

$action = (string) ($_POST['action'] ?? '');
$taskId = (int) ($_POST['task_id'] ?? 0);

$payload = [
    'title' => trim((string) ($_POST['title'] ?? '')),
    'description' => trim((string) ($_POST['description'] ?? '')),
    'priority' => trim((string) ($_POST['priority'] ?? '')),
    'due_date' => trim((string) ($_POST['due_date'] ?? '')),
    'links' => is_array($_POST['link_items'] ?? null) ? $_POST['link_items'] : []
];

$errors = [];

if ($payload['title'] === '') {
    $errors[] = 'Title is required.';
}

if ($payload['priority'] === '' || !in_array($payload['priority'], ['A', 'B', 'C'], true)) {
    $errors[] = 'Priority must be A, B, or C.';
}

if ($payload['due_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['due_date'])) {
    $errors[] = 'Due date must be a valid date.';
}

if (!in_array($action, ['create_task', 'update_task'], true)) {
    $errors[] = 'Unknown action.';
}

if ($errors) {
    if ($action === 'update_task' && $taskId > 0) {
        $redirectParams['task_id'] = $taskId;
        $redirectParams['mode'] = 'edit';
    } elseif ($action === 'create_task') {
        $redirectParams['mode'] = 'new';
    }
    $redirectParams['notice'] = 'task_error';
    header('Location: ' . $baseUrl . '?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();

    if ($teamId <= 0 || !userHasTeamAccess($pdo, $userId, $teamId)) {
        logAction($userId, 'team_task_access_denied', sprintf('Denied team access for task save. team_id=%d', $teamId));
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'task_error'])));
        exit;
    }

    $dueDate = $payload['due_date'] !== '' ? $payload['due_date'] : null;
    $linkItems = array_filter(array_map('trim', $payload['links']));

    if ($action === 'create_task') {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO team_tasks
                (team_id, title, description, priority, due_date, is_template, trigger_day, trigger_month, trigger_last_year, created_by)
             VALUES
                (:team_id, :title, :description, :priority, :due_date, 0, NULL, NULL, NULL, :created_by)'
        );
        $stmt->execute([
            ':team_id' => $teamId,
            ':title' => $payload['title'],
            ':description' => normalizeOptionalString($payload['description']),
            ':priority' => $payload['priority'],
            ':due_date' => $dueDate,
            ':created_by' => $userId > 0 ? $userId : null
        ]);

        $newId = (int) $pdo->lastInsertId();
        if ($linkItems) {
            clearObjectLinks($pdo, 'task', $newId, $teamId, null);
            foreach ($linkItems as $linkItem) {
                [$type, $id] = array_pad(explode(':', (string) $linkItem, 2), 2, '');
                if ($type === '' || (int) $id <= 0) {
                    continue;
                }
                createObjectLink($pdo, 'task', $newId, (string) $type, (int) $id, $teamId, null);
            }
        }

        $pdo->commit();

        logAction($userId, 'team_task_created', sprintf('Created task %d', $newId));

        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, [
            'notice' => 'task_created'
        ])));
        exit;
    }

    if ($action === 'update_task') {
        if ($taskId <= 0) {
            header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'task_error'])));
            exit;
        }

        $existing = fetchTeamTask($pdo, $teamId, $taskId);
        if (!$existing) {
            header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'task_error'])));
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE team_tasks
             SET title = :title,
                 description = :description,
                 priority = :priority,
                 due_date = :due_date
             WHERE id = :id AND team_id = :team_id AND is_template = 0'
        );
        $stmt->execute([
            ':title' => $payload['title'],
            ':description' => normalizeOptionalString($payload['description']),
            ':priority' => $payload['priority'],
            ':due_date' => $dueDate,
            ':id' => (int) $existing['id'],
            ':team_id' => $teamId
        ]);

        clearObjectLinks($pdo, 'task', (int) $existing['id'], $teamId, null);
        if ($linkItems) {
            foreach ($linkItems as $linkItem) {
                [$type, $id] = array_pad(explode(':', (string) $linkItem, 2), 2, '');
                if ($type === '' || (int) $id <= 0) {
                    continue;
                }
                createObjectLink($pdo, 'task', (int) $existing['id'], (string) $type, (int) $id, $teamId, null);
            }
        }

        $pdo->commit();

        logAction($userId, 'team_task_updated', sprintf('Updated task %d', (int) $existing['id']));

        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, [
            'notice' => 'task_updated',
            'task_id' => (int) $existing['id']
        ])));
        exit;
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logAction($userId, 'team_task_save_error', $error->getMessage());
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'task_error'])));
    exit;
}
