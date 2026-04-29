<?php
require_once __DIR__ . '/../core/database.php';

function fetchTeamShows(PDO $pdo, int $teamId, ?string $search = null): array
{
    if ($teamId <= 0) {
        return [];
    }

    $params = [':team_id' => $teamId];
    $where = 'WHERE s.team_id = :team_id';

    if ($search !== null) {
        $search = trim($search);
    }

    if ($search !== null && $search !== '') {
        $where .= ' AND (s.name LIKE :like_name OR s.notes LIKE :like_notes OR v.name LIKE :like_venue)';
        $like = '%' . $search . '%';
        $params[':like_name'] = $like;
        $params[':like_notes'] = $like;
        $params[':like_venue'] = $like;
    }

    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.show_date, s.show_time, s.artist_fee, s.notes, s.venue_id, v.name AS venue_name, s.created_at, s.updated_at
         FROM team_shows s
         JOIN venues v ON v.id = s.venue_id
         ' . $where . '
         ORDER BY s.show_date DESC, s.id DESC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function fetchTeamShow(PDO $pdo, int $teamId, int $showId): ?array
{
    if ($teamId <= 0 || $showId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT s.*, v.name AS venue_name
         FROM team_shows s
         JOIN venues v ON v.id = s.venue_id
         WHERE s.id = :id AND s.team_id = :team_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $showId,
        ':team_id' => $teamId,
    ]);

    $show = $stmt->fetch();
    return $show ?: null;
}

function fetchShowVenueOptions(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM venues ORDER BY name ASC');
    return $stmt->fetchAll();
}

function venueExists(PDO $pdo, int $venueId): bool
{
    if ($venueId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM venues WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $venueId]);
    return (bool) $stmt->fetchColumn();
}
