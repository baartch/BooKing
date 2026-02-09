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

function createObjectLink(PDO $pdo, string $leftType, int $leftId, string $rightType, int $rightId): bool
{
    if ($leftType === '' || $rightType === '' || $leftId <= 0 || $rightId <= 0) {
        return false;
    }

    if ($leftType === $rightType && $leftId === $rightId) {
        return false;
    }

    [$normalizedLeftType, $normalizedLeftId, $normalizedRightType, $normalizedRightId] = normalizeObjectLink(
        $leftType,
        $leftId,
        $rightType,
        $rightId
    );

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO object_links (left_type, left_id, right_type, right_id)
         VALUES (:left_type, :left_id, :right_type, :right_id)'
    );
    $stmt->execute([
        ':left_type' => $normalizedLeftType,
        ':left_id' => $normalizedLeftId,
        ':right_type' => $normalizedRightType,
        ':right_id' => $normalizedRightId
    ]);

    return $stmt->rowCount() > 0;
}
