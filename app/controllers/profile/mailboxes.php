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
$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
$userId = (int) ($currentUser['user_id'] ?? 0);

if ($action === 'delete_mailbox') {
    if ($mailboxId <= 0) {
        $errors[] = 'Select a mailbox to delete.';
        return;
    }

    try {
        $pdo = getDatabaseConnection();
        $existingMailbox = fetchUserMailbox($pdo, $mailboxId, $userId);
        if (!$existingMailbox) {
            $errors[] = 'Mailbox not found.';
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM mailboxes WHERE id = :id');
        $stmt->execute([':id' => $existingMailbox['id']]);
        $notice = 'Mailbox deleted successfully.';
        logAction($userId, 'profile_mailbox_deleted', sprintf('Deleted mailbox %d', $existingMailbox['id']));
    } catch (Throwable $error) {
        $errors[] = 'Failed to delete mailbox.';
        logAction($userId, 'profile_mailbox_delete_error', $error->getMessage());
    }
}

if (in_array($action, ['create_mailbox', 'update_mailbox', 'test_imap', 'test_smtp'], true)) {
    $allowedEncryptions = ['ssl', 'tls', 'none'];
    $defaultImapPort = 993;
    $defaultSmtpPort = 587;

    if ($action === 'test_imap' || $action === 'test_smtp') {
        if ($mailboxId <= 0) {
            $errors[] = 'Save mailbox first before testing.';
        } else {
            try {
                $existingMailbox = fetchUserMailbox($pdo, $mailboxId, $userId);
                if (!$existingMailbox) {
                    $errors[] = 'Mailbox not found for test.';
                } elseif ($action === 'test_imap') {
                    $imapPasswordOverride = trim((string) ($_POST['imap_password'] ?? ''));
                    $imapResult = testImapConnection($existingMailbox, $imapPasswordOverride !== '' ? $imapPasswordOverride : null);
                    if (!empty($imapResult['ok'])) {
                        $notice = (string) ($imapResult['message'] ?? 'IMAP connection successful.');
                    } else {
                        $errors[] = (string) ($imapResult['message'] ?? 'IMAP connection failed.');
                    }
                } else {
                    $smtpPasswordOverride = trim((string) ($_POST['smtp_password'] ?? ''));
                    $smtpRecipient = trim((string) ($_POST['smtp_test_recipient'] ?? ''));
                    $smtpResult = sendSmtpTestEmail($pdo, $existingMailbox, $smtpRecipient, $smtpPasswordOverride !== '' ? $smtpPasswordOverride : null);
                    if (!empty($smtpResult['ok'])) {
                        $notice = (string) ($smtpResult['message'] ?? 'SMTP test email sent successfully.');
                    } else {
                        $errors[] = (string) ($smtpResult['message'] ?? 'SMTP test email failed.');
                    }
                }
            } catch (Throwable $error) {
                $errors[] = 'Mailbox test failed.';
                logAction($userId, 'profile_mailbox_test_error', $error->getMessage());
            }
        }
    }

    if ($action === 'create_mailbox' || $action === 'update_mailbox') {
        $isCreate = $action === 'create_mailbox';

        $formResult = buildMailboxFormInput($_POST, [
            'allowed_encryptions' => $allowedEncryptions,
            'default_imap_port' => $defaultImapPort,
            'default_smtp_port' => $defaultSmtpPort,
            'require_team' => false,
            'is_create' => $isCreate,
        ]);

        if (!empty($formResult['errors'])) {
            $errors = array_merge($errors, $formResult['errors']);
        } else {
            try {
                $formValues = $formResult['values'];
                $formValues['user_id'] = $userId;

                if ($isCreate) {
                    persistMailbox($pdo, $formValues, $formResult['imap_password'], $formResult['smtp_password']);
                    $notice = 'Mailbox created successfully.';
                } else {
                    $existingMailbox = fetchUserMailbox($pdo, $mailboxId, $userId);
                    if (!$existingMailbox) {
                        $errors[] = 'Mailbox not found.';
                    } else {
                        $formValues['user_id'] = (int) ($existingMailbox['user_id'] ?? 0);
                        persistMailbox($pdo, $formValues, $formResult['imap_password'], $formResult['smtp_password'], $existingMailbox);
                        $notice = 'Mailbox updated successfully.';
                    }
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to save mailbox.';
                logAction($userId, 'profile_mailbox_save_error', $error->getMessage());
            }
        }
    }
}

if ($pdo) {
    $personalMailboxes = fetchUserMailboxes($pdo, $userId);
    foreach ($personalMailboxes as &$mailbox) {
        $mailbox['team_name'] = $mailbox['team_name'] ?? '';
    }
    unset($mailbox);
}
