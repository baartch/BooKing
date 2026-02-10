<?php

function normalizeObjectLink(string $leftType, int $leftId, string $rightType, int $rightId): array
{
    if ($leftType === $rightType) {
        if ($leftId <= $rightId) {
            return [$leftType, $leftId, $rightType, $rightId];
        }

        return [$rightType, $rightId, $leftType, $leftId];
    }

    if (strcmp($leftType, $rightType) <= 0) {
        return [$leftType, $leftId, $rightType, $rightId];
    }

    return [$rightType, $rightId, $leftType, $leftId];
}

function createObjectLink(PDO $pdo, string $leftType, int $leftId, string $rightType, int $rightId, ?int $teamId, ?int $userId): bool
{
    if ($leftType === '' || $rightType === '' || $leftId <= 0 || $rightId <= 0) {
        return false;
    }

    if ($leftType === $rightType && $leftId === $rightId) {
        return false;
    }

    if ($teamId === null && $userId === null) {
        return false;
    }

    [$normalizedLeftType, $normalizedLeftId, $normalizedRightType, $normalizedRightId] = normalizeObjectLink(
        $leftType,
        $leftId,
        $rightType,
        $rightId
    );

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO object_links (left_type, left_id, right_type, right_id, team_id, user_id)
         VALUES (:left_type, :left_id, :right_type, :right_id, :team_id, :user_id)'
    );
    $stmt->execute([
        ':left_type' => $normalizedLeftType,
        ':left_id' => $normalizedLeftId,
        ':right_type' => $normalizedRightType,
        ':right_id' => $normalizedRightId,
        ':team_id' => $teamId,
        ':user_id' => $userId
    ]);

    return $stmt->rowCount() > 0;
}

function clearObjectLinks(PDO $pdo, string $type, int $id, ?int $teamId, ?int $userId): void
{
    if ($type === '' || $id <= 0) {
        return;
    }

    if ($teamId === null && $userId === null) {
        return;
    }

    $sql =
        'DELETE FROM object_links
         WHERE ((left_type = :type AND left_id = :id)
            OR (right_type = :type AND right_id = :id))
           AND ';

    if ($userId !== null) {
        $sql .= 'user_id = :user_id';
    } else {
        $sql .= 'user_id IS NULL AND team_id = :team_id';
    }

    $stmt = $pdo->prepare($sql);
    $params = [
        ':type' => $type,
        ':id' => $id
    ];

    if ($userId !== null) {
        $params[':user_id'] = $userId;
    } else {
        $params[':team_id'] = $teamId;
    }

    $stmt->execute($params);
}

function clearAllObjectLinks(PDO $pdo, string $type, int $id): void
{
    if ($type === '' || $id <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM object_links
         WHERE (left_type = :left_type AND left_id = :left_id)
            OR (right_type = :right_type AND right_id = :right_id)'
    );
    $stmt->execute([
        ':left_type' => $type,
        ':left_id' => $id,
        ':right_type' => $type,
        ':right_id' => $id
    ]);
}
