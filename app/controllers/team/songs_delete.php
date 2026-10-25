<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/team/songs.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/controllers/team/index.php';
$searchQuery = trim((string) ($_POST['q'] ?? ''));
$teamId = (int) ($_POST['team_id'] ?? 0);
$songId = (int) ($_POST['song_id'] ?? 0);

$redirectParams = ['tab' => 'songs'];
if ($teamId > 0) {
    $redirectParams['team_id'] = $teamId;
}
if ($searchQuery !== '') {
    $redirectParams['q'] = $searchQuery;
}

if ($songId <= 0) {
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'song_error'])));
    exit;
}

try {
    $pdo = getDatabaseConnection();

    if ($teamId <= 0 || !userHasTeamAccess($pdo, $userId, $teamId)) {
        logAction($userId, 'team_song_access_denied', sprintf('Denied team access for song delete. team_id=%d', $teamId));
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'song_error'])));
        exit;
    }

    $song = fetchTeamSong($pdo, $teamId, $songId);
    if (!$song) {
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'song_error'])));
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM team_songs WHERE id = :id AND team_id = :team_id');
    $stmt->execute([':id' => $songId, ':team_id' => $teamId]);

    logAction($userId, 'team_song_deleted', sprintf('Deleted song %d', $songId));

    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'song_deleted'])));
    exit;
} catch (Throwable $error) {
    logAction($userId, 'team_song_delete_error', $error->getMessage());
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'song_error'])));
    exit;
}
