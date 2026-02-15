<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../src-php/core/form_helpers.php';
require_once __DIR__ . '/../../src-php/core/object_links.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);

$redirectParams = [
    'tab' => 'email',
    'mailbox_id' => $mailboxId,
    'folder' => 'trash',
    'sort' => (string) ($_POST['sort'] ?? 'received_desc'),
    'filter' => (string) ($_POST['filter'] ?? ''),
    'page' => (int) ($_POST['page'] ?? 1)
];

if ($mailboxId <= 0) {
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
        'SELECT id
         FROM email_messages
         WHERE mailbox_id = :mailbox_id
           AND folder = "trash"'
    );
    $stmt->execute([':mailbox_id' => $mailboxId]);
    $trashIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));

    if ($trashIds) {
        $pdo->beginTransaction();
        try {
            foreach ($trashIds as $trashId) {
                clearAllObjectLinks($pdo, 'email', $trashId);
            }

            $deleteStmt = $pdo->prepare(
                'DELETE FROM email_messages
                 WHERE mailbox_id = :mailbox_id
                   AND folder = "trash"'
            );
            $deleteStmt->execute([':mailbox_id' => $mailboxId]);

            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    logAction($userId, 'email_trash_emptied', sprintf('Emptied trash for mailbox %d', $mailboxId));
    $redirectParams['notice'] = 'deleted';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
} catch (Throwable $error) {
    logAction($userId, 'email_trash_empty_error', $error->getMessage());
    $redirectParams['notice'] = 'deleted';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
}
