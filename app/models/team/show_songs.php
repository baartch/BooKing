<?php
/**
 * Show Songs (Setlist) Model
 *
 * Ordered setlist handling for team shows: normalization of the posted
 * `show_songs[]` array plus the DB helpers used by the show form, detail
 * view, and save controller. The junction table `team_show_songs` enforces
 * song-once-per-show, slot uniqueness, and same-team membership at the DB
 * level; these helpers stay inside those guarantees.
 */

/**
 * Normalize the posted `show_songs[]` input.
 *
 * Accepts an ordered list of song ID strings (the trailing empty select
 * posts ''). Empty and non-numeric entries are silently filtered; a song
 * appearing twice makes the whole input invalid (the UI prevents this,
 * this is the backstop).
 *
 * @return array{song_ids: int[], errors: string[]}
 */
function normalizeShowSongInput(array $post): array
{
    $raw = $post['show_songs'] ?? null;
    if (!is_array($raw)) {
        return ['song_ids' => [], 'errors' => []];
    }

    $songIds = [];
    foreach ($raw as $entry) {
        $trimmed = trim((string) $entry);
        if ($trimmed === '' || !preg_match('/^\d+$/', $trimmed)) {
            continue;
        }
        $songId = (int) $trimmed;
        if ($songId > 0) {
            $songIds[] = $songId;
        }
    }

    if (count($songIds) !== count(array_unique($songIds))) {
        return [
            'song_ids' => [],
            'errors' => ['Each song may only appear once on a show.'],
        ];
    }

    return ['song_ids' => $songIds, 'errors' => []];
}

/**
 * Fetch the ordered setlist of a show, joined with song data.
 *
 * @return array<int, array{id: int, name: string, composer: string, arranger: string, duration_seconds: ?int, position: int}>
 */
function fetchShowSongs(PDO $pdo, int $showId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.composer, s.arranger, s.duration_seconds, x.position
         FROM team_show_songs x
         JOIN team_songs s ON s.id = x.song_id AND s.team_id = x.team_id
         WHERE x.show_id = :show_id
         ORDER BY x.position ASC'
    );
    $stmt->execute([':show_id' => $showId]);

    return array_map(
        static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'composer' => (string) $row['composer'],
                'arranger' => (string) $row['arranger'],
                'duration_seconds' => $row['duration_seconds'] !== null ? (int) $row['duration_seconds'] : null,
                'position' => (int) $row['position'],
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

/**
 * Fetch the songs of a team for dropdown options, ordered by name.
 *
 * @return array<int, array{id: int, name: string, composer: string}>
 */
function fetchTeamSongOptions(PDO $pdo, int $teamId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, composer
         FROM team_songs
         WHERE team_id = :team_id
         ORDER BY name ASC'
    );
    $stmt->execute([':team_id' => $teamId]);

    return array_map(
        static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'composer' => (string) $row['composer'],
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

/**
 * Replace the setlist of a show with the given ordered song IDs.
 *
 * Runs inside the caller's transaction (no begin/commit here). An empty
 * list clears the setlist. Position starts at 1; the posted order is the
 * setlist order.
 */
function replaceShowSongs(PDO $pdo, int $showId, int $teamId, array $songIds): void
{
    $stmt = $pdo->prepare('DELETE FROM team_show_songs WHERE show_id = :show_id');
    $stmt->execute([':show_id' => $showId]);

    $songIds = array_values($songIds);
    if ($songIds === []) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO team_show_songs (show_id, team_id, song_id, position)
         VALUES (:show_id, :team_id, :song_id, :position)'
    );
    foreach ($songIds as $index => $songId) {
        $stmt->execute([
            ':show_id' => $showId,
            ':team_id' => $teamId,
            ':song_id' => (int) $songId,
            ':position' => $index + 1,
        ]);
    }
}

/**
 * Count how many show setlists reference each of the given songs.
 *
 * @param array<int, mixed> $songIds
 * @return array<int, int> map of song ID => number of distinct shows
 */
function fetchSetlistCountsBySongIds(PDO $pdo, array $songIds): array
{
    $counts = [];
    $songIds = array_values(array_map('intval', $songIds));
    $songIds = array_values(array_filter($songIds, static fn (int $id): bool => $id > 0));
    if ($songIds === []) {
        return $counts;
    }

    $placeholders = implode(',', array_fill(0, count($songIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT song_id, COUNT(DISTINCT show_id) AS setlist_count
         FROM team_show_songs
         WHERE song_id IN (' . $placeholders . ')
         GROUP BY song_id'
    );
    $stmt->execute($songIds);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(int) $row['song_id']] = (int) $row['setlist_count'];
    }

    return $counts;
}

/**
 * Fetch the shows whose setlist includes the given song (same team only),
 * newest show date first.
 *
 * @return array<int, array{id: int, name: string, show_date: string}>
 */
function fetchShowsForSong(PDO $pdo, int $teamId, int $songId): array
{
    if ($teamId <= 0 || $songId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT sh.id, sh.name, sh.show_date
         FROM team_show_songs x
         JOIN team_shows sh ON sh.id = x.show_id AND sh.team_id = x.team_id
         WHERE x.song_id = :song_id AND x.team_id = :team_id
         ORDER BY sh.show_date DESC, sh.id DESC'
    );
    $stmt->execute([
        ':song_id' => $songId,
        ':team_id' => $teamId,
    ]);

    return array_map(
        static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'show_date' => (string) $row['show_date'],
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}
