<?php

function fetchDashboardUnreadEmails(PDO $pdo, array $mailboxes): array
{
    $mailboxesById = [];
    foreach ($mailboxes as $mailbox) {
        $mailboxId = (int) ($mailbox['id'] ?? 0);
        if ($mailboxId <= 0) {
            continue;
        }

        $displayName = trim((string) ($mailbox['display_name'] ?? ''));
        $storedName = trim((string) ($mailbox['name'] ?? ''));
        $mailboxName = $displayName !== '' ? $displayName : ($storedName !== '' ? $storedName : 'Mailbox');
        $mailboxInitial = '';
        if (function_exists('mb_substr')) {
            $mailboxInitial = mb_substr($mailboxName, 0, 1, 'UTF-8');
        } elseif (preg_match('/^./us', $mailboxName, $matches) === 1) {
            $mailboxInitial = $matches[0];
        }
        $mailboxInitial = function_exists('mb_strtoupper')
            ? mb_strtoupper($mailboxInitial, 'UTF-8')
            : strtoupper($mailboxInitial);
        if ($mailboxInitial === '') {
            $mailboxInitial = 'M';
        }

        $mailboxContext = !empty($mailbox['user_id'])
            ? 'Personal'
            : trim((string) ($mailbox['team_name'] ?? ''));
        if ($mailboxContext === '') {
            $mailboxContext = 'Team';
        }

        $mailboxesById[$mailboxId] = [
            'mailbox_label' => $mailboxContext . ' · ' . $mailboxName,
            'mailbox_initial' => $mailboxInitial,
        ];
    }

    if ($mailboxesById === []) {
        return [];
    }

    $mailboxIds = array_keys($mailboxesById);
    $placeholders = implode(', ', array_fill(0, count($mailboxIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT id, mailbox_id, from_email, subject, received_at, created_at
         FROM email_messages
         WHERE mailbox_id IN (' . $placeholders . ')
           AND folder = \'inbox\'
           AND is_read = 0
         ORDER BY COALESCE(received_at, created_at) DESC, id DESC'
    );
    $stmt->execute($mailboxIds);

    $messages = [];
    foreach ($stmt->fetchAll() as $message) {
        $mailboxId = (int) ($message['mailbox_id'] ?? 0);
        if (!isset($mailboxesById[$mailboxId])) {
            continue;
        }

        $messages[] = array_merge($message, $mailboxesById[$mailboxId]);
    }

    return $messages;
}
