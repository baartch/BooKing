<?php
$personalMailboxes = [];
$noticeKey = (string) ($_GET['notice'] ?? '');
if ($noticeKey === 'mailbox_created') {
    $notice = 'Mailbox created successfully.';
} elseif ($noticeKey === 'mailbox_updated') {
    $notice = 'Mailbox updated successfully.';
}

try {
    $pdo = getDatabaseConnection();
    $personalMailboxes = fetchUserMailboxes($pdo, (int) ($currentUser['user_id'] ?? 0));
    foreach ($personalMailboxes as &$mailbox) {
        $mailbox['team_name'] = $mailbox['team_name'] ?? '';
    }
    unset($mailbox);
} catch (Throwable $error) {
    $errors[] = 'Failed to load mailboxes.';
    logAction($currentUser['user_id'] ?? null, 'profile_mailbox_list_error', $error->getMessage());
    $pdo = null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$action = $_POST['action'] ?? '';
if ($action !== 'delete_mailbox') {
    return;
}

$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
if ($mailboxId <= 0) {
    $errors[] = 'Select a mailbox to delete.';
    return;
}

try {
    $pdo = getDatabaseConnection();
    $existingMailbox = fetchUserMailbox($pdo, $mailboxId, (int) ($currentUser['user_id'] ?? 0));
    if (!$existingMailbox) {
        $errors[] = 'Mailbox not found.';
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM mailboxes WHERE id = :id');
    $stmt->execute([':id' => $existingMailbox['id']]);
    $notice = 'Mailbox deleted successfully.';
    logAction($currentUser['user_id'] ?? null, 'profile_mailbox_deleted', sprintf('Deleted mailbox %d', $existingMailbox['id']));
    $personalMailboxes = fetchUserMailboxes($pdo, (int) ($currentUser['user_id'] ?? 0));
    foreach ($personalMailboxes as &$mailbox) {
        $mailbox['team_name'] = $mailbox['team_name'] ?? '';
    }
    unset($mailbox);
} catch (Throwable $error) {
    $errors[] = 'Failed to delete mailbox.';
    logAction($currentUser['user_id'] ?? null, 'profile_mailbox_delete_error', $error->getMessage());
}
