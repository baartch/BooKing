<?php
require_once __DIR__ . '/../core/database.php';

function fetchUserTeams(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT t.id, t.name
         FROM teams t
         JOIN team_members tm ON tm.team_id = t.id
         WHERE tm.user_id = :user_id
         ORDER BY t.name'
    );
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

function fetchUserTeamIds(PDO $pdo, int $userId): array
{
    $teams = fetchUserTeams($pdo, $userId);
    return array_map('intval', array_column($teams, 'id'));
}

function userHasTeamAccess(PDO $pdo, int $userId, int $teamId): bool
{
    if ($userId <= 0 || $teamId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM team_members WHERE user_id = :user_id AND team_id = :team_id LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId, ':team_id' => $teamId]);
    return (bool) $stmt->fetchColumn();
}

function fetchTeamName(PDO $pdo, int $teamId): string
{
    if ($teamId <= 0) {
        return '';
    }

    $stmt = $pdo->prepare('SELECT name FROM teams WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $teamId]);

    return trim((string) ($stmt->fetchColumn() ?: ''));
}

function resolveActiveTeamId(PDO $pdo, int $userId, int $requestedTeamId = 0): int
{
    $teamIds = fetchUserTeamIds($pdo, $userId);
    if (!$teamIds) {
        return 0;
    }

    if ($requestedTeamId > 0 && in_array($requestedTeamId, $teamIds, true)) {
        return $requestedTeamId;
    }

    return (int) ($teamIds[0] ?? 0);
}

/**
 * Runs a team tab list fetch, recording a per-tab error and log entry on failure.
 *
 * @param callable $fetch Returns the list rows; may throw.
 * @param array<string, string[]> $errors Collected by reference; gets a
 *        "Failed to load <tabKey>." message on failure.
 */
function loadTeamTabList(callable $fetch, string $tabKey, array &$errors, ?int $userId): array
{
    try {
        return (array) $fetch();
    } catch (Throwable $error) {
        if (!is_array($errors[$tabKey] ?? null)) {
            $errors[$tabKey] = [];
        }
        $errors[$tabKey][] = 'Failed to load ' . $tabKey . '.';
        logAction($userId, 'team_' . $tabKey . '_list_error', $error->getMessage());
        return [];
    }
}
