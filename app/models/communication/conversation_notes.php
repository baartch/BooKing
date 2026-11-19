<?php

/** Combine emails and notes in the same newest-first order as the email view. */
function buildConversationTimeline(array $emails, array $notes): array
{
    $timeline = [];
    foreach ($emails as $email) {
        $date = ($email['folder'] ?? '') === 'inbox'
            ? ($email['received_at'] ?? $email['created_at'] ?? '')
            : ($email['sent_at'] ?? $email['created_at'] ?? '');
        $timeline[] = ['type' => 'email', 'item' => $email, 'date' => (string) $date];
    }
    foreach ($notes as $note) {
        $timeline[] = ['type' => 'note', 'item' => $note, 'date' => (string) $note['occurred_at']];
    }
    usort($timeline, static function (array $a, array $b): int {
        return strcmp($b['date'], $a['date'])
            ?: strcmp($a['type'], $b['type'])
            ?: ((int) $b['item']['id'] <=> (int) $a['item']['id']);
    });
    return $timeline;
}

function resolveNoteContactTeam(array $teams, int $selected): ?int
{
    $ids = array_map('intval', array_column($teams, 'id'));
    if ($selected > 0) {
        return in_array($selected, $ids, true) ? $selected : null;
    }
    return count($ids) === 1 ? $ids[0] : null;
}

function splitNoteParticipantName(string $name): array
{
    $parts = preg_split('/\s+/u', trim($name), 2) ?: [];
    return [trim((string) ($parts[0] ?? '')), trim((string) ($parts[1] ?? ''))];
}

function noteTextLength(string $text): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }
    $count = preg_match_all('/./us', $text);
    return $count === false ? strlen($text) : $count;
}

function noteTargetMatchesScope(array $conversation, array $target): bool
{
    $conversationTeam = (int) ($conversation['team_id'] ?? 0);
    $targetTeam = (int) ($target['team_id'] ?? 0);
    $targetUser = (int) ($target['user_id'] ?? 0);
    if ($conversationTeam > 0) {
        return $targetUser === 0 && $targetTeam === $conversationTeam;
    }
    return $targetUser === 0 || $targetUser === (int) ($conversation['user_id'] ?? 0);
}

function fetchConversationNotes(PDO $pdo, int $conversationId, int $userId): array
{
    $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
    if (!$conversation) {
        throw new RuntimeException('Conversation access denied.');
    }
    $stmt = $pdo->prepare('SELECT * FROM conversation_notes WHERE conversation_id = ? ORDER BY occurred_at DESC, id DESC');
    $stmt->execute([$conversationId]);
    $notes = $stmt->fetchAll();
    $participants = $pdo->prepare('SELECT c.id, c.firstname, c.surname FROM contacts c JOIN team_members tm ON tm.team_id = c.team_id AND tm.user_id = ? JOIN conversation_note_participants p ON p.contact_id = c.id WHERE p.note_id = ? ORDER BY c.firstname, c.surname');
    foreach ($notes as &$note) {
        $participants->execute([$userId, (int) $note['id']]);
        $note['participants'] = $participants->fetchAll();
        $note['links'] = fetchLinkedObjects($pdo, 'conversation_note', (int) $note['id'], !empty($conversation['team_id']) ? (int) $conversation['team_id'] : null, !empty($conversation['user_id']) ? (int) $conversation['user_id'] : null);
        $note['links'] = array_values(array_filter($note['links'], static function (array $link) use ($pdo, $conversation, $userId): bool {
            try {
                $target = resolveLinkSourceScopeOrThrow($pdo, (string) $link['type'], (int) $link['id'], $userId, [
                    'team_id' => $conversation['team_id'] ?? null,
                    'user_id' => $conversation['user_id'] ?? null,
                ]);
                return noteTargetMatchesScope($conversation, $target);
            } catch (Throwable $error) {
                return false;
            }
        }));
    }
    unset($note);
    return $notes;
}

function fetchConversationNoteTeams(PDO $pdo, array $conversation, int $userId): array
{
    $teams = fetchUserTeams($pdo, $userId);
    if (!empty($conversation['team_id'])) {
        $teamId = (int) $conversation['team_id'];
        return array_values(array_filter($teams, static fn($team) => (int) $team['id'] === $teamId));
    }
    return $teams;
}

function fetchConversationNoteContactOptions(PDO $pdo, array $conversation, int $userId): array
{
    $teams = fetchConversationNoteTeams($pdo, $conversation, $userId);
    if (!$teams) {
        return [];
    }
    $ids = array_map(static fn($team) => (int) $team['id'], $teams);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('SELECT id, team_id, firstname, surname, email FROM contacts WHERE team_id IN (' . $placeholders . ') ORDER BY firstname, surname');
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

function refreshConversationNoteActivity(PDO $pdo, int $conversationId): void
{
    $stmt = $pdo->prepare(
        'SELECT MAX(event_at) FROM (
            SELECT CASE WHEN folder = "inbox" THEN COALESCE(received_at, created_at) ELSE COALESCE(sent_at, created_at) END AS event_at FROM email_messages WHERE conversation_id = ?
            UNION ALL
            SELECT occurred_at AS event_at FROM conversation_notes WHERE conversation_id = ?
         ) AS timeline'
    );
    $stmt->execute([$conversationId, $conversationId]);
    $latest = $stmt->fetchColumn();
    $update = $pdo->prepare('UPDATE email_conversations SET last_activity_at = ? WHERE id = ?');
    $update->execute([$latest, $conversationId]);
}

