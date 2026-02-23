<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';
require_once __DIR__ . '/../../models/venues/venue_ratings.php';
require_once __DIR__ . '/../../models/communication/team_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$venueId = (int) ($_POST['venue_id'] ?? 0);
$teamId = (int) ($_POST['team_id'] ?? 0);
$rating = trim((string) ($_POST['rating'] ?? ''));

$redirectParams = [
    'venue_id' => $venueId,
    'page' => (int) ($_POST['page'] ?? 1),
    'per_page' => (int) ($_POST['per_page'] ?? 25),
    'team_id' => $teamId
];
$filter = trim((string) ($_POST['filter'] ?? ''));
if ($filter !== '') {
    $redirectParams['filter'] = $filter;
}

try {
    $pdo = getDatabaseConnection();
    $activeTeamId = resolveActiveTeamId($pdo, $userId, $teamId);
    if ($activeTeamId <= 0 || $activeTeamId !== $teamId) {
        logAction($userId, 'venue_rating_team_denied', sprintf('Denied rating team_id=%d venue_id=%d', $teamId, $venueId));
        header('Location: ' . BASE_PATH . '/app/controllers/venues/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $success = upsertVenueRating($pdo, $venueId, $teamId, $rating, $userId);
    if (!$success) {
        logAction($userId, 'venue_rating_error', sprintf('Failed rating venue_id=%d team_id=%d', $venueId, $teamId));
    } else {
        logAction($userId, 'venue_rating_saved', sprintf('Rated venue_id=%d team_id=%d rating=%s', $venueId, $teamId, $rating));
    }
} catch (Throwable $error) {
    logAction($userId, 'venue_rating_exception', $error->getMessage());
}

header('Location: ' . BASE_PATH . '/app/controllers/venues/index.php?' . http_build_query($redirectParams));
exit;
