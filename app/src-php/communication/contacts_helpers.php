<?php
require_once __DIR__ . '/../core/database.php';

function fetchContacts(PDO $pdo, ?string $search = null): array
{
    $params = [];
    $where = '';

    if ($search !== null) {
        $search = trim($search);
    }

    if ($search !== null && $search !== '') {
        $where = 'WHERE (firstname LIKE :q OR surname LIKE :q OR email LIKE :q OR phone LIKE :q OR city LIKE :q)';
        $params[':q'] = '%' . $search . '%';
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

function fetchContact(PDO $pdo, int $contactId): ?array
{
    if ($contactId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM contacts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $contactId]);
    $contact = $stmt->fetch();

    return $contact ?: null;
}
