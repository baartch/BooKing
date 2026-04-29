<?php

require_once __DIR__ . '/../communication/email_helpers.php';

function searchLinkTargets(PDO $pdo, int $userId, string $query, array $types, int $scopeMailboxId = 0): array
{
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

    if (in_array('task', $types, true)) {
        $stmt = $pdo->prepare(
            'SELECT tt.id, tt.title, tt.priority
             FROM team_tasks tt
             JOIN team_members tm ON tm.team_id = tt.team_id
             WHERE tm.user_id = :user_id
               AND (tt.title LIKE :term_title OR tt.description LIKE :term_description)
             ORDER BY FIELD(tt.priority, "A", "B", "C"), tt.due_date IS NULL, tt.due_date
             LIMIT 8'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':term_title' => $term,
            ':term_description' => $term
        ]);
        foreach ($stmt->fetchAll() as $row) {
            $label = trim((string) ($row['title'] ?? ''));
            if ($label === '') {
                $label = 'Task #' . $row['id'];
            }
            $items[] = [
                'id' => (int) $row['id'],
                'type' => 'task',
                'label' => $label,
                'detail' => 'Priority ' . (string) ($row['priority'] ?? 'B')
            ];
        }
    }

    if (in_array('show', $types, true)) {
        $stmt = $pdo->prepare(
            'SELECT ts.id, ts.name, ts.show_date, ts.venue_text
             FROM team_shows ts
             JOIN team_members tm ON tm.team_id = ts.team_id
             WHERE tm.user_id = :user_id
               AND (ts.name LIKE :term_name OR ts.notes LIKE :term_notes OR ts.venue_text LIKE :term_venue)
             ORDER BY ts.show_date DESC, ts.id DESC
             LIMIT 8'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':term_name' => $term,
            ':term_notes' => $term,
            ':term_venue' => $term
        ]);
        foreach ($stmt->fetchAll() as $row) {
            $label = trim((string) ($row['name'] ?? ''));
            if ($label === '') {
                $label = 'Show #' . $row['id'];
            }
            $date = trim((string) ($row['show_date'] ?? ''));
            $venue = trim((string) ($row['venue_text'] ?? ''));
            $detail = trim($date . ' ' . ($venue !== '' ? '· ' . $venue : ''));
            $items[] = [
                'id' => (int) $row['id'],
                'type' => 'show',
                'label' => $label,
                'detail' => $detail
            ];
        }
    }

    if (in_array('conversation', $types, true) && $scopeMailboxId > 0) {
        $mailbox = ensureMailboxAccess($pdo, $scopeMailboxId, $userId);
        if ($mailbox) {
            $scopeConditions = [];
            $scopeParams = [
                ':term_subject' => $term,
                ':term_id_like' => $term
            ];
            $queryId = ctype_digit($query) ? (int) $query : 0;
            if ($queryId > 0) {
                $scopeParams[':conversation_id'] = $queryId;
            }
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
                       AND (c.subject LIKE :term_subject OR CAST(c.id AS CHAR) LIKE :term_id_like' . ($queryId > 0 ? ' OR c.id = :conversation_id' : '') . ')
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

    return $items;
}
