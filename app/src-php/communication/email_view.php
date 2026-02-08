<?php
require_once __DIR__ . '/email_helpers.php';
require_once __DIR__ . '/email_parents.php';

$errors = [];
$notice = '';
$messages = [];
$message = null;
$attachments = [];
$folderCounts = [];
$templates = [];
$teamMailboxes = [];
$selectedMailbox = null;
$pdo = null;
$userId = (int) ($currentUser['user_id'] ?? 0);
$messageParentLabels = [];

$folderOptions = getEmailFolderOptions();
$sortOptions = getEmailSortOptions();
$folder = (string) ($_GET['folder'] ?? 'inbox');
if (!array_key_exists($folder, $folderOptions)) {
    $folder = 'inbox';
}

$sortKey = (string) ($_GET['sort'] ?? 'received_desc');
if (!array_key_exists($sortKey, $sortOptions) || !in_array($sortKey, ['received_desc', 'received_asc'], true)) {
    $sortKey = 'received_desc';
}

$filter = trim((string) ($_GET['filter'] ?? ''));
$parentFilter = trim((string) ($_GET['parent'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = EMAIL_PAGE_SIZE_DEFAULT;
$composeMode = ($_GET['compose'] ?? '') === '1';
$replyId = (int) ($_GET['reply'] ?? 0);
$forwardId = (int) ($_GET['forward'] ?? 0);
$templateId = (int) ($_GET['template_id'] ?? 0);

$noticeKey = (string) ($_GET['notice'] ?? '');
if ($noticeKey === 'sent') {
    $notice = 'Email sent successfully.';
} elseif ($noticeKey === 'draft_saved') {
    $notice = 'Draft saved successfully.';
} elseif ($noticeKey === 'deleted') {
    $notice = 'Email deleted.';
} elseif ($noticeKey === 'send_failed') {
    $notice = 'Email could not be sent. Saved as draft.';
}

try {
    $pdo = getDatabaseConnection();
    $teamMailboxes = fetchTeamMailboxes($pdo, $userId);
} catch (Throwable $error) {
    $errors[] = 'Failed to load mailboxes.';
    logAction($userId, 'email_mailbox_load_error', $error->getMessage());
}

$parentFilterIds = ['contacts' => [], 'venues' => []];
if ($pdo && $parentFilter !== '') {
    try {
        $parentFilterIds = findParentCandidatesForFilter($pdo, $parentFilter);
    } catch (Throwable $error) {
        logAction($userId, 'email_parent_filter_error', $error->getMessage());
        $parentFilterIds = ['contacts' => [], 'venues' => []];
    }
}

$selectedMailboxId = (int) ($_GET['mailbox_id'] ?? 0);
if ($selectedMailboxId <= 0 && $teamMailboxes) {
    $selectedMailboxId = (int) ($teamMailboxes[0]['id'] ?? 0);
}

if ($pdo && $selectedMailboxId > 0) {
    try {
        $selectedMailbox = ensureMailboxAccess($pdo, $selectedMailboxId, $userId);
        if (!$selectedMailbox) {
            $errors[] = 'Mailbox access denied.';
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load mailbox.';
        logAction($userId, 'email_mailbox_access_error', $error->getMessage());
    }
}

$quotaUsed = 0;
$quotaTotal = EMAIL_ATTACHMENT_QUOTA_DEFAULT;
if ($pdo && $selectedMailbox) {
    try {
        $quotaUsed = fetchMailboxQuotaUsage($pdo, (int) $selectedMailbox['id']);
        $quotaTotal = (int) ($selectedMailbox['attachment_quota_bytes'] ?? EMAIL_ATTACHMENT_QUOTA_DEFAULT);
    } catch (Throwable $error) {
        $errors[] = 'Failed to load attachment quota.';
        logAction($userId, 'email_quota_load_error', $error->getMessage());
    }
}

$composeValues = [
    'to_emails' => '',
    'cc_emails' => '',
    'bcc_emails' => '',
    'subject' => '',
    'body' => '',
    'parent_type' => '',
    'parent_id' => '',
    'parent_label' => ''
];

$selectedMessageId = (int) ($_GET['message_id'] ?? 0);
if ($pdo && $selectedMailbox && $selectedMessageId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT em.*
             FROM email_messages em
             WHERE em.id = :id AND em.mailbox_id = :mailbox_id
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $selectedMessageId,
            ':mailbox_id' => $selectedMailbox['id']
        ]);
        $message = $stmt->fetch();

        if ($message && $message['folder'] === 'drafts') {
            $composeValues['to_emails'] = (string) ($message['to_emails'] ?? '');
            $composeValues['cc_emails'] = (string) ($message['cc_emails'] ?? '');
            $composeValues['bcc_emails'] = (string) ($message['bcc_emails'] ?? '');
            $composeValues['subject'] = (string) ($message['subject'] ?? '');
            $composeValues['body'] = (string) ($message['body'] ?? '');
            $composeValues['parent_type'] = (string) ($message['parent_type'] ?? '');
            $composeValues['parent_id'] = (string) ($message['parent_id'] ?? '');
            $composeValues['parent_label'] = $composeValues['parent_type'] && $composeValues['parent_id']
                ? (string) (resolveParentLabel($pdo, $composeValues['parent_type'], (int) $composeValues['parent_id']) ?? '')
                : '';
            $composeMode = true;
        } elseif ($message && !(bool) $message['is_read']) {
            $updateStmt = $pdo->prepare('UPDATE email_messages SET is_read = 1 WHERE id = :id');
            $updateStmt->execute([':id' => $selectedMessageId]);
        }

        if ($message) {
            $attachmentsStmt = $pdo->prepare(
                'SELECT * FROM email_attachments WHERE email_id = :email_id ORDER BY id'
            );
            $attachmentsStmt->execute([':email_id' => $selectedMessageId]);
            $attachments = $attachmentsStmt->fetchAll();
            $message['parent_label'] = resolveParentLabel(
                $pdo,
                $message['parent_type'] ?? null,
                isset($message['parent_id']) ? (int) $message['parent_id'] : null
            );
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load email message.';
        logAction($userId, 'email_message_load_error', $error->getMessage());
    }
}

$prefillMessageId = $replyId > 0 ? $replyId : $forwardId;
if ($pdo && $selectedMailbox && $prefillMessageId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT em.*
             FROM email_messages em
             WHERE em.id = :id AND em.mailbox_id = :mailbox_id
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $prefillMessageId,
            ':mailbox_id' => $selectedMailbox['id']
        ]);
        $prefillMessage = $stmt->fetch();
        if ($prefillMessage) {
            $originalSubject = (string) ($prefillMessage['subject'] ?? '');
            $subjectPrefix = $replyId > 0 ? 'Re: ' : 'Fwd: ';
            $subject = $originalSubject;
            if ($subject !== '' && stripos($subject, $subjectPrefix) !== 0) {
                $subject = $subjectPrefix . $subject;
            }
            $composeValues['subject'] = $subject;
            if ($replyId > 0) {
                $composeValues['to_emails'] = (string) ($prefillMessage['from_email'] ?? '');
            }
            if (!empty($prefillMessage['parent_type']) && !empty($prefillMessage['parent_id'])) {
                $composeValues['parent_type'] = (string) $prefillMessage['parent_type'];
                $composeValues['parent_id'] = (string) $prefillMessage['parent_id'];
                $composeValues['parent_label'] = (string) (resolveParentLabel(
                    $pdo,
                    $prefillMessage['parent_type'],
                    (int) $prefillMessage['parent_id']
                ) ?? '');
            }
            $bodyLines = [];
            if ($replyId > 0) {
                $bodyLines[] = '';
                $bodyLines[] = sprintf('On %s, %s wrote:', $prefillMessage['received_at'] ?? '', $prefillMessage['from_name'] ?? $prefillMessage['from_email'] ?? '');
                $bodyLines[] = (string) ($prefillMessage['body'] ?? '');
            } elseif ($forwardId > 0) {
                $bodyLines[] = '';
                $bodyLines[] = '---- Forwarded message ----';
                $bodyLines[] = sprintf('From: %s', $prefillMessage['from_email'] ?? '');
                $bodyLines[] = sprintf('Date: %s', $prefillMessage['received_at'] ?? '');
                $bodyLines[] = sprintf('Subject: %s', $prefillMessage['subject'] ?? '');
                $bodyLines[] = sprintf('To: %s', $prefillMessage['to_emails'] ?? '');
                $bodyLines[] = '';
                $bodyLines[] = (string) ($prefillMessage['body'] ?? '');
            }
            $composeValues['body'] = trim(implode("\n", $bodyLines));
            $composeMode = true;
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to prepare reply.';
        logAction($userId, 'email_reply_load_error', $error->getMessage());
    }
}

