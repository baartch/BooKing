<?php

function getLinkIcons(): array
{
    return [
        'contact' => 'fa-user',
        'venue' => 'fa-location-dot',
        'email' => 'fa-envelope',
        'task' => 'fa-list-check',
        'conversation' => 'fa-comments'
    ];
}

function fetchLinkedObjects(PDO $pdo, string $type, int $id, ?int $teamId, ?int $userId): array
{
    $type = trim($type);
    if ($type === '' || $id <= 0) {
        return [];
    }

    if ($teamId === null && $userId === null) {
        return [];
    }

    $sql =
        'SELECT left_type, left_id, right_type, right_id
         FROM object_links
         WHERE ((left_type = :left_type AND left_id = :left_id)
            OR (right_type = :right_type AND right_id = :right_id))
           AND ';

    $params = [
        ':left_type' => $type,
        ':left_id' => $id,
        ':right_type' => $type,
        ':right_id' => $id
    ];

    if ($userId !== null && $teamId !== null) {
        $sql .= '(user_id = :user_id OR (user_id IS NULL AND team_id = :team_id))';
        $params[':user_id'] = $userId;
        $params[':team_id'] = $teamId;
    } elseif ($userId !== null) {
        $sql .= 'user_id = :user_id';
        $params[':user_id'] = $userId;
    } else {
        $sql .= 'user_id IS NULL AND team_id = :team_id';
        $params[':team_id'] = $teamId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return [];
    }

    $rawLinks = [];
    $idsByType = [
        'contact' => [],
        'venue' => [],
        'email' => [],
        'task' => []
    ];

    foreach ($rows as $row) {
        $isLeft = $row['left_type'] === $type && (int) $row['left_id'] === $id;
        $linkType = $isLeft ? (string) $row['right_type'] : (string) $row['left_type'];
        $linkId = (int) ($isLeft ? $row['right_id'] : $row['left_id']);
        if (!array_key_exists($linkType, $idsByType)) {
            continue;
        }
        $rawLinks[] = ['type' => $linkType, 'id' => $linkId];
        $idsByType[$linkType][] = $linkId;
    }

    $labels = [];
    if ($idsByType['contact']) {
        $contactIds = array_values(array_unique($idsByType['contact']));
        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        $sql =
            'SELECT id, firstname, surname, email
             FROM contacts
             WHERE id IN (' . $placeholders . ')';

        $params = $contactIds;
        if ($teamId !== null && $userId === null) {
            $sql .= ' AND team_id = ?';
            $params[] = $teamId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $name = trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['surname'] ?? ''));
            $email = (string) ($row['email'] ?? '');
            $label = $name !== '' ? $name : $email;
            if ($label === '') {
                $label = 'Contact #' . (int) $row['id'];
            }
            $labels['contact:' . (int) $row['id']] = $label;
        }
    }

    if ($idsByType['venue']) {
        $venueIds = array_values(array_unique($idsByType['venue']));
        $placeholders = implode(',', array_fill(0, count($venueIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT id, name, contact_email
             FROM venues
             WHERE id IN (' . $placeholders . ')'
        );
        $stmt->execute($venueIds);
        foreach ($stmt->fetchAll() as $row) {
            $name = (string) ($row['name'] ?? '');
            $email = (string) ($row['contact_email'] ?? '');
            $label = $name !== '' ? $name : $email;
            if ($label === '') {
                $label = 'Venue #' . (int) $row['id'];
            }
            $labels['venue:' . (int) $row['id']] = $label;
        }
    }

    if ($idsByType['email']) {
        $emailIds = array_values(array_unique($idsByType['email']));
        $placeholders = implode(',', array_fill(0, count($emailIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT id, subject, from_email, to_emails
             FROM email_messages
             WHERE id IN (' . $placeholders . ')'
        );
        $stmt->execute($emailIds);
        foreach ($stmt->fetchAll() as $row) {
            $subject = trim((string) ($row['subject'] ?? ''));
            $from = trim((string) ($row['from_email'] ?? ''));
            $to = trim((string) ($row['to_emails'] ?? ''));
            $label = $subject !== '' ? $subject : ($from !== '' ? $from : ($to !== '' ? $to : 'Email #' . (int) $row['id']));
            $labels['email:' . (int) $row['id']] = $label;
        }
    }

    if ($idsByType['task']) {
        $taskIds = array_values(array_unique($idsByType['task']));
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT id, title, priority
             FROM team_tasks
             WHERE id IN (' . $placeholders . ')'
        );
        $stmt->execute($taskIds);
        foreach ($stmt->fetchAll() as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $label = $title !== '' ? $title : 'Task #' . (int) $row['id'];
            $labels['task:' . (int) $row['id']] = $label;
        }
    }

    $results = [];
    $seen = [];
    foreach ($rawLinks as $link) {
        $key = $link['type'] . ':' . (int) $link['id'];
        if (isset($seen[$key]) || !isset($labels[$key])) {
            continue;
        }
        $seen[$key] = true;
        $results[] = [
            'type' => $link['type'],
            'id' => (int) $link['id'],
            'label' => $labels[$key]
        ];
    }

    return $results;
}
