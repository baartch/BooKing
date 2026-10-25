<?php
require_once __DIR__ . '/../core/database.php';

function fetchTeamSongs(PDO $pdo, int $teamId, ?string $search = null): array
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
        $where .= ' AND (s.name LIKE :like_name OR s.composer LIKE :like_composer OR s.arranger LIKE :like_arranger)';
        $like = '%' . $search . '%';
        $params[':like_name'] = $like;
        $params[':like_composer'] = $like;
        $params[':like_arranger'] = $like;
    }

    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.duration_seconds, s.composer, s.arranger, s.created_at, s.updated_at
         FROM team_songs s
         ' . $where . '
         ORDER BY s.name ASC, s.id ASC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function fetchTeamSong(PDO $pdo, int $teamId, int $songId): ?array
{
    if ($teamId <= 0 || $songId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM team_songs
         WHERE id = :id AND team_id = :team_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $songId,
        ':team_id' => $teamId,
    ]);

    $song = $stmt->fetch();
    return $song ?: null;
}

/**
 * Validates and normalizes song form input.
 *
 * @return array{errors: string[], values: array{name: string, duration: string, composer: string, arranger: string, duration_seconds: int|null}}
 */
function buildSongFormInput(array $post): array
{
    $values = [
        'name' => trim((string) ($post['name'] ?? '')),
        'duration' => trim((string) ($post['duration'] ?? '')),
        'composer' => trim((string) ($post['composer'] ?? '')),
        'arranger' => trim((string) ($post['arranger'] ?? '')),
        'duration_seconds' => null,
    ];

    $errors = [];

    $requiredFields = [
        'name' => 'Name',
        'composer' => 'Composer',
        'arranger' => 'Arranger',
    ];
    foreach ($requiredFields as $field => $label) {
        $length = function_exists('mb_strlen') ? mb_strlen($values[$field]) : strlen($values[$field]);
        if ($values[$field] === '') {
            $errors[] = sprintf('%s is required.', $label);
        } elseif ($length > 255) {
            $errors[] = sprintf('%s must be at most 255 characters.', $label);
        }
    }

    if ($values['duration'] !== '') {
        if (preg_match('/^([0-9]{1,4}):([0-5][0-9])$/', $values['duration'], $matches)) {
            $minutes = (int) $matches[1];
            if ($minutes > 1000) {
                $errors[] = 'Duration must be at most 1000 minutes.';
            } else {
                $values['duration_seconds'] = $minutes * 60 + (int) $matches[2];
            }
        } else {
            $errors[] = 'Duration must use MM:SS format (e.g. 3:45).';
        }
    }

    return [
        'errors' => $errors,
        'values' => $values,
    ];
}

function formatSongDuration(?int $seconds): string
{
    if ($seconds === null || $seconds < 0) {
        return '';
    }

    return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
}
