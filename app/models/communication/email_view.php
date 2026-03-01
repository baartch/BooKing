<?php
require_once __DIR__ . '/email_helpers.php';
require_once __DIR__ . '/../core/link_helpers.php';

$errors = [];
$notice = '';
$messages = [];
$message = null;
$attachments = [];
$folderCounts = [];
$templates = [];
$teamMailboxes = [];
$mailboxIndicators = [];
$selectedMailbox = null;
$pdo = null;
$userId = (int) ($currentUser['user_id'] ?? 0);

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
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = EMAIL_PAGE_SIZE_DEFAULT;
$composeMode = ($_GET['compose'] ?? '') === '1';
$replyId = (int) ($_GET['reply'] ?? 0);
$forwardId = (int) ($_GET['forward'] ?? 0);
$templateId = (int) ($_GET['template_id'] ?? 0);
$composeConversationId = null;

$noticeKey = (string) ($_GET['notice'] ?? '');
if ($noticeKey === 'sent') {
    $notice = 'Email sent successfully.';
} elseif ($noticeKey === 'draft_saved') {
    $notice = 'Draft saved successfully.';
} elseif ($noticeKey === 'deleted') {
    $notice = 'Email deleted.';
} elseif ($noticeKey === 'send_failed') {
    $notice = 'Email could not be sent. Saved as draft.';
} elseif ($noticeKey === 'recipient_required') {
    $notice = 'Please fill at least one recipient field: To, Cc, or Bcc.';
} elseif ($noticeKey === 'scheduled') {
    $notice = 'Email scheduled and saved to drafts.';
}

