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
        $where .= ' AND (s.name LIKE :like_name OR s.notes LIKE :like_notes OR s.venue_text LIKE :like_venue)';
        $like = '%' . $search . '%';
        $params[':like_name'] = $like;
        $params[':like_notes'] = $like;
        $params[':like_venue'] = $like;
    }

    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.show_date, s.show_time, s.venue_text, s.artist_fee, s.notes, s.created_at, s.updated_at
         FROM team_shows s
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
        'SELECT *
         FROM team_shows
         WHERE id = :id AND team_id = :team_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $showId,
        ':team_id' => $teamId,
    ]);

    $show = $stmt->fetch();
    return $show ?: null;
}

function resolveAutoVenueText(PDO $pdo, ?string $venueText, array $normalizedLinks): ?string
{
    $current = trim((string) ($venueText ?? ''));
    if ($current !== '') {
        return $current;
    }

    foreach ($normalizedLinks as $link) {
        $type = (string) ($link['type'] ?? '');
        $id = (int) ($link['id'] ?? 0);
        if ($type !== 'venue' || $id <= 0) {
            continue;
        }

        $stmt = $pdo->prepare('SELECT name FROM venues WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $name = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($name !== '') {
            return $name;
        }
    }

    return null;
}
