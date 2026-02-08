<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/email_helpers.php';

function parseSingleEmail(string $value): ?string
{
    $emails = splitEmailList($value);
    if (count($emails) !== 1) {
        return null;
    }

    return strtolower(trim($emails[0]));
}

function findParentByEmail(PDO $pdo, string $email): ?array
{
    $normalized = strtolower(trim($email));
    if ($normalized === '') {
        return null;
    }

    $contactStmt = $pdo->prepare(
        'SELECT id, firstname, surname
         FROM contacts
         WHERE email IS NOT NULL AND LOWER(email) = :email
         LIMIT 1'
    );
    $contactStmt->execute([':email' => $normalized]);
    $contact = $contactStmt->fetch();
    if ($contact) {
        return [
            'type' => 'contact',
            'id' => (int) $contact['id'],
            'label' => trim((string) ($contact['firstname'] ?? '') . ' ' . (string) ($contact['surname'] ?? ''))
        ];
    }

    $venueStmt = $pdo->prepare(
        'SELECT id, name
         FROM venues
         WHERE contact_email IS NOT NULL AND LOWER(contact_email) = :email
         LIMIT 1'
    );
    $venueStmt->execute([':email' => $normalized]);
    $venue = $venueStmt->fetch();
    if ($venue) {
        return [
            'type' => 'venue',
            'id' => (int) $venue['id'],
            'label' => (string) ($venue['name'] ?? '')
        ];
    }

    return null;
}

function resolveParentLabel(PDO $pdo, ?string $type, ?int $id): ?string
{
    if (!$type || !$id) {
        return null;
    }

    if ($type === 'contact') {
        $stmt = $pdo->prepare('SELECT firstname, surname, email FROM contacts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $name = trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['surname'] ?? ''));
        $email = (string) ($row['email'] ?? '');
        $label = $name !== '' ? $name : $email;
        return $label !== '' ? $label : null;
    }

    if ($type === 'venue') {
        $stmt = $pdo->prepare('SELECT name, contact_email FROM venues WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $name = (string) ($row['name'] ?? '');
        $email = (string) ($row['contact_email'] ?? '');
        $label = $name !== '' ? $name : $email;
        return $label !== '' ? $label : null;
    }

    return null;
}

function searchParentCandidates(PDO $pdo, string $query, int $limit = 8): array
{
    $term = trim($query);
    if ($term === '') {
        return [];
    }

    $termLike = '%' . $term . '%';
    $candidates = [];

    $contactStmt = $pdo->prepare(
        'SELECT id, firstname, surname, email
         FROM contacts
         WHERE firstname LIKE :q OR surname LIKE :q OR email LIKE :q
         ORDER BY firstname, surname
         LIMIT :limit'
    );
    $contactStmt->bindValue(':q', $termLike, PDO::PARAM_STR);
    $contactStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $contactStmt->execute();
    foreach ($contactStmt->fetchAll() as $row) {
        $name = trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['surname'] ?? ''));
        $email = (string) ($row['email'] ?? '');
        $label = $name !== '' ? $name : $email;
        $secondary = $email !== '' && $label !== $email ? $email : '';
        $candidates[] = [
            'type' => 'contact',
            'id' => (int) $row['id'],
            'label' => $label,
            'secondary' => $secondary,
            'email' => $email
        ];
    }

    if (count($candidates) < $limit) {
        $remaining = $limit - count($candidates);
        $venueStmt = $pdo->prepare(
            'SELECT id, name, contact_email
             FROM venues
             WHERE name LIKE :q OR contact_email LIKE :q
             ORDER BY name
             LIMIT :limit'
        );
        $venueStmt->bindValue(':q', $termLike, PDO::PARAM_STR);
        $venueStmt->bindValue(':limit', $remaining, PDO::PARAM_INT);
        $venueStmt->execute();
        foreach ($venueStmt->fetchAll() as $row) {
            $name = (string) ($row['name'] ?? '');
            $email = (string) ($row['contact_email'] ?? '');
            $label = $name !== '' ? $name : $email;
            $secondary = $email !== '' && $label !== $email ? $email : '';
            $candidates[] = [
                'type' => 'venue',
                'id' => (int) $row['id'],
                'label' => $label,
                'secondary' => $secondary,
                'email' => $email
            ];
        }
    }

    return $candidates;
}

function findParentCandidatesForFilter(PDO $pdo, string $query): array
{
    $term = trim($query);
    if ($term === '') {
        return ['contacts' => [], 'venues' => []];
    }

    $termLike = '%' . $term . '%';
    $contactsStmt = $pdo->prepare(
        'SELECT id
         FROM contacts
         WHERE firstname LIKE :q OR surname LIKE :q OR email LIKE :q'
    );
    $contactsStmt->execute([':q' => $termLike]);
    $contactIds = array_map('intval', array_column($contactsStmt->fetchAll(), 'id'));

    $venuesStmt = $pdo->prepare(
        'SELECT id
         FROM venues
         WHERE name LIKE :q OR contact_email LIKE :q'
    );
    $venuesStmt->execute([':q' => $termLike]);
    $venueIds = array_map('intval', array_column($venuesStmt->fetchAll(), 'id'));

    return ['contacts' => $contactIds, 'venues' => $venueIds];
}

function resolveParentLabelsForMessages(PDO $pdo, array $messages): array
{
    $contactIds = [];
    $venueIds = [];
    foreach ($messages as $row) {
        if (!empty($row['parent_type']) && !empty($row['parent_id'])) {
            if ($row['parent_type'] === 'contact') {
                $contactIds[] = (int) $row['parent_id'];
            } elseif ($row['parent_type'] === 'venue') {
                $venueIds[] = (int) $row['parent_id'];
            }
        }
    }

    $contactIds = array_values(array_unique($contactIds));
    $venueIds = array_values(array_unique($venueIds));

    $labels = [];
    if ($contactIds) {
        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT id, firstname, surname, email FROM contacts WHERE id IN (' . $placeholders . ')'
        );
        $stmt->execute($contactIds);
        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['surname'] ?? ''));
            $email = (string) ($row['email'] ?? '');
            $label = $name !== '' ? $name : $email;
            if ($label !== '') {
                $labels['contact:' . (int) $row['id']] = $label;
            }
        }
    }

    if ($venueIds) {
        $placeholders = implode(',', array_fill(0, count($venueIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT id, name, contact_email FROM venues WHERE id IN (' . $placeholders . ')'
        );
        $stmt->execute($venueIds);
        foreach ($stmt->fetchAll() as $row) {
            $name = (string) ($row['name'] ?? '');
            $email = (string) ($row['contact_email'] ?? '');
            $label = $name !== '' ? $name : $email;
            if ($label !== '') {
                $labels['venue:' . (int) $row['id']] = $label;
            }
        }
    }

    return $labels;
}
