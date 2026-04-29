<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/team/shows.php';
require_once __DIR__ . '/../../models/core/object_links.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/controllers/team/index.php';
$searchQuery = trim((string) ($_POST['q'] ?? ''));
$teamId = (int) ($_POST['team_id'] ?? 0);
$showId = (int) ($_POST['show_id'] ?? 0);

$redirectParams = ['tab' => 'shows'];
if ($teamId > 0) {
    $redirectParams['team_id'] = $teamId;
}
if ($searchQuery !== '') {
    $redirectParams['q'] = $searchQuery;
}

if ($showId <= 0) {
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'show_error'])));
    exit;
}

try {
    $pdo = getDatabaseConnection();

    if ($teamId <= 0 || !userHasTeamAccess($pdo, $userId, $teamId)) {
        logAction($userId, 'team_show_access_denied', sprintf('Denied team access for show delete. team_id=%d', $teamId));
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'show_error'])));
        exit;
    }

    $show = fetchTeamShow($pdo, $teamId, $showId);
    if (!$show) {
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'show_error'])));
        exit;
    }

    $pdo->beginTransaction();
    clearObjectLinks($pdo, 'show', $showId, $teamId, null);

    $stmt = $pdo->prepare('DELETE FROM team_shows WHERE id = :id AND team_id = :team_id');
    $stmt->execute([':id' => $showId, ':team_id' => $teamId]);

    $pdo->commit();
    logAction($userId, 'team_show_deleted', sprintf('Deleted show %d', $showId));

    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'show_deleted'])));
    exit;
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logAction($userId, 'team_show_delete_error', $error->getMessage());
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'show_error'])));
    exit;
}
