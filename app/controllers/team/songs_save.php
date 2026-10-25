<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';
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

$redirectParams = ['tab' => 'songs'];
if ($teamId > 0) {
    $redirectParams['team_id'] = $teamId;
}
if ($searchQuery !== '') {
    $redirectParams['q'] = $searchQuery;
}

$action = (string) ($_POST['action'] ?? '');
$songId = (int) ($_POST['song_id'] ?? 0);

$formResult = buildSongFormInput($_POST);
$errors = $formResult['errors'];

if (!in_array($action, ['create_song', 'update_song'], true)) {
    $errors[] = 'Unknown action.';
}

if ($errors) {
    setFormFlash('team_song_form', $formResult['values']);
    if ($action === 'update_song' && $songId > 0) {
        $redirectParams['song_id'] = $songId;
        $redirectParams['mode'] = 'edit';
    } elseif ($action === 'create_song') {
        $redirectParams['mode'] = 'new';
    }
    $redirectParams['notice'] = 'song_error';
    $redirectParams['error'] = (string) ($errors[0] ?? 'Validation failed.');
    header('Location: ' . $baseUrl . '?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();

    if ($teamId <= 0 || !userHasTeamAccess($pdo, $userId, $teamId)) {
        logAction($userId, 'team_song_access_denied', sprintf('Denied team access for song save. team_id=%d', $teamId));
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'song_error'])));
        exit;
    }

    $values = $formResult['values'];

    if ($action === 'create_song') {
        $stmt = $pdo->prepare(
            'INSERT INTO team_songs
                (team_id, name, duration_seconds, composer, arranger, created_by)
             VALUES
                (:team_id, :name, :duration_seconds, :composer, :arranger, :created_by)'
        );
        $stmt->execute([
            ':team_id' => $teamId,
            ':name' => $values['name'],
            ':duration_seconds' => $values['duration_seconds'],
            ':composer' => $values['composer'],
            ':arranger' => $values['arranger'],
            ':created_by' => $userId > 0 ? $userId : null,
        ]);

        $newId = (int) $pdo->lastInsertId();
        logAction($userId, 'team_song_created', sprintf('Created song %d', $newId));

        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, [
            'notice' => 'song_created',
            'song_id' => $newId,
        ])));
        exit;
    }

    if ($songId <= 0) {
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'song_error'])));
        exit;
    }

    $existing = fetchTeamSong($pdo, $teamId, $songId);
    if (!$existing) {
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'song_error'])));
        exit;
    }

    $stmt = $pdo->prepare(
        'UPDATE team_songs
         SET name = :name,
             duration_seconds = :duration_seconds,
             composer = :composer,
             arranger = :arranger
         WHERE id = :id AND team_id = :team_id'
    );
    $stmt->execute([
        ':name' => $values['name'],
        ':duration_seconds' => $values['duration_seconds'],
        ':composer' => $values['composer'],
        ':arranger' => $values['arranger'],
        ':id' => (int) $existing['id'],
        ':team_id' => $teamId,
    ]);

    logAction($userId, 'team_song_updated', sprintf('Updated song %d', (int) $existing['id']));

    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, [
        'notice' => 'song_updated',
        'song_id' => (int) $existing['id'],
    ])));
    exit;
} catch (Throwable $error) {
    logAction($userId, 'team_song_save_error', $error->getMessage());
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'song_error'])));
    exit;
}
