<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/core/object_links.php';
require_once __DIR__ . '/../../models/venues/venue_task_triggers.php';
require_once __DIR__ . '/../../models/venues/venues_repository.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/controllers/venues/index.php';
$venueId = (int) ($_POST['venue_id'] ?? 0);
$teamId = (int) ($_POST['team_id'] ?? 0);
$taskId = (int) ($_POST['task_id'] ?? 0);
$page = (int) ($_POST['page'] ?? 1);
$pageSize = (int) ($_POST['per_page'] ?? 25);
$filter = trim((string) ($_POST['filter'] ?? ''));

$showTriggers = !empty($_POST['show_triggers']);

$redirectParams = [
    'venue_id' => $venueId,
    'page' => $page,
    'per_page' => $pageSize,
    'team_id' => $teamId,
    'show_triggers' => $showTriggers ? 1 : null
];
if ($filter !== '') {
    $redirectParams['filter'] = $filter;
}

if ($venueId <= 0 || $teamId <= 0 || $taskId <= 0) {
    $redirectParams['notice'] = 'trigger_error';
    header('Location: ' . $baseUrl . '?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();

    if (!userHasTeamAccess($pdo, $userId, $teamId)) {
        logAction($userId, 'venue_trigger_access_denied', sprintf('Denied team access for trigger delete. team_id=%d venue_id=%d', $teamId, $venueId));
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'trigger_error'])));
        exit;
    }

    $venue = fetchVenueById($venueId);
    if (!$venue) {
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'trigger_error'])));
        exit;
    }

    $trigger = fetchVenueTaskTrigger($pdo, $venueId, $teamId, $taskId);
    if (!$trigger) {
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'trigger_error'])));
        exit;
    }

    $pdo->beginTransaction();

    clearObjectLinks($pdo, 'task', $taskId, $teamId, null);
    $stmt = $pdo->prepare('DELETE FROM team_tasks WHERE id = :id AND team_id = :team_id AND is_template = 1');
    $stmt->execute([':id' => $taskId, ':team_id' => $teamId]);

    $pdo->commit();

    logAction($userId, 'venue_trigger_deleted', sprintf('Deleted trigger %d for venue %d', $taskId, $venueId));
    $redirectParams['notice'] = 'trigger_deleted';
    header('Location: ' . $baseUrl . '?' . http_build_query($redirectParams));
    exit;
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logAction($userId, 'venue_trigger_delete_error', $error->getMessage());
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'trigger_error'])));
    exit;
}
