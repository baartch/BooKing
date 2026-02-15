<?php
require_once __DIR__ . '/../../../models/auth/check.php';
require_once __DIR__ . '/../../../models/core/database.php';
require_once __DIR__ . '/../../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../../models/core/form_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$conversationId = (int) ($_POST['conversation_id'] ?? 0);

$redirectParams = [
    'tab' => 'conversations',
    'conversation_id' => $conversationId
];

if ($conversationId <= 0) {
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
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

        logAction($userId, 'conversation_reopened', sprintf('Reopened conversation %d', $conversationId));
    }
} catch (Throwable $error) {
    logAction($userId, 'conversation_reopen_error', $error->getMessage());
}

header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
exit;
