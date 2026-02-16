<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/object_links.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../models/communication/contacts_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$userId = (int) ($currentUser['user_id'] ?? 0);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}

// Accept CSRF token from header, POST body, or JSON body
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? $_POST['csrf_token']
    ?? trim((string) ($input['csrf_token'] ?? ''));

if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF validation failed']);
    exit;
}

$sourceType = trim((string) ($input['source_type'] ?? ''));
$sourceId = (int) ($input['source_id'] ?? 0);
$mailboxId = (int) ($input['mailbox_id'] ?? 0);
$conversationId = isset($input['conversation_id']) ? (int) $input['conversation_id'] : null;
$detachConversation = !empty($input['detach_conversation']);
$links = (array) ($input['links'] ?? []);

$allowedSourceTypes = ['email', 'contact', 'venue'];
if (!in_array($sourceType, $allowedSourceTypes, true) || $sourceId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid source']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Determine team/user scope
    $teamId = null;
    $scopeUserId = null;

    if ($sourceType === 'email' && $mailboxId > 0) {
        $mailbox = ensureMailboxAccess($pdo, $mailboxId, $userId);
        if (!$mailbox) {
            http_response_code(403);
            echo json_encode(['error' => 'Mailbox access denied']);
            exit;
        }

        $messageStmt = $pdo->prepare('SELECT mailbox_id, team_id, user_id FROM email_messages WHERE id = :id');
        $messageStmt->execute([':id' => $sourceId]);
        $messageScope = $messageStmt->fetch();
        if (!$messageScope || (int) ($messageScope['mailbox_id'] ?? 0) !== $mailboxId) {
            http_response_code(404);
            echo json_encode(['error' => 'Email not found']);
            exit;
        }

        $teamId = !empty($mailbox['team_id'])
            ? (int) $mailbox['team_id']
            : (!empty($messageScope['team_id']) ? (int) $messageScope['team_id'] : null);
        $scopeUserId = !empty($mailbox['user_id'])
            ? (int) $mailbox['user_id']
            : (!empty($messageScope['user_id']) ? (int) $messageScope['user_id'] : null);
    } else {
        // For contacts/venues, scope to the contact's team (team-scoped contacts)
        if ($sourceType !== 'contact') {
            http_response_code(400);
            echo json_encode(['error' => 'Unsupported link scope']);
            exit;
        }

        $contactStmt = $pdo->prepare('SELECT team_id FROM contacts WHERE id = :id LIMIT 1');
        $contactStmt->execute([':id' => $sourceId]);
        $contactRow = $contactStmt->fetch();
        $teamId = !empty($contactRow['team_id']) ? (int) $contactRow['team_id'] : null;

        if ($teamId === null || !userHasTeamAccess($pdo, $userId, $teamId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Contact access denied']);
            exit;
        }

        $scopeUserId = null;
    }

    if ($teamId === null && $scopeUserId === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot determine link scope']);
        exit;
    }

    $pdo->beginTransaction();

    // Clear existing object links (contacts/venues) and re-create
    if ($teamId === null) {
        clearObjectLinks($pdo, $sourceType, $sourceId, null, $scopeUserId);
    } else {
        clearObjectLinks($pdo, $sourceType, $sourceId, $teamId, null);
    }

    foreach ($links as $link) {
        $linkType = trim((string) ($link['type'] ?? ''));
        $linkId = (int) ($link['id'] ?? 0);
        if ($linkType === '' || $linkId <= 0) {
            continue;
        }
        if (!in_array($linkType, ['contact', 'venue', 'email'], true)) {
            continue;
        }
        $linkTeamId = $teamId;
        $linkUserId = $scopeUserId;
        if ($teamId !== null) {
            $linkUserId = null;
        } elseif ($scopeUserId !== null) {
            $linkTeamId = null;
        }
        createObjectLink($pdo, $sourceType, $sourceId, $linkType, $linkId, $linkTeamId, $linkUserId);
    }

    // Handle conversation assignment / detach for emails
    if ($sourceType === 'email') {
        if ($detachConversation) {
            $stmt = $pdo->prepare('UPDATE email_messages SET conversation_id = NULL WHERE id = :id');
            $stmt->execute([':id' => $sourceId]);
            logAction($userId, 'email_conversation_detached', sprintf('Detached email %d from conversation', $sourceId));
        } elseif ($conversationId !== null && $conversationId > 0) {
            $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
            if ($conversation) {
                $stmt = $pdo->prepare('UPDATE email_messages SET conversation_id = :cid WHERE id = :id');
                $stmt->execute([':cid' => $conversationId, ':id' => $sourceId]);
                logAction($userId, 'email_conversation_assigned', sprintf('Assigned email %d to conversation %d', $sourceId, $conversationId));
            } else {
                $errorPayload = sprintf('Conversation access denied. conversation_id=%d user_id=%d', $conversationId, $userId);
                logAction($userId, 'email_conversation_assign_denied', $errorPayload);
            }
        }
    }

    $pdo->commit();

    logAction($userId, 'links_saved', sprintf('Saved links for %s:%d', $sourceType, $sourceId));
    echo json_encode(['ok' => true]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logAction($userId, 'links_save_error', $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save links', 'detail' => $error->getMessage()]);
}