try {
    $pdo = getDatabaseConnection();
    $teamMailboxes = fetchAccessibleMailboxes($pdo, $userId);
} catch (Throwable $error) {
    $errors[] = 'Failed to load mailboxes.';
    logAction($userId, 'email_mailbox_load_error', $error->getMessage());
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

if ($pdo && $teamMailboxes) {
    try {
        $mailboxIds = array_values(array_filter(array_map(
            static fn(array $mailbox): int => (int) ($mailbox['id'] ?? 0),
            $teamMailboxes
        ), static fn(int $id): bool => $id > 0));

        if ($mailboxIds) {
            $placeholders = implode(',', array_fill(0, count($mailboxIds), '?'));
            $stmt = $pdo->prepare(
                'SELECT mailbox_id,
                        SUM(CASE WHEN folder = "inbox" AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count,
                        SUM(CASE WHEN folder = "inbox" AND is_read = 0 AND received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS new_count
                 FROM email_messages
                 WHERE mailbox_id IN (' . $placeholders . ')
                 GROUP BY mailbox_id'
            );
            $stmt->execute($mailboxIds);
            foreach ($stmt->fetchAll() as $row) {
                $mailboxId = (int) ($row['mailbox_id'] ?? 0);
                if ($mailboxId <= 0) {
                    continue;
                }
                $mailboxIndicators[$mailboxId] = [
                    'unread_count' => (int) ($row['unread_count'] ?? 0),
                    'new_count' => (int) ($row['new_count'] ?? 0)
                ];
            }
        }
    } catch (Throwable $error) {
        logAction($userId, 'email_mailbox_indicator_error', $error->getMessage());
    }
}

$composeValues = [
    'to_emails' => '',
    'cc_emails' => '',
    'bcc_emails' => '',
    'subject' => '',
    'body' => '',
    'schedule_date' => '',
    'schedule_time' => '',
    'start_new_conversation' => false,
    'link_items' => [],
    'conversation_id' => null,
    'conversation_label' => ''
];

$prefillTo = trim((string) ($_GET['to'] ?? ''));
$prefillCc = trim((string) ($_GET['cc'] ?? ''));
$prefillBcc = trim((string) ($_GET['bcc'] ?? ''));

if ($prefillTo !== '') {
    $composeValues['to_emails'] = normalizeEmailList($prefillTo);

    if ($pdo) {
        try {
            $teamScopeId = !empty($selectedMailbox['team_id']) ? (int) $selectedMailbox['team_id'] : null;
            $recipientEmails = preg_split('/[;,]+/', $composeValues['to_emails']) ?: [];
            $recipientEmails = array_values(array_unique(array_filter(array_map(
                static fn(string $email): string => strtolower(trim($email)),
                $recipientEmails
            ), static fn(string $email): bool => $email !== '')));

            $linkItemsByKey = [];

            $contactIds = [];
            $venueIds = [];

            foreach ($recipientEmails as $recipientEmail) {
                $contactIds = array_merge($contactIds, findContactIdsByEmail($pdo, $recipientEmail, $teamScopeId));
                $venueIds = array_merge($venueIds, findVenueIdsByEmail($pdo, $recipientEmail));
            }

            foreach (array_values(array_unique(array_map('intval', $contactIds))) as $contactId) {
                if ($contactId <= 0) {
                    continue;
                }
                $key = 'contact:' . $contactId;
                $linkItemsByKey[$key] = [
                    'type' => 'contact',
                    'id' => $contactId,
                    'label' => 'Contact #' . $contactId
                ];
            }

            foreach (array_values(array_unique(array_map('intval', $venueIds))) as $venueId) {
                if ($venueId <= 0) {
                    continue;
                }
                $key = 'venue:' . $venueId;
                $linkItemsByKey[$key] = [
                    'type' => 'venue',
                    'id' => $venueId,
                    'label' => 'Venue #' . $venueId
                ];
            }

            if (!empty($linkItemsByKey)) {
                $labelsByKey = [];

                $uniqueContactIds = array_values(array_unique(array_map('intval', $contactIds)));
                if (!empty($uniqueContactIds)) {
                    $placeholders = implode(',', array_fill(0, count($uniqueContactIds), '?'));
                    $sql = 'SELECT id, firstname, surname, email FROM contacts WHERE id IN (' . $placeholders . ')';
                    $params = $uniqueContactIds;
                    if ($teamScopeId !== null) {
                        $sql .= ' AND team_id = ?';
                        $params[] = $teamScopeId;
                    }
                    $contactStmt = $pdo->prepare($sql);
                    $contactStmt->execute($params);
                    foreach ($contactStmt->fetchAll() as $contactRow) {
                        $name = trim((string) ($contactRow['firstname'] ?? '') . ' ' . (string) ($contactRow['surname'] ?? ''));
                        $email = trim((string) ($contactRow['email'] ?? ''));
                        $label = $name !== '' ? $name : $email;
                        if ($label === '') {
                            $label = 'Contact #' . (int) ($contactRow['id'] ?? 0);
                        }
                        $labelsByKey['contact:' . (int) ($contactRow['id'] ?? 0)] = $label;
                    }
                }

                $uniqueVenueIds = array_values(array_unique(array_map('intval', $venueIds)));
                if (!empty($uniqueVenueIds)) {
                    $placeholders = implode(',', array_fill(0, count($uniqueVenueIds), '?'));
                    $venueStmt = $pdo->prepare(
                        'SELECT id, name, contact_email FROM venues WHERE id IN (' . $placeholders . ')'
                    );
                    $venueStmt->execute($uniqueVenueIds);
                    foreach ($venueStmt->fetchAll() as $venueRow) {
                        $name = trim((string) ($venueRow['name'] ?? ''));
                        $email = trim((string) ($venueRow['contact_email'] ?? ''));
                        $label = $name !== '' ? $name : $email;
                        if ($label === '') {
                            $label = 'Venue #' . (int) ($venueRow['id'] ?? 0);
                        }
                        $labelsByKey['venue:' . (int) ($venueRow['id'] ?? 0)] = $label;
                    }
                }

                $composeValues['link_items'] = array_map(
                    static fn(array $link): array => [
                        'type' => (string) ($link['type'] ?? ''),
                        'id' => (int) ($link['id'] ?? 0),
                        'label' => (string) ($labelsByKey[(string) ($link['type'] ?? '') . ':' . (int) ($link['id'] ?? 0)] ?? ($link['label'] ?? ''))
                    ],
                    array_values($linkItemsByKey)
                );
            }
        } catch (Throwable $error) {
            logAction($userId, 'email_prefill_links_resolve_error', $error->getMessage());
        }
    }

    $composeMode = true;
}

$messageLinks = [];

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

        if ($message && !empty($message['conversation_id'])) {
            $conversation = ensureConversationAccess($pdo, (int) $message['conversation_id'], $userId);
            if (!$conversation) {
                $message = null;
            }
        }

        if ($message) {
            $messageFolder = (string) ($message['folder'] ?? '');
            if ($messageFolder !== '' && array_key_exists($messageFolder, $folderOptions)) {
                $folder = $messageFolder;
            }
        }

        if ($message && $message['folder'] === 'drafts') {
            $composeValues['to_emails'] = (string) ($message['to_emails'] ?? '');
            $composeValues['cc_emails'] = (string) ($message['cc_emails'] ?? '');
            $composeValues['bcc_emails'] = (string) ($message['bcc_emails'] ?? '');
            $composeValues['subject'] = (string) ($message['subject'] ?? '');
            $composeValues['body'] = (string) ($message['body_html'] ?? '');
            if ($composeValues['body'] === '') {
                $composeValues['body'] = (string) ($message['body'] ?? '');
            }
            $scheduledAt = (string) ($message['scheduled_at'] ?? '');
            if ($scheduledAt !== '') {
                $composeValues['schedule_date'] = date('Y-m-d', strtotime($scheduledAt));
                $composeValues['schedule_time'] = date('H:i', strtotime($scheduledAt));
            }
            $composeValues['start_new_conversation'] = !empty($message['start_new_conversation']);
            $composeValues['conversation_id'] = !empty($message['conversation_id'])
                ? (int) $message['conversation_id']
                : null;
            if (!empty($composeValues['conversation_id']) && isset($pdo)) {
                try {
                    $convStmt = $pdo->prepare('SELECT subject FROM email_conversations WHERE id = :id LIMIT 1');
                    $convStmt->execute([':id' => (int) $composeValues['conversation_id']]);
                    $convRow = $convStmt->fetch();
                    $composeValues['conversation_label'] = trim((string) ($convRow['subject'] ?? ''));
                    if ($composeValues['conversation_label'] === '') {
                        $composeValues['conversation_label'] = 'Conversation #' . (int) $composeValues['conversation_id'];
                    }
                } catch (Throwable $error) {
                    $composeValues['conversation_label'] = 'Conversation #' . (int) $composeValues['conversation_id'];
                }
            }
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

            try {
                $linkTeamId = !empty($selectedMailbox['team_id'])
                    ? (int) $selectedMailbox['team_id']
                    : (!empty($message['team_id']) ? (int) $message['team_id'] : null);
                $linkUserId = !empty($selectedMailbox['user_id'])
                    ? (int) $selectedMailbox['user_id']
                    : null;

                $messageLinks = fetchLinkedObjects(
                    $pdo,
                    'email',
                    (int) $message['id'],
                    $linkTeamId,
                    $linkUserId
                );
            } catch (Throwable $error) {
                logAction($userId, 'email_links_load_error', $error->getMessage());
            }

            if ($message && ($message['folder'] ?? '') === 'drafts' && !empty($messageLinks)) {
                $composeValues['link_items'] = array_map(
                    static fn(array $link): array => [
                        'type' => (string) ($link['type'] ?? ''),
                        'id' => (int) ($link['id'] ?? 0),
                        'label' => (string) ($link['label'] ?? '')
                    ],
                    $messageLinks
                );
            }
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

        if ($prefillMessage && !empty($prefillMessage['conversation_id'])) {
            $conversation = ensureConversationAccess($pdo, (int) $prefillMessage['conversation_id'], $userId);
            if (!$conversation) {
                $prefillMessage = null;
            }
        }
        if ($prefillMessage) {
            $originalSubject = (string) ($prefillMessage['subject'] ?? '');
            $subjectPrefix = $replyId > 0 ? 'Re: ' : 'Fwd: ';
            $subject = $originalSubject;
            if ($subject !== '') {
                $trimmedSubject = ltrim($subject);
                $hasReplyPrefix = preg_match('/^re\s*:/i', $trimmedSubject) === 1;
                $hasForwardPrefix = preg_match('/^fwd?\s*:/i', $trimmedSubject) === 1;
                $shouldPrefix = $replyId > 0 ? !$hasReplyPrefix : !$hasForwardPrefix;
                if ($shouldPrefix) {
                    $subject = $subjectPrefix . $subject;
                }
            }
            $composeValues['subject'] = $subject;
            if ($replyId > 0) {
                $replyTarget = '';
                $prefillFolder = (string) ($prefillMessage['folder'] ?? '');
                if ($prefillFolder === 'sent') {
                    $replyTarget = normalizeEmailList((string) ($prefillMessage['to_emails'] ?? ''));
                }
                if ($replyTarget === '') {
                    $replyTarget = normalizeEmailList((string) ($prefillMessage['from_email'] ?? ''));
                }
                $composeValues['to_emails'] = $replyTarget;
            }
            $composeConversationId = !empty($prefillMessage['conversation_id'])
                ? (int) $prefillMessage['conversation_id']
                : null;
            $composeValues['conversation_id'] = $composeConversationId;
            if (!empty($composeConversationId) && isset($pdo)) {
                try {
                    $convStmt = $pdo->prepare('SELECT subject FROM email_conversations WHERE id = :id LIMIT 1');
                    $convStmt->execute([':id' => (int) $composeConversationId]);
                    $convRow = $convStmt->fetch();
                    $composeValues['conversation_label'] = trim((string) ($convRow['subject'] ?? ''));
                    if ($composeValues['conversation_label'] === '') {
                        $composeValues['conversation_label'] = 'Conversation #' . (int) $composeConversationId;
                    }
                } catch (Throwable $error) {
                    $composeValues['conversation_label'] = 'Conversation #' . (int) $composeConversationId;
                }
            }

            try {
                $prefillLinkTeamId = !empty($selectedMailbox['team_id'])
                    ? (int) $selectedMailbox['team_id']
                    : (!empty($prefillMessage['team_id']) ? (int) $prefillMessage['team_id'] : null);
                $prefillLinkUserId = !empty($selectedMailbox['user_id'])
                    ? (int) $selectedMailbox['user_id']
                    : (!empty($prefillMessage['user_id']) ? (int) $prefillMessage['user_id'] : null);

                $prefillLinks = fetchLinkedObjects(
                    $pdo,
                    'email',
                    (int) $prefillMessage['id'],
                    $prefillLinkTeamId,
                    $prefillLinkUserId
                );

                if (!empty($prefillLinks)) {
                    $composeValues['link_items'] = array_map(
                        static fn(array $link): array => [
                            'type' => (string) ($link['type'] ?? ''),
                            'id' => (int) ($link['id'] ?? 0),
                            'label' => (string) ($link['label'] ?? '')
                        ],
                        $prefillLinks
                    );
                }
            } catch (Throwable $error) {
                logAction($userId, 'email_prefill_links_load_error', $error->getMessage());
            }
            $originalBody = (string) ($prefillMessage['body_html'] ?? '');
            if ($originalBody === '') {
                $originalBody = (string) ($prefillMessage['body'] ?? '');
            }
            $isHtml = $originalBody !== '' && $originalBody !== strip_tags($originalBody);
            $quotedBody = $isHtml ? $originalBody : nl2br(htmlspecialchars($originalBody));

            $fromName  = trim((string) ($prefillMessage['from_name'] ?? ''));
            $fromEmail = trim((string) ($prefillMessage['from_email'] ?? ''));
            $senderName = $fromName !== '' ? $fromName : $fromEmail;
            $senderLabel = htmlspecialchars($senderName);

            $receivedAt = trim((string) ($prefillMessage['received_at'] ?? ''));
            $sentAt = trim((string) ($prefillMessage['sent_at'] ?? ''));
            $createdAt = trim((string) ($prefillMessage['created_at'] ?? ''));
            $quoteSourceDate = $receivedAt !== '' ? $receivedAt : ($sentAt !== '' ? $sentAt : $createdAt);
            $quoteDate = '';
            if ($quoteSourceDate !== '') {
                try {
                    $quoteDate = (new DateTime($quoteSourceDate))->format('d.m.Y H:i');
                } catch (Throwable $error) {
                    $quoteDate = $quoteSourceDate;
                }
            }
            $quoteHeader = $quoteDate !== ''
                ? '<div class="moz-cite-prefix">On ' . htmlspecialchars($quoteDate) . ', ' . $senderLabel . ' wrote:<br></div>'
                : '<div class="moz-cite-prefix">' . $senderLabel . ' wrote:<br></div>';
            $messageId = trim((string) ($prefillMessage['message_id'] ?? ''));
            $citeAttribute = $messageId !== '' ? ' cite="' . htmlspecialchars($messageId) . '"' : '';
            $quoteBlock = '<blockquote type="cite"' . $citeAttribute . '>' . $quotedBody . '</blockquote>';

            if ($replyId > 0) {
                $composeValues['body'] = '<p><br></p>'
                    . $quoteHeader
                    . $quoteBlock;
            } elseif ($forwardId > 0) {
                $composeValues['body'] = '<p><br></p>'
                    . $quoteHeader
                    . $quoteBlock;
            }
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
            $mailboxTeamId = (int) ($selectedMailbox['team_id'] ?? 0);
            if ($template && ($mailboxTeamId === 0 || (int) $template['team_id'] === $mailboxTeamId)) {
                $composeValues['subject'] = (string) ($template['subject'] ?? '');
                $composeValues['body'] = (string) ($template['body'] ?? '');
                $composeMode = true;
            }
        }

    } catch (Throwable $error) {
        $errors[] = 'Failed to load templates.';
        logAction($userId, 'email_template_load_error', $error->getMessage());
    }
}

if ($composeMode) {
    if ($prefillTo !== '') {
        $composeValues['to_emails'] = normalizeEmailList($prefillTo);
    }
    if ($prefillCc !== '') {
        $composeValues['cc_emails'] = normalizeEmailList($prefillCc);
    }
    if ($prefillBcc !== '') {
        $composeValues['bcc_emails'] = normalizeEmailList($prefillBcc);
    }
}

$totalMessages = 0;
$totalPages = 1;
if ($pdo && $selectedMailbox) {
    try {
        $filterSql = '';
        $scopeSql = 'mailbox_id = :mailbox_id';
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

        $countSql = 'SELECT COUNT(*) FROM email_messages
             WHERE ' . $scopeSql . ' AND folder = :folder ' . $filterSql;
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
                    received_at, sent_at, scheduled_at, created_at
             FROM email_messages
             WHERE ' . $scopeSql . ' AND folder = :folder ' . $filterSql .
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

        $countStmt = $pdo->prepare(
            'SELECT folder, COUNT(*) AS total
             FROM email_messages
             WHERE ' . $scopeSql . '
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
$baseEmailUrl = BASE_PATH . '/app/controllers/communication/index.php';

$baseQuery = [
    'tab' => 'email',
    'mailbox_id' => $selectedMailbox['id'] ?? null,
    'folder' => $folder,
    'sort' => $sortKey,
    'filter' => $filter,
    'page' => $page
];
$baseQuery = array_filter($baseQuery, static fn($value) => $value !== null && $value !== '');
$mailboxCount = count($teamMailboxes);
