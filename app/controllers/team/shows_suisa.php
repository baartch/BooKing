<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/security_headers.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';
require_once __DIR__ . '/../../models/core/link_helpers.php';
require_once __DIR__ . '/../../models/team/shows.php';
require_once __DIR__ . '/../../models/team/show_songs.php';
require_once __DIR__ . '/../../models/venues/venues_repository.php';
require_once __DIR__ . '/../../models/team/suisa.php';

setApiSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/controllers/team/index.php';
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$teamId = (int) ($_GET['team_id'] ?? 0);
$showId = (int) ($_GET['show_id'] ?? 0);

$redirectParams = ['tab' => 'shows'];
if ($teamId > 0) {
    $redirectParams['team_id'] = $teamId;
}
if ($showId > 0) {
    $redirectParams['show_id'] = $showId;
}
if ($searchQuery !== '') {
    $redirectParams['q'] = $searchQuery;
}

$redirectUrl = $baseUrl . '?' . http_build_query(array_merge($redirectParams, ['notice' => 'show_error']));

if ($showId <= 0) {
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    if ($teamId <= 0 || !userHasTeamAccess($pdo, $userId, $teamId)) {
        logAction($userId, 'team_show_access_denied', sprintf('Denied team access for SUISA export. team_id=%d show_id=%d', $teamId, $showId));
        header('Location: ' . $redirectUrl);
        exit;
    }

    $show = fetchTeamShow($pdo, $teamId, $showId);
    if (!$show) {
        header('Location: ' . $redirectUrl);
        exit;
    }
    $show['team_name'] = fetchTeamName($pdo, $teamId);

    $venue = null;
    foreach (fetchLinkedObjects($pdo, 'show', $showId, $teamId, $userId) as $link) {
        if (($link['type'] ?? '') === 'venue') {
            $venue = fetchVenueById((int) ($link['id'] ?? 0));
            break;
        }
    }

    $songs = fetchShowSongs($pdo, $showId);

    $odt = buildSuisaOdt($show, $venue, $songs);

    logAction($userId, 'team_show_suisa_exported', sprintf('Exported SUISA form. team_id=%d show_id=%d songs=%d', $teamId, $showId, count($songs)));

    header('Content-Type: application/vnd.oasis.opendocument.text');
    header('Content-Disposition: attachment; filename="' . suisaDownloadFilename($show) . '"');
    header('Content-Length: ' . strlen($odt));
    header('Cache-Control: private, max-age=0, no-cache');
    echo $odt;
} catch (Throwable $error) {
    logAction($userId, 'team_show_suisa_export_error', sprintf('Failed SUISA export. team_id=%d show_id=%d error=%s', $teamId, $showId, $error->getMessage()));
    header('Location: ' . $redirectUrl);
    exit;
}
