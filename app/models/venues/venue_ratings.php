<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../communication/team_helpers.php';

const VENUE_RATING_VALUES = ['A', 'B', 'C'];

function fetchVenueRatingForTeam(PDO $pdo, int $venueId, int $teamId): ?array
{
    if ($venueId <= 0 || $teamId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT rating, rated_by, rated_at, updated_at
         FROM venue_ratings
         WHERE venue_id = :venue_id AND team_id = :team_id
         LIMIT 1'
    );
    $stmt->execute([
        ':venue_id' => $venueId,
        ':team_id' => $teamId
    ]);
    $rating = $stmt->fetch();

    return $rating ?: null;
}

function fetchVenueRatingsForList(PDO $pdo, array $venueIds, int $teamId): array
{
    $venueIds = array_values(array_filter(array_map('intval', $venueIds), static fn($id) => $id > 0));
    if (!$venueIds || $teamId <= 0) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($venueIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT venue_id, rating
         FROM venue_ratings
         WHERE team_id = ? AND venue_id IN (' . $placeholders . ')'
    );
    $stmt->execute(array_merge([$teamId], $venueIds));
    $rows = $stmt->fetchAll();

    $ratings = [];
    foreach ($rows as $row) {
        $ratings[(int) $row['venue_id']] = (string) $row['rating'];
    }

    return $ratings;
}

function upsertVenueRating(PDO $pdo, int $venueId, int $teamId, string $rating, int $userId): bool
{
    if ($venueId <= 0 || $teamId <= 0 || $userId <= 0) {
        return false;
    }

    $rating = strtoupper(trim($rating));
    if (!in_array($rating, VENUE_RATING_VALUES, true)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO venue_ratings
         (venue_id, team_id, rating, rated_by, rated_at, updated_at)
         VALUES
         (:venue_id, :team_id, :rating, :rated_by, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
             rating = VALUES(rating),
             rated_by = VALUES(rated_by),
             rated_at = VALUES(rated_at),
             updated_at = VALUES(updated_at)'
    );

    return $stmt->execute([
        ':venue_id' => $venueId,
        ':team_id' => $teamId,
        ':rating' => $rating,
        ':rated_by' => $userId
    ]);
}
