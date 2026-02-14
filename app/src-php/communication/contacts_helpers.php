<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/team_helpers.php';

function fetchContacts(PDO $pdo, int $teamId, ?string $search = null): array
{
    if ($teamId <= 0) {
        return [];
    }

    $params = [':team_id' => $teamId];
    $where = 'WHERE team_id = :team_id';

    if ($search !== null) {
        $search = trim($search);
    }

    if ($search !== null && $search !== '') {
        $where .= ' AND (firstname LIKE :like OR surname LIKE :like OR email LIKE :like OR phone LIKE :like OR city LIKE :like)';
        $params[':like'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare(
        'SELECT id, firstname, surname, email, phone, city, country, updated_at
         FROM contacts
         ' . $where . '
         ORDER BY firstname, surname, id'
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetchContact(PDO $pdo, int $teamId, int $contactId): ?array
{
    if ($teamId <= 0 || $contactId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM contacts WHERE id = :id AND team_id = :team_id LIMIT 1');
    $stmt->execute([':id' => $contactId, ':team_id' => $teamId]);
    $contact = $stmt->fetch();

    return $contact ?: null;
}

