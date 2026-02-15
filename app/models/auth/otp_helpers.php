<?php
require_once __DIR__ . '/../../src-php/core/database.php';

function findUserByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, username
         FROM users
         WHERE LOWER(username) = :email
         LIMIT 1'
    );
    $stmt->execute([':email' => strtolower($email)]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function fetchOtpRecord(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM email_otps
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->execute([':email' => strtolower($email)]);
    $record = $stmt->fetch();
    return $record ?: null;
}

function saveOtpRecord(PDO $pdo, int $userId, string $email, string $codeHash, int $expiresAt, int $attempts, int $lastSentAt): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO email_otps
         (user_id, email, code_hash, expires_at, attempts, last_sent_at, created_at, updated_at)
         VALUES
         (:user_id, :email, :code_hash, :expires_at, :attempts, :last_sent_at, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
             user_id = VALUES(user_id),
             code_hash = VALUES(code_hash),
             expires_at = VALUES(expires_at),
             attempts = VALUES(attempts),
             last_sent_at = VALUES(last_sent_at),
             updated_at = NOW()'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':email' => strtolower($email),
        ':code_hash' => $codeHash,
        ':expires_at' => date('Y-m-d H:i:s', $expiresAt),
        ':attempts' => $attempts,
        ':last_sent_at' => date('Y-m-d H:i:s', $lastSentAt)
    ]);
}

function incrementOtpAttempts(PDO $pdo, string $email): int
{
    $stmt = $pdo->prepare(
        'UPDATE email_otps
         SET attempts = attempts + 1,
             updated_at = NOW()
         WHERE email = :email'
    );
    $stmt->execute([':email' => strtolower($email)]);

    $stmt = $pdo->prepare('SELECT attempts FROM email_otps WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => strtolower($email)]);
    return (int) $stmt->fetchColumn();
}

function deleteOtpRecord(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('DELETE FROM email_otps WHERE email = :email');
    $stmt->execute([':email' => strtolower($email)]);
}
