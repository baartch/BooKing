<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/navigation_helpers.php';
require_once __DIR__ . '/../../models/core/error_helpers.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$redirectParams = buildConversationsTabQuery();

try {
    $pdo = getDatabaseConnection();

    $selectStmt = $pdo->prepare(
        'SELECT c.id
         FROM email_conversations c
         LEFT JOIN team_members tm ON tm.team_id = c.team_id AND tm.user_id = :viewer_user_id_join
         WHERE c.is_closed = 1
           AND (
             (c.team_id IS NOT NULL AND tm.user_id = :viewer_user_id_team)
             OR (c.user_id IS NOT NULL AND c.user_id = :viewer_user_id_owner)
           )'
    );
    $selectStmt->execute([
        ':viewer_user_id_join' => $userId,
        ':viewer_user_id_team' => $userId,
        ':viewer_user_id_owner' => $userId,
    ]);

    $conversationIds = array_map('intval', array_column($selectStmt->fetchAll(), 'id'));

    if ($conversationIds) {
        $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));

        $pdo->beginTransaction();

        $unlinkStmt = $pdo->prepare(
            'UPDATE email_messages
             SET conversation_id = NULL
             WHERE conversation_id IN (' . $placeholders . ')'
        );
        $unlinkStmt->execute($conversationIds);

        $deleteStmt = $pdo->prepare(
            'DELETE FROM email_conversations
             WHERE id IN (' . $placeholders . ')'
        );
        $deleteStmt->execute($conversationIds);

        $pdo->commit();

        logAction($userId, 'conversation_closed_bulk_deleted', sprintf('Deleted %d closed conversations', count($conversationIds)));
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logThrowable($userId, 'conversation_closed_bulk_delete_error', $error);
}

header('Location: ' . buildCommunicationUrl($redirectParams));
exit;