function saveConversationNote(PDO $pdo, int $conversationId, int $userId, int $noteId, array $input): int
{
    $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
    if (!$conversation) {
        throw new RuntimeException('Conversation access denied.');
    }
    $body = trim((string) ($input['body'] ?? ''));
    $dateInput = trim((string) ($input['occurred_at'] ?? ''));
    $date = DateTime::createFromFormat('!Y-m-d\TH:i', $dateInput);
    if ($body === '' || !$date || $date->format('Y-m-d\TH:i') !== $dateInput) {
        throw new InvalidArgumentException('Enter a note and a valid date and time.');
    }
    $teams = fetchUserTeams($pdo, $userId);
    $teamId = !empty($conversation['team_id']) ? (int) $conversation['team_id'] : null;
    if ($teamId !== null) {
        $teams = array_values(array_filter($teams, static fn($team) => (int) $team['id'] === $teamId));
    }
    $names = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($input['new_participants'] ?? '')) ?: []), static fn($name) => $name !== ''));
    $contactTeam = $names ? resolveNoteContactTeam($teams, (int) ($input['contact_team_id'] ?? 0)) : null;
    if ($names && $contactTeam === null) {
        throw new InvalidArgumentException('Select an available team for new contacts.');
    }
    foreach ($names as $name) {
        if (noteTextLength($name) > 120) {
            throw new InvalidArgumentException('Participant names must be 120 characters or fewer.');
        }
    }
    $participantIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['participants'] ?? [])), static fn($id) => $id > 0)));
    foreach ($participantIds as $contactId) {
        $target = resolveContactLinkScopeOrThrow($pdo, $contactId, $userId);
        if (!noteTargetMatchesScope($conversation, $target)) {
            throw new RuntimeException('Participant is not accessible to this conversation.');
        }
    }
    $links = [];
    foreach ((array) ($input['link_items'] ?? []) as $raw) {
        if (!preg_match('/^(contact|venue|show|task|email):([1-9][0-9]*)$/', (string) $raw, $parts)) {
            throw new InvalidArgumentException('Invalid related object.');
        }
        $type = $parts[1];
        $id = (int) $parts[2];
        $target = resolveLinkSourceScopeOrThrow($pdo, $type, $id, $userId, ['team_id' => $teamId, 'user_id' => $conversation['user_id'] ?? null]);
        if ($type !== 'venue' && !noteTargetMatchesScope($conversation, $target)) {
            throw new RuntimeException('Related object is not accessible to this conversation.');
        }
        $links[$type . ':' . $id] = [$type, $id];
    }

    $pdo->beginTransaction();
    try {
        if ($noteId > 0) {
            $stmt = $pdo->prepare('UPDATE conversation_notes SET body = ?, occurred_at = ? WHERE id = ? AND conversation_id = ?');
            $stmt->execute([$body, $date->format('Y-m-d H:i:s'), $noteId, $conversationId]);
            $check = $pdo->prepare('SELECT id FROM conversation_notes WHERE id = ? AND conversation_id = ?');
            $check->execute([$noteId, $conversationId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Note not found.');
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO conversation_notes (conversation_id, body, occurred_at, created_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$conversationId, $body, $date->format('Y-m-d H:i:s'), $userId]);
            $noteId = (int) $pdo->lastInsertId();
        }
        foreach ($names as $name) {
            [$firstName, $surname] = splitNoteParticipantName($name);
            $stmt = $pdo->prepare('INSERT INTO contacts (team_id, firstname, surname) VALUES (?, ?, ?)');
            $stmt->execute([$contactTeam, $firstName, $surname !== '' ? $surname : null]);
            $participantIds[] = (int) $pdo->lastInsertId();
        }
        if (array_key_exists('participants', $input) || $names) {
            $stmt = $pdo->prepare('DELETE FROM conversation_note_participants WHERE note_id = ?');
            $stmt->execute([$noteId]);
            $stmt = $pdo->prepare('INSERT INTO conversation_note_participants (note_id, contact_id) VALUES (?, ?)');
            foreach ($participantIds as $id) {
                $stmt->execute([$noteId, $id]);
            }
        }
        $scopeUser = !empty($conversation['user_id']) ? (int) $conversation['user_id'] : null;
        clearObjectLinks($pdo, 'conversation_note', $noteId, $teamId, $scopeUser);
        foreach ($links as [$type, $id]) {
            createObjectLink($pdo, 'conversation_note', $noteId, $type, $id, $teamId, $scopeUser);
        }
        refreshConversationNoteActivity($pdo, $conversationId);
        $pdo->commit();
        return $noteId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function deleteConversationNote(PDO $pdo, int $conversationId, int $userId, int $noteId): void
{
    if (!ensureConversationAccess($pdo, $conversationId, $userId)) {
        throw new RuntimeException('Conversation access denied.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM conversation_notes WHERE id = ? AND conversation_id = ?');
        $stmt->execute([$noteId, $conversationId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Note not found.');
        }
        clearAllObjectLinks($pdo, 'conversation_note', $noteId);
        $stmt = $pdo->prepare('DELETE FROM conversation_notes WHERE id = ? AND conversation_id = ?');
        $stmt->execute([$noteId, $conversationId]);
        refreshConversationNoteActivity($pdo, $conversationId);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
