<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/communication/email_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim((string) ($_GET['q'] ?? ''));
$types = array_filter(explode(',', (string) ($_GET['types'] ?? 'contact,venue,conversation')));
$scopeMailboxId = (int) ($_GET['mailbox_id'] ?? 0);
$userId = (int) ($currentUser['user_id'] ?? 0);

if ($query === '' || mb_strlen($query) < 2) {
    echo json_encode(['items' => []]);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $term = '%' . $query . '%';
    $items = [];

    if (in_array('contact', $types, true)) {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.firstname, c.surname, c.email
             FROM contacts c
             JOIN team_members tm ON tm.team_id = c.team_id
             WHERE tm.user_id = :user_id
               AND (c.firstname LIKE :term_firstname OR c.surname LIKE :term_surname OR c.email LIKE :term_email)
             ORDER BY c.firstname, c.surname
             LIMIT 8'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':term_firstname' => $term,
            ':term_surname' => $term,
            ':term_email' => $term
        ]);
        foreach ($stmt->fetchAll() as $row) {
            $name = trim(($row['firstname'] ?? '') . ' ' . ($row['surname'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $label = $name !== '' ? $name : ($email !== '' ? $email : 'Contact #' . $row['id']);
            $items[] = [
                'id' => (int) $row['id'],
                'type' => 'contact',
                'label' => $label,
                'detail' => $email
            ];
        }
    }

    if (in_array('venue', $types, true)) {
        $stmt = $pdo->prepare(
            'SELECT id, name, city
             FROM venues
             WHERE name LIKE ? OR city LIKE ? OR contact_email LIKE ?
             ORDER BY name
             LIMIT 8'
        );
        $stmt->execute([$term, $term, $term]);
        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $city = trim((string) ($row['city'] ?? ''));
            $label = $name !== '' ? $name : 'Venue #' . $row['id'];
            $items[] = [
                'id' => (int) $row['id'],
                'type' => 'venue',
                'label' => $label,
                'detail' => $city
            ];
        }
    }

    if (in_array('email', $types, true)) {
        $mailboxes = fetchAccessibleMailboxes($pdo, $userId);
        $mailboxIds = array_values(array_filter(array_map('intval', array_column($mailboxes, 'id'))));
        if ($mailboxIds) {
            $placeholders = implode(',', array_fill(0, count($mailboxIds), '?'));
            $stmt = $pdo->prepare(
                'SELECT id, subject, from_email, to_emails
                 FROM email_messages
                 WHERE mailbox_id IN (' . $placeholders . ')
                   AND (subject LIKE ? OR from_email LIKE ? OR to_emails LIKE ?)
                 ORDER BY created_at DESC
                 LIMIT 8'
            );
            $params = array_merge($mailboxIds, [$term, $term, $term]);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) {
                $subject = trim((string) ($row['subject'] ?? ''));
                $from = trim((string) ($row['from_email'] ?? ''));
                $label = $subject !== '' ? $subject : ($from !== '' ? $from : 'Email #' . $row['id']);
                $items[] = [
                    'id' => (int) $row['id'],
                    'type' => 'email',
                    'label' => $label,
                    'detail' => $from
                ];
            }
        }
    }

    if (in_array('conversation', $types, true) && $scopeMailboxId > 0) {
        $mailbox = ensureMailboxAccess($pdo, $scopeMailboxId, $userId);
        if ($mailbox) {
            $scopeConditions = [];
            $scopeParams = [':term' => $term];
            if (!empty($mailbox['team_id'])) {
                $scopeConditions[] = '(c.team_id = :team_id AND c.user_id IS NULL)';
                $scopeParams[':team_id'] = (int) $mailbox['team_id'];
            }
            if (!empty($mailbox['user_id'])) {
                $scopeConditions[] = '(c.user_id = :user_id AND c.team_id IS NULL)';
                $scopeParams[':user_id'] = (int) $mailbox['user_id'];
            }
            if ($scopeConditions) {
                $stmt = $pdo->prepare(
                    'SELECT c.id, c.subject, c.is_closed
                     FROM email_conversations c
                     WHERE (' . implode(' OR ', $scopeConditions) . ')
                       AND c.subject LIKE :term
                     ORDER BY c.last_activity_at DESC
                     LIMIT 8'
                );
                $stmt->execute($scopeParams);
                foreach ($stmt->fetchAll() as $row) {
                    $label = trim((string) ($row['subject'] ?? ''));
                    if ($label === '') {
                        $label = 'Conversation #' . $row['id'];
                    }
                    $items[] = [
                        'id' => (int) $row['id'],
                        'type' => 'conversation',
                        'label' => $label,
                        'detail' => $row['is_closed'] ? 'closed' : 'open'
                    ];
                }
            }
        }
    }

    echo json_encode(['items' => $items]);
} catch (Throwable $error) {
    logAction($userId, 'link_search_error', $error->getMessage());
    http_response_code(500);
    echo json_encode(['items' => []]);
}
