<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';
require_once __DIR__ . '/../../models/core/form_helpers.php';
require_once __DIR__ . '/../../models/communication/mail_delivery.php';
require_once __DIR__ . '/../../models/core/object_links.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'send_email');
$draftId = (int) ($_POST['draft_id'] ?? 0);
$conversationId = (int) ($_POST['conversation_id'] ?? 0);
$toEmails = normalizeEmailList((string) ($_POST['to_emails'] ?? ''));
$ccEmails = normalizeEmailList((string) ($_POST['cc_emails'] ?? ''));
$bccEmails = normalizeEmailList((string) ($_POST['bcc_emails'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$body = trim((string) ($_POST['body'] ?? ''));
$startNewConversation = !empty($_POST['start_new_conversation']);
$scheduleDate = trim((string) ($_POST['schedule_date'] ?? ''));
$scheduleTime = trim((string) ($_POST['schedule_time'] ?? ''));
$linkItems = $_POST['link_items'] ?? [];
$linkItems = is_array($linkItems) ? $linkItems : [];

$parseScheduledAt = static function (string $date, string $time): ?string {
    if ($date === '' || $time === '') {
        return null;
    }

    $timestamp = strtotime($date . ' ' . $time);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
};

$redirectParams = [
    'tab' => 'email',
    'mailbox_id' => $mailboxId,
    'folder' => (string) ($_POST['folder'] ?? 'inbox'),
    'sort' => (string) ($_POST['sort'] ?? 'received_desc'),
    'filter' => (string) ($_POST['filter'] ?? ''),
    'page' => (int) ($_POST['page'] ?? 1)
];

if ($mailboxId <= 0) {
    $redirectParams['notice'] = 'send_failed';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $mailbox = ensureMailboxAccess($pdo, $mailboxId, $userId);
    if (!$mailbox) {
        $redirectParams['notice'] = 'send_failed';
        header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $linkTeamId = !empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : null;
    $linkUserId = !empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : null;
    $fromEmail = (string) ($mailbox['smtp_username'] ?? '');
    $fromName = (string) ($mailbox['display_name'] ?? '');

    if ($conversationId > 0) {
        $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
        if (!$conversation) {
            $redirectParams['notice'] = 'send_failed';
            header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
            exit;
        }
        $redirectParams['tab'] = 'conversations';
        $redirectParams['conversation_id'] = $conversationId;
        $redirectParams['mailbox_id'] = $conversation['mailbox_id'] ?? $mailboxId;
    }

    $scheduledAt = $parseScheduledAt($scheduleDate, $scheduleTime);
    $isScheduleAction = $action === 'schedule_send';

    if ($action === 'save_draft' || $isScheduleAction) {
        if ($isScheduleAction && (!$scheduledAt || $toEmails === '')) {
            $redirectParams['notice'] = 'send_failed';
            header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
            exit;
        }

        if ($draftId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE email_messages
                 SET subject = :subject,
                     body = :body,
                     body_html = :body_html,
                     from_name = :from_name,
                     from_email = :from_email,
                     to_emails = :to_emails,
                     cc_emails = :cc_emails,
                     bcc_emails = :bcc_emails,
                     conversation_id = :conversation_id,
                     scheduled_at = :scheduled_at,
                     updated_at = NOW()
                 WHERE id = :id
                   AND mailbox_id = :mailbox_id
                   AND folder = "drafts"'
            );
            $stmt->execute([
                ':subject' => $subject !== '' ? $subject : null,
                ':body' => $body !== '' ? strip_tags($body) : null,
                ':body_html' => $body !== '' ? $body : null,
                ':from_name' => $fromName !== '' ? $fromName : null,
                ':from_email' => $fromEmail !== '' ? $fromEmail : null,
                ':to_emails' => $toEmails !== '' ? $toEmails : null,
                ':cc_emails' => $ccEmails !== '' ? $ccEmails : null,
                ':bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
                ':conversation_id' => $conversationId > 0 ? $conversationId : null,
                ':scheduled_at' => $isScheduleAction ? $scheduledAt : null,
                ':id' => $draftId,
                ':mailbox_id' => $mailbox['id']
            ]);

            if ($mailbox['user_id']) {
                $ownershipStmt = $pdo->prepare(
                    'UPDATE email_messages
                     SET team_id = NULL,
                         user_id = :user_id
                     WHERE id = :id'
                );
                $ownershipStmt->execute([
                    ':user_id' => $mailbox['user_id'],
                    ':id' => $draftId
                ]);
            }
            clearObjectLinks($pdo, 'email', $draftId, $linkTeamId, $linkUserId);
            if ($linkItems) {
                foreach ($linkItems as $linkItem) {
                    [$type, $id] = array_pad(explode(':', (string) $linkItem, 2), 2, '');
                    if ($type === '') {
                        continue;
                    }
                    createObjectLink($pdo, 'email', $draftId, (string) $type, (int) $id, $linkTeamId, $linkUserId);
                }
            }
            $logActionKey = $isScheduleAction ? 'email_scheduled_updated' : 'email_draft_updated';
            $logActionLabel = $isScheduleAction ? 'Scheduled email updated' : 'Updated draft';
            logAction($userId, $logActionKey, sprintf('%s %d in mailbox %d', $logActionLabel, $draftId, $mailboxId));
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO email_messages
                 (mailbox_id, team_id, user_id, conversation_id, folder, subject, body, body_html, from_name, from_email, to_emails, cc_emails, bcc_emails, created_by, created_at, scheduled_at)
                 VALUES
                 (:mailbox_id, :team_id, :user_id, :conversation_id, "drafts", :subject, :body, :body_html, :from_name, :from_email, :to_emails, :cc_emails, :bcc_emails, :created_by, NOW(), :scheduled_at)'
            );
            $stmt->execute([
                ':mailbox_id' => $mailbox['id'],
                ':team_id' => $mailbox['team_id'] ?? null,
                ':user_id' => $mailbox['user_id'] ?? null,
                ':conversation_id' => $conversationId > 0 ? $conversationId : null,
                ':subject' => $subject !== '' ? $subject : null,
                ':body' => $body !== '' ? strip_tags($body) : null,
                ':body_html' => $body !== '' ? $body : null,
                ':from_name' => $fromName !== '' ? $fromName : null,
                ':from_email' => $fromEmail !== '' ? $fromEmail : null,
                ':to_emails' => $toEmails !== '' ? $toEmails : null,
                ':cc_emails' => $ccEmails !== '' ? $ccEmails : null,
                ':bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
                ':created_by' => $userId,
                ':scheduled_at' => $isScheduleAction ? $scheduledAt : null
            ]);
            $draftId = (int) $pdo->lastInsertId();
            if ($draftId > 0 && $linkItems) {
                foreach ($linkItems as $linkItem) {
                    [$type, $id] = array_pad(explode(':', (string) $linkItem, 2), 2, '');
                    if ($type === '') {
                        continue;
                    }
                    createObjectLink($pdo, 'email', $draftId, (string) $type, (int) $id, $linkTeamId, $linkUserId);
                }
            }
            $logActionKey = $isScheduleAction ? 'email_scheduled_saved' : 'email_draft_saved';
            $logActionLabel = $isScheduleAction ? 'Scheduled email saved' : 'Saved draft';
            logAction($userId, $logActionKey, sprintf('%s in mailbox %d', $logActionLabel, $mailboxId));
        }
        $redirectParams['notice'] = $isScheduleAction ? 'scheduled' : 'draft_saved';
        $redirectParams['folder'] = 'drafts';
        header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    if ($toEmails === '') {
        $redirectParams['notice'] = 'send_failed';
        header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    $sent = sendEmailViaMailbox($pdo, $mailbox, [
        'to_emails' => $toEmails,
        'cc_emails' => $ccEmails !== '' ? $ccEmails : null,
        'bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
        'subject' => $subject !== '' ? $subject : null,
        'body' => $body !== '' ? $body : null,
        'from_email' => $fromEmail,
        'from_name' => $fromName
    ]);

    if (!$sent) {
        $redirectParams['notice'] = 'send_failed';
        header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
        exit;
    }

    if ($conversationId <= 0) {
        $teamScopeId = !empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : null;
        $fallbackUserId = !empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : null;
        $conversationId = $teamScopeId
            ? findConversationForEmail(
                $pdo,
                $mailbox,
                getMailboxPrimaryEmail($mailbox),
                $toEmails,
                $subject,
                date('Y-m-d H:i:s'),
                $teamScopeId,
                null
            )
            : findConversationForEmail(
                $pdo,
                $mailbox,
                getMailboxPrimaryEmail($mailbox),
                $toEmails,
                $subject,
                date('Y-m-d H:i:s'),
                null,
                $fallbackUserId
            );

        if ($conversationId <= 0 && $startNewConversation) {
            $conversationId = ensureConversationForEmail(
                $pdo,
                $mailbox,
                getMailboxPrimaryEmail($mailbox),
                $toEmails,
                $subject,
                true,
                date('Y-m-d H:i:s'),
                $teamScopeId,
                $fallbackUserId
            );
        }
    }

    $messageTeamId = $mailbox['team_id'] ?? null;
    $messageUserId = $mailbox['user_id'] ?? null;

    if ($conversationId > 0 && $messageTeamId !== null) {
        $conversation = ensureConversationAccess($pdo, $conversationId, $userId);
        if ($conversation) {
            $messageTeamId = $conversation['team_id'] ?? $messageTeamId;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO email_messages
         (mailbox_id, team_id, user_id, folder, subject, body, body_html, from_name, from_email, to_emails, cc_emails, bcc_emails, created_by, sent_at, created_at, conversation_id)
         VALUES
         (:mailbox_id, :team_id, :user_id, "sent", :subject, :body, :body_html, :from_name, :from_email, :to_emails, :cc_emails, :bcc_emails, :created_by, NOW(), NOW(), :conversation_id)'
    );
    $stmt->execute([
        ':mailbox_id' => $mailbox['id'],
        ':team_id' => $messageTeamId,
        ':user_id' => $messageUserId,
        ':subject' => $subject !== '' ? $subject : null,
        ':body' => $body !== '' ? strip_tags($body) : null,
        ':body_html' => $body !== '' ? $body : null,
        ':from_name' => $fromName !== '' ? $fromName : null,
        ':from_email' => $fromEmail !== '' ? $fromEmail : null,
        ':to_emails' => $toEmails,
        ':cc_emails' => $ccEmails !== '' ? $ccEmails : null,
        ':bcc_emails' => $bccEmails !== '' ? $bccEmails : null,
        ':created_by' => $userId,
        ':conversation_id' => $conversationId
    ]);
    $sentId = (int) $pdo->lastInsertId();
    if ($sentId > 0 && $linkItems) {
        foreach ($linkItems as $linkItem) {
            [$type, $id] = array_pad(explode(':', (string) $linkItem, 2), 2, '');
            if ($type === '') {
                continue;
            }
            createObjectLink($pdo, 'email', $sentId, (string) $type, (int) $id, $linkTeamId, $linkUserId);
        }
    }
    $redirectParams['folder'] = 'sent';
    logAction($userId, 'email_sent', sprintf('Sent email via mailbox %d', $mailboxId));
    $redirectParams['notice'] = 'sent';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
} catch (Throwable $error) {
    logAction($userId, 'email_send_error', $error->getMessage());
    $redirectParams['notice'] = 'send_failed';
    header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
    exit;
}
