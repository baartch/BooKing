<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/communication/email_helpers.php';
require_once __DIR__ . '/../../src-php/core/form_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
$conversationId = (int) ($_POST['conversation_id'] ?? 0);

$redirectParams = [
    'tab' => 'conversations',
    'mailbox_id' => $mailboxId,
    'conversation_id' => $conversationId
];

if ($mailboxId <= 0 || $conversationId <= 0) {
    header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $mailbox = ensureMailboxAccess($pdo, $mailboxId, $userId);
    if (!$mailbox) {
        header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $conversation = ensureConversationScopeAccess($pdo, $conversationId, $mailbox, $userId);
    if ($conversation) {
        $stmt = $pdo->prepare(
            'UPDATE email_conversations
             SET is_closed = 0,
                 closed_at = NULL
             WHERE id = :id AND is_closed = 1'
        );
        $stmt->execute([
            ':id' => $conversationId
        ]);

        logAction($userId, 'conversation_reopened', sprintf('Reopened conversation %d in mailbox %d', $conversationId, $mailboxId));
    }
} catch (Throwable $error) {
    logAction($userId, 'conversation_reopen_error', $error->getMessage());
}

header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
exit;
