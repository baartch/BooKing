<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/communication/email_helpers.php';

$query = trim((string) ($_GET['q'] ?? ''));

header('Content-Type: application/json; charset=utf-8');

if ($query === '') {
    echo json_encode(['items' => []]);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    $term = '%' . $query . '%';
    $contactStmt = $pdo->prepare(
        'SELECT id, firstname, surname, email
         FROM contacts
         WHERE email IS NOT NULL AND email <> ""
           AND (firstname LIKE ? OR surname LIKE ? OR email LIKE ?)
         ORDER BY firstname, surname
         LIMIT 8'
    );
    $contactStmt->execute([$term, $term, $term]);

    $items = [];
    foreach ($contactStmt->fetchAll() as $row) {
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '') {
            continue;
        }
        $name = trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['surname'] ?? ''));
        $label = $name !== '' ? $name : $email;
        $items[] = [
            'id' => (int) $row['id'],
            'type' => 'contact',
            'name' => $label,
            'label' => $label,
            'email' => $email,
            'source' => 'contact'
        ];
    }

    if (count($items) < 8) {
        $remaining = 8 - count($items);
        $limit = max(0, (int) $remaining);
        $venueStmt = $pdo->prepare(
            'SELECT id, name, contact_email
             FROM venues
             WHERE contact_email IS NOT NULL AND contact_email <> ""
               AND (name LIKE ? OR contact_email LIKE ?)
             ORDER BY name
             LIMIT ' . $limit
        );
        $venueStmt->execute([$term, $term]);

        foreach ($venueStmt->fetchAll() as $row) {
            $email = trim((string) ($row['contact_email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $name = (string) ($row['name'] ?? '');
            $label = $name !== '' ? $name : $email;
            $items[] = [
                'id' => (int) $row['id'],
                'type' => 'venue',
                'name' => $label,
                'label' => $label,
                'email' => $email,
                'source' => 'venue'
            ];
        }
    }

    echo json_encode(['items' => $items]);
} catch (Throwable $error) {
    logAction($currentUser['user_id'] ?? null, 'email_recipient_lookup_error', $error->getMessage());
    http_response_code(500);
    echo json_encode(['items' => []]);
}
