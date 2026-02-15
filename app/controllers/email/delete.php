<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';
require_once __DIR__ . '/../../models/core/object_links.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
$emailId = (int) ($_POST['email_id'] ?? 0);

$redirectParams = [
    'tab' => 'email',
    'mailbox_id' => $mailboxId,
    'folder' => (string) ($_POST['folder'] ?? 'inbox'),
    'sort' => (string) ($_POST['sort'] ?? 'received_desc'),
    'filter' => (string) ($_POST['filter'] ?? ''),
    'page' => (int) ($_POST['page'] ?? 1)
];

if ($mailboxId <= 0 || $emailId <= 0) {
    $redirectParams['notice'] = 'deleted';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $mailbox = ensureMailboxAccess($pdo, $mailboxId, $userId);
    if (!$mailbox) {
        $redirectParams['notice'] = 'deleted';
        header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, folder
         FROM email_messages
         WHERE id = :id AND mailbox_id = :mailbox_id
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $emailId,
        ':mailbox_id' => $mailboxId
    ]);
    $message = $stmt->fetch();

    if ($message && $message['folder'] === 'trash') {
        $pdo->beginTransaction();
        try {
            clearAllObjectLinks($pdo, 'email', $emailId);

            $deleteStmt = $pdo->prepare(
                'DELETE FROM email_messages
                 WHERE id = :id AND mailbox_id = :mailbox_id'
            );
            $deleteStmt->execute([
                ':id' => $emailId,
                ':mailbox_id' => $mailboxId
            ]);

            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }

        logAction($userId, 'email_deleted_permanent', sprintf('Deleted email %d from trash', $emailId));
    } elseif ($message) {
        $updateStmt = $pdo->prepare(
            'UPDATE email_messages
             SET folder = "trash"
             WHERE id = :id AND mailbox_id = :mailbox_id'
        );
        $updateStmt->execute([
            ':id' => $emailId,
            ':mailbox_id' => $mailboxId
        ]);

        logAction($userId, 'email_deleted', sprintf('Moved email %d to trash', $emailId));
    }

    $redirectParams['notice'] = 'deleted';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
} catch (Throwable $error) {
    logAction($userId, 'email_delete_error', $error->getMessage());
    $redirectParams['notice'] = 'deleted';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
}
