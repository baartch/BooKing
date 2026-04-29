<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/team/shows.php';
require_once __DIR__ . '/../../models/core/object_links.php';
require_once __DIR__ . '/../../models/core/link_scope.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/controllers/team/index.php';
$searchQuery = trim((string) ($_POST['q'] ?? ''));
$teamId = (int) ($_POST['team_id'] ?? 0);

$redirectParams = ['tab' => 'shows'];
if ($teamId > 0) {
    $redirectParams['team_id'] = $teamId;
}
if ($searchQuery !== '') {
    $redirectParams['q'] = $searchQuery;
}

$action = (string) ($_POST['action'] ?? '');
$showId = (int) ($_POST['show_id'] ?? 0);

$payload = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'show_date' => trim((string) ($_POST['show_date'] ?? '')),
    'show_time' => trim((string) ($_POST['show_time'] ?? '')),
    'venue_text' => trim((string) ($_POST['venue_text'] ?? '')),
    'artist_fee' => trim((string) ($_POST['artist_fee'] ?? '')),
    'notes' => trim((string) ($_POST['notes'] ?? '')),
    'links' => is_array($_POST['link_items'] ?? null) ? $_POST['link_items'] : []
];

$errors = [];

if ($payload['show_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['show_date'])) {
    $errors[] = 'Date is required and must be valid.';
}

if ($payload['show_time'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $payload['show_time'])) {
    $errors[] = 'Time must use HH:MM format.';
}


if ($payload['artist_fee'] !== '' && (!is_numeric($payload['artist_fee']) || (float) $payload['artist_fee'] < 0)) {
    $errors[] = 'Artist fee must be a non-negative amount.';
}

if (!in_array($action, ['create_show', 'update_show'], true)) {
    $errors[] = 'Unknown action.';
}

if ($errors) {
    if ($action === 'update_show' && $showId > 0) {
        $redirectParams['show_id'] = $showId;
        $redirectParams['mode'] = 'edit';
    } elseif ($action === 'create_show') {
        $redirectParams['mode'] = 'new';
    }
    $redirectParams['notice'] = 'show_error';
    header('Location: ' . $baseUrl . '?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();

    if ($teamId <= 0 || !userHasTeamAccess($pdo, $userId, $teamId)) {
        logAction($userId, 'team_show_access_denied', sprintf('Denied team access for show save. team_id=%d', $teamId));
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'show_error'])));
        exit;
    }

    $showTime = $payload['show_time'] !== '' ? $payload['show_time'] : null;
    $artistFee = $payload['artist_fee'] !== '' ? (float) $payload['artist_fee'] : null;

    $rawLinkItems = array_filter(array_map('trim', $payload['links']));
    $normalizedLinkInput = [];
    foreach ($rawLinkItems as $linkItem) {
        [$type, $id] = array_pad(explode(':', (string) $linkItem, 2), 2, '');
        $normalizedLinkInput[] = [
            'type' => $type,
            'id' => (int) $id,
        ];
    }
    $normalizedLinks = normalizeLinkItems($normalizedLinkInput);
    $resolvedVenueText = resolveAutoVenueText($pdo, $payload['venue_text'], $normalizedLinks);

    if ($action === 'create_show') {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO team_shows
                (team_id, name, show_date, show_time, venue_text, artist_fee, notes, created_by)
             VALUES
                (:team_id, :name, :show_date, :show_time, :venue_text, :artist_fee, :notes, :created_by)'
        );
        $stmt->execute([
            ':team_id' => $teamId,
            ':name' => normalizeOptionalString($payload['name']),
            ':show_date' => $payload['show_date'],
            ':show_time' => $showTime,
            ':venue_text' => normalizeOptionalString((string) ($resolvedVenueText ?? '')),
            ':artist_fee' => $artistFee,
            ':notes' => normalizeOptionalString($payload['notes']),
            ':created_by' => $userId > 0 ? $userId : null,
        ]);

        $newId = (int) $pdo->lastInsertId();
        clearObjectLinks($pdo, 'show', $newId, $teamId, null);
        foreach ($normalizedLinks as $link) {
            createObjectLink($pdo, 'show', $newId, (string) ($link['type'] ?? ''), (int) ($link['id'] ?? 0), $teamId, null);
        }

        $pdo->commit();
        logAction($userId, 'team_show_created', sprintf('Created show %d', $newId));

        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, [
            'notice' => 'show_created',
            'show_id' => $newId,
        ])));
        exit;
    }

    if ($showId <= 0) {
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'show_error'])));
        exit;
    }

    $existing = fetchTeamShow($pdo, $teamId, $showId);
    if (!$existing) {
        header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'show_error'])));
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'UPDATE team_shows
         SET name = :name,
             show_date = :show_date,
             show_time = :show_time,
             venue_text = :venue_text,
             artist_fee = :artist_fee,
             notes = :notes
         WHERE id = :id AND team_id = :team_id'
    );
    $stmt->execute([
        ':name' => normalizeOptionalString($payload['name']),
        ':show_date' => $payload['show_date'],
        ':show_time' => $showTime,
        ':venue_text' => normalizeOptionalString((string) ($resolvedVenueText ?? '')),
        ':artist_fee' => $artistFee,
        ':notes' => normalizeOptionalString($payload['notes']),
        ':id' => (int) $existing['id'],
        ':team_id' => $teamId,
    ]);

    clearObjectLinks($pdo, 'show', (int) $existing['id'], $teamId, null);
    foreach ($normalizedLinks as $link) {
        createObjectLink($pdo, 'show', (int) $existing['id'], (string) ($link['type'] ?? ''), (int) ($link['id'] ?? 0), $teamId, null);
    }

    $pdo->commit();
    logAction($userId, 'team_show_updated', sprintf('Updated show %d', (int) $existing['id']));

    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, [
        'notice' => 'show_updated',
        'show_id' => (int) $existing['id'],
    ])));
    exit;
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logAction($userId, 'team_show_save_error', $error->getMessage());
    header('Location: ' . $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'show_error'])));
    exit;
}
