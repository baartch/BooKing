<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/communication/email_helpers.php';
require_once __DIR__ . '/../../src-php/communication/email_parents.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$emailId = (int) ($_POST['email_id'] ?? 0);
$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
$parentType = trim((string) ($_POST['parent_type'] ?? ''));
$parentId = (int) ($_POST['parent_id'] ?? 0);
$clearParent = !empty($_POST['clear_parent']);

$redirectParams = [
    'tab' => 'email',
    'mailbox_id' => $mailboxId,
    'folder' => (string) ($_POST['folder'] ?? 'inbox'),
    'sort' => (string) ($_POST['sort'] ?? 'received_desc'),
    'filter' => (string) ($_POST['filter'] ?? ''),
    'parent' => (string) ($_POST['parent'] ?? ''),
    'page' => (int) ($_POST['page'] ?? 1),
    'message_id' => $emailId
];

if ($emailId <= 0 || $mailboxId <= 0) {
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

    if ($clearParent) {
        $stmt = $pdo->prepare(
            'UPDATE email_messages
             SET parent_type = NULL, parent_id = NULL
             WHERE id = :id AND mailbox_id = :mailbox_id'
        );
        $stmt->execute([':id' => $emailId, ':mailbox_id' => $mailboxId]);
        logAction($userId, 'email_parent_cleared', sprintf('Cleared parent on email %d', $emailId));
    } else {
        if ($parentType === '' || $parentId <= 0) {
            header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
            exit;
        }

        $label = resolveParentLabel($pdo, $parentType, $parentId);
        if ($label === null) {
            header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
            exit;
        }

        $stmt = $pdo->prepare(
            'UPDATE email_messages
             SET parent_type = :parent_type, parent_id = :parent_id
             WHERE id = :id AND mailbox_id = :mailbox_id'
        );
        $stmt->execute([
            ':parent_type' => $parentType,
            ':parent_id' => $parentId,
            ':id' => $emailId,
            ':mailbox_id' => $mailboxId
        ]);
        logAction($userId, 'email_parent_updated', sprintf('Updated parent on email %d', $emailId));
    }
} catch (Throwable $error) {
    logAction($userId, 'email_parent_update_error', $error->getMessage());
}

header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
exit;