if ($pdo && $selectedMailbox) {
    try {
        $templates = fetchTeamTemplates($pdo, $userId, (int) $selectedMailbox['team_id']);
        if ($templateId > 0) {
            $template = ensureTemplateAccess($pdo, $templateId, $userId);
            if ($template && (int) $template['team_id'] === (int) $selectedMailbox['team_id']) {
                $composeValues['subject'] = $composeValues['subject'] !== '' ? $composeValues['subject'] : (string) ($template['subject'] ?? '');
                $composeValues['body'] = $composeValues['body'] !== '' ? $composeValues['body'] : (string) ($template['body'] ?? '');
                $composeMode = true;
            }
        }

        if ($composeMode && $composeValues['parent_type'] === '' && $composeValues['parent_id'] === '') {
            $singleRecipient = parseSingleEmail($composeValues['to_emails']);
            if ($singleRecipient) {
                $match = findParentByEmail($pdo, $singleRecipient);
                if ($match) {
                    $composeValues['parent_type'] = $match['type'];
                    $composeValues['parent_id'] = (string) $match['id'];
                    $composeValues['parent_label'] = (string) ($match['label'] ?? '');
                }
            }
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load templates.';
        logAction($userId, 'email_template_load_error', $error->getMessage());
    }
}

$totalMessages = 0;
$totalPages = 1;
if ($pdo && $selectedMailbox) {
    try {
        $filterSql = '';
        $params = [
            ':mailbox_id' => $selectedMailbox['id'],
            ':folder' => $folder
        ];
        if ($filter !== '') {
            $filterSql = 'AND (subject LIKE :filter OR ';
            if ($folder === 'inbox') {
                $filterSql .= 'from_name LIKE :filter OR from_email LIKE :filter';
            } else {
                $filterSql .= 'to_emails LIKE :filter';
            }
            $filterSql .= ')';
            $params[':filter'] = '%' . $filter . '%';
        }

        $parentFilterSql = '';
        if ($parentFilter !== '') {
            $clauses = [];
            if ($parentFilterIds['contacts']) {
                $contactKeys = [];
                foreach ($parentFilterIds['contacts'] as $index => $id) {
                    $key = ':parent_contact_' . $index;
                    $contactKeys[] = $key;
                    $params[$key] = $id;
                }
                if ($contactKeys) {
                    $clauses[] = '(parent_type = "contact" AND parent_id IN (' . implode(',', $contactKeys) . '))';
                }
            }
            if ($parentFilterIds['venues']) {
                $venueKeys = [];
                foreach ($parentFilterIds['venues'] as $index => $id) {
                    $key = ':parent_venue_' . $index;
                    $venueKeys[] = $key;
                    $params[$key] = $id;
                }
                if ($venueKeys) {
                    $clauses[] = '(parent_type = "venue" AND parent_id IN (' . implode(',', $venueKeys) . '))';
                }
            }
            if ($clauses) {
                $parentFilterSql = 'AND (' . implode(' OR ', $clauses) . ')';
            } else {
                $parentFilterSql = 'AND 1 = 0';
            }
        }

        $countSql = 'SELECT COUNT(*) FROM email_messages
             WHERE mailbox_id = :mailbox_id AND folder = :folder ' . $filterSql . ' ' . $parentFilterSql;
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalMessages = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalMessages / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;

        $sortColumn = $sortOptions[$sortKey]['column'];
        $sortDirection = $sortOptions[$sortKey]['direction'];
        if ($sortColumn === 'received_at' && $folder !== 'inbox') {
            $sortColumn = $folder === 'sent' ? 'sent_at' : 'created_at';
        }

        $listSql = 'SELECT id, subject, from_name, from_email, to_emails, is_read,
                    received_at, sent_at, created_at, parent_type, parent_id
             FROM email_messages
             WHERE mailbox_id = :mailbox_id AND folder = :folder ' . $filterSql . ' ' . $parentFilterSql .
            ' ORDER BY ' . $sortColumn . ' ' . $sortDirection .
            ' LIMIT :limit OFFSET :offset';

        $listStmt = $pdo->prepare($listSql);
        foreach ($params as $key => $value) {
            $listStmt->bindValue($key, $value);
        }
        $listStmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();
        $messages = $listStmt->fetchAll();
        $messageParentLabels = resolveParentLabelsForMessages($pdo, $messages);

        $countStmt = $pdo->prepare(
            'SELECT folder, COUNT(*) AS total
             FROM email_messages
             WHERE mailbox_id = :mailbox_id
             GROUP BY folder'
        );
        $countStmt->execute([':mailbox_id' => $selectedMailbox['id']]);
        $counts = $countStmt->fetchAll();
        foreach ($counts as $row) {
            $folderCounts[$row['folder']] = (int) $row['total'];
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load emails.';
        logAction($userId, 'email_list_error', $error->getMessage());
    }
}

$quotaPercent = calculateQuotaPercent($quotaUsed, $quotaTotal);
$baseEmailUrl = BASE_PATH . '/app/pages/communication/index.php';

$baseQuery = [
    'tab' => 'email',
    'mailbox_id' => $selectedMailbox['id'] ?? null,
    'folder' => $folder,
    'sort' => $sortKey,
    'filter' => $filter,
    'parent' => $parentFilter,
    'page' => $page
];
$baseQuery = array_filter($baseQuery, static fn($value) => $value !== null && $value !== '');
$mailboxCount = count($teamMailboxes);
