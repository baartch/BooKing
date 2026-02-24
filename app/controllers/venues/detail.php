<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/venues/venues_repository.php';
require_once __DIR__ . '/../../models/venues/venue_ratings.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';

$venueId = (int) ($_GET['venue_id'] ?? 0);
$teamId = (int) ($_GET['team_id'] ?? 0);

if ($venueId <= 0) {
    http_response_code(400);
    echo 'Missing venue.';
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $userId = (int) ($currentUser['user_id'] ?? 0);
    $activeTeamId = resolveActiveTeamId($pdo, $userId, $teamId);
    if ($activeTeamId <= 0) {
        $activeTeamId = 0;
    }

    $activeVenue = fetchVenueById($venueId);
    if (!$activeVenue) {
        http_response_code(404);
        echo 'Venue not found.';
        exit;
    }

    $page = 1;
    $pageSize = 25;
    $filter = '';

    $detailActionsHtml = null;
    $detailRows = [];
    $detailTitle = null;
    $detailSubtitle = null;
    $detailEmptyMessage = 'Select a venue to see the details.';

    $selectedVenueRating = null;
    if ($activeTeamId > 0) {
        $selectedVenueRating = fetchVenueRatingForTeam($pdo, $venueId, $activeTeamId);
    }

    ob_start();
    require __DIR__ . '/../../views/venues/venues/detail.php';
    $html = (string) ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode(['html' => $html], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(500);
    logAction($currentUser['user_id'] ?? null, 'venue_detail_error', $error->getMessage());
    echo 'Failed to load venue.';
}
