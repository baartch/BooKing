<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/layout.php';
require_once __DIR__ . '/../../models/communication/mailbox_helpers.php';

$errors = [];
$notice = '';
$defaultPageSize = 25;
$minPageSize = 25;
$maxPageSize = 500;
$currentPageSize = (int) ($currentUser['venues_page_size'] ?? $defaultPageSize);
$currentPageSize = max($minPageSize, min($maxPageSize, $currentPageSize));
$activeTab = $_GET['tab'] ?? 'venues';
$validTabs = ['venues', 'mailboxes', 'appearance'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'venues';
}

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
} catch (Throwable $error) {
    $errors[] = 'Failed to load mailboxes.';
    logAction($currentUser['user_id'] ?? null, 'profile_mailbox_list_error', $error->getMessage());
    $pdo = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $action = $_POST['action'] ?? '';

    if ($action === 'update_page_size') {
        $requestedPageSize = (int) ($_POST['venues_page_size'] ?? $defaultPageSize);
        $requestedPageSize = max($minPageSize, min($maxPageSize, $requestedPageSize));

        try {
            $pdo = getDatabaseConnection();
            $stmt = $pdo->prepare('UPDATE users SET venues_page_size = :venues_page_size WHERE id = :id');
            $stmt->execute([
                ':venues_page_size' => $requestedPageSize,
                ':id' => $currentUser['user_id']
            ]);
            $currentPageSize = $requestedPageSize;
            $currentUser['venues_page_size'] = $requestedPageSize;
            logAction($currentUser['user_id'] ?? null, 'profile_page_size_updated', sprintf('Page size set to %d', $requestedPageSize));
            $notice = 'Page size updated successfully.';
        } catch (Throwable $error) {
            $errors[] = 'Failed to update page size.';
            logAction($currentUser['user_id'] ?? null, 'profile_page_size_error', $error->getMessage());
        }
    }

    if ($action === 'delete_mailbox') {
        $mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
        if ($mailboxId <= 0) {
            $errors[] = 'Select a mailbox to delete.';
        } else {
            try {
                $pdo = getDatabaseConnection();
                $existingMailbox = fetchUserMailbox($pdo, $mailboxId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingMailbox) {
                    $errors[] = 'Mailbox not found.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM mailboxes WHERE id = :id');
                    $stmt->execute([':id' => $existingMailbox['id']]);
                    $notice = 'Mailbox deleted successfully.';
                    logAction($currentUser['user_id'] ?? null, 'profile_mailbox_deleted', sprintf('Deleted mailbox %d', $existingMailbox['id']));
                    $personalMailboxes = fetchUserMailboxes($pdo, (int) ($currentUser['user_id'] ?? 0));
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to delete mailbox.';
                logAction($currentUser['user_id'] ?? null, 'profile_mailbox_delete_error', $error->getMessage());
            }
        }
    }
}

require __DIR__ . '/../../views/profile/index.php';
