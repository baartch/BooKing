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
