<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/core/object_links.php';
require_once __DIR__ . '/../../models/venues/venues_repository.php';
require_once __DIR__ . '/../../models/venues/venue_task_triggers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/controllers/venues/index.php';
$venueId = (int) ($_POST['venue_id'] ?? 0);
$teamId = (int) ($_POST['team_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$taskId = (int) ($_POST['task_id'] ?? 0);
$page = (int) ($_POST['page'] ?? 1);
$pageSize = (int) ($_POST['per_page'] ?? 25);
$filter = trim((string) ($_POST['filter'] ?? ''));

$redirectParams = [
    'venue_id' => $venueId,
    'page' => $page,
    'per_page' => $pageSize,
    'team_id' => $teamId
];
if ($filter !== '') {
    $redirectParams['filter'] = $filter;
}

$payload = [
    'title' => trim((string) ($_POST['title'] ?? '')),
    'description' => trim((string) ($_POST['description'] ?? '')),
    'priority' => trim((string) ($_POST['priority'] ?? '')),
    'trigger_day' => (int) ($_POST['trigger_day'] ?? 0),
    'trigger_month' => (int) ($_POST['trigger_month'] ?? 0)
];

$errors = [];

if ($payload['title'] === '') {
    $errors[] = 'Title is required.';
}

if ($payload['priority'] === '' || !in_array($payload['priority'], ['A', 'B', 'C'], true)) {
    $errors[] = 'Priority must be A, B, or C.';
}

if ($payload['trigger_day'] < 1 || $payload['trigger_day'] > 31) {
    $errors[] = 'Day must be between 1 and 31.';
}

if ($payload['trigger_month'] < 1 || $payload['trigger_month'] > 12) {
    $errors[] = 'Month must be between 1 and 12.';
}

if (!in_array($action, ['create_trigger', 'update_trigger'], true)) {
    $errors[] = 'Unknown action.';
}

if ($errors) {
    $redirectParams['notice'] = 'trigger_error';
    header('Location: ' . $baseUrl . '?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();

    if ($venueId <= 0 || !$teamId || !userHasTeamAccess($pdo, $userId, $teamId)) {
        logAction($userId, 'venue_trigger_access_denied', sprintf('Denied team access for trigger save. team_id=%d venue_id=%d', $teamId, $venueId));
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'trigger_error'])));
        exit;
    }

    $venue = fetchVenueById($venueId);
    if (!$venue) {
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'trigger_error'])));
        exit;
    }

    if ($action === 'create_trigger') {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO team_tasks
                (team_id, title, description, priority, due_date, is_template, trigger_day, trigger_month, trigger_last_year, created_by)
             VALUES
                (:team_id, :title, :description, :priority, NULL, 1, :trigger_day, :trigger_month, NULL, :created_by)'
        );
        $stmt->execute([
            ':team_id' => $teamId,
            ':title' => $payload['title'],
            ':description' => normalizeOptionalString($payload['description']),
            ':priority' => $payload['priority'],
            ':trigger_day' => $payload['trigger_day'],
            ':trigger_month' => $payload['trigger_month'],
            ':created_by' => $userId > 0 ? $userId : null
        ]);
        $newId = (int) $pdo->lastInsertId();

        createObjectLink($pdo, 'task', $newId, 'venue', $venueId, $teamId, null);

        $pdo->commit();

        logAction($userId, 'venue_trigger_created', sprintf('Created trigger %d for venue %d', $newId, $venueId));
        $redirectParams['notice'] = 'trigger_created';
        header('Location: ' . $baseUrl . '?' . http_build_query($redirectParams));
        exit;
    }

    if ($action === 'update_trigger') {
        if ($taskId <= 0) {
            header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'trigger_error'])));
            exit;
        }

        $trigger = fetchVenueTaskTrigger($pdo, $venueId, $teamId, $taskId);
        if (!$trigger) {
            header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'trigger_error'])));
            exit;
        }

        $stmt = $pdo->prepare(
            'UPDATE team_tasks
             SET title = :title,
                 description = :description,
                 priority = :priority,
                 trigger_day = :trigger_day,
                 trigger_month = :trigger_month
             WHERE id = :id AND team_id = :team_id AND is_template = 1'
        );
        $stmt->execute([
            ':title' => $payload['title'],
            ':description' => normalizeOptionalString($payload['description']),
            ':priority' => $payload['priority'],
            ':trigger_day' => $payload['trigger_day'],
            ':trigger_month' => $payload['trigger_month'],
            ':id' => $taskId,
            ':team_id' => $teamId
        ]);

        logAction($userId, 'venue_trigger_updated', sprintf('Updated trigger %d for venue %d', $taskId, $venueId));
        $redirectParams['notice'] = 'trigger_updated';
        header('Location: ' . $baseUrl . '?' . http_build_query($redirectParams));
        exit;
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logAction($userId, 'venue_trigger_save_error', $error->getMessage());
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'trigger_error'])));
    exit;
}
