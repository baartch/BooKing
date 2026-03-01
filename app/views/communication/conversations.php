<?php
require_once __DIR__ . '/../../models/core/link_helpers.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';

$errors = [];
$conversations = [];
$conversationMessages = [];
$pdo = null;
$userId = (int) ($currentUser['user_id'] ?? 0);
$folderOptions = getEmailFolderOptions();

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $error) {
    $errors[] = 'Failed to load conversations.';
    logAction($userId, 'conversation_db_error', $error->getMessage());
}

$conversationId = (int) ($_GET['conversation_id'] ?? 0);

if ($pdo) {
    try {
        $stmt = $pdo->prepare(
            'SELECT c.*, COUNT(em.id) AS message_count,
                    (SELECT em2.folder
                     FROM email_messages em2
                     WHERE em2.conversation_id = c.id
                     ORDER BY COALESCE(em2.received_at, em2.sent_at, em2.created_at) DESC, em2.id DESC
                     LIMIT 1) AS last_message_folder
             FROM email_conversations c
             LEFT JOIN team_members tm ON tm.team_id = c.team_id AND tm.user_id = :viewer_user_id_join
             LEFT JOIN email_messages em ON em.conversation_id = c.id
             WHERE (c.team_id IS NOT NULL AND tm.user_id = :viewer_user_id_team)
                OR (c.user_id IS NOT NULL AND c.user_id = :viewer_user_id_owner)
             GROUP BY c.id
             ORDER BY c.last_activity_at DESC, c.id DESC'
        );
        $stmt->execute([
            ':viewer_user_id_join' => $userId,
            ':viewer_user_id_team' => $userId,
            ':viewer_user_id_owner' => $userId
        ]);
        $conversations = $stmt->fetchAll();

    } catch (Throwable $error) {
        $errors[] = 'Failed to load conversations.';
        logAction($userId, 'conversation_list_error', $error->getMessage());
    }
}

if ($pdo && $conversationId > 0) {
    try {
        $conversationScope = ensureConversationAccess($pdo, $conversationId, $userId);
        if (!$conversationScope) {
            $errors[] = 'Conversation access denied.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT em.id, em.mailbox_id, em.subject, em.body, em.body_html, em.from_name, em.from_email, em.to_emails, em.folder,
                        em.is_read, em.received_at, em.sent_at, em.created_at,
                        em.team_id, em.user_id, u.username AS user_name
                 FROM email_messages em
                 LEFT JOIN users u ON u.id = em.user_id
                 WHERE em.conversation_id = :conversation_id
                 ORDER BY COALESCE(em.received_at, em.sent_at, em.created_at) DESC'
            );
            $stmt->execute([
                ':conversation_id' => $conversationId
            ]);
            $conversationMessages = $stmt->fetchAll();
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load conversation emails.';
        logAction($userId, 'conversation_messages_error', $error->getMessage());
    }
}

$baseUrl = BASE_PATH . '/app/controllers/communication/index.php';
$baseQuery = [
    'tab' => 'conversations'
];
$baseQuery = array_filter($baseQuery, static fn($value) => $value !== null && $value !== '');
$cooldownSeconds = 14 * 24 * 60 * 60;
?>
<div class="columns is-variable is-4">
  <section class="column is-6">
    <div class="box">
      <div class="level mb-3">
        <div class="level-left">
          <h2 class="title is-5">Conversations</h2>
        </div>
      </div>


      <?php foreach ($errors as $error): ?>
        <div class="notification"><?php echo htmlspecialchars($error); ?></div>
      <?php endforeach; ?>

      <?php
        $openConversations = [];
        $closedConversations = [];

        foreach ($conversations as $conversation) {
            if (!empty($conversation['is_closed'])) {
                $closedConversations[] = $conversation;
            } else {
                $openConversations[] = $conversation;
            }
        }

        $incomingConversations = [];
        $outgoingConversations = [];

        foreach ($openConversations as $conversation) {
            $lastFolder = (string) ($conversation['last_message_folder'] ?? '');
            if ($lastFolder === 'inbox') {
                $incomingConversations[] = $conversation;
            } else {
                $outgoingConversations[] = $conversation;
            }
        }

        $sortByHeat = static function (array $left, array $right): int {
            $leftActivity = $left['last_activity_at'] ?? $left['created_at'] ?? null;
            $rightActivity = $right['last_activity_at'] ?? $right['created_at'] ?? null;
            $leftTime = $leftActivity ? strtotime((string) $leftActivity) : 0;
            $rightTime = $rightActivity ? strtotime((string) $rightActivity) : 0;

            return $leftTime <=> $rightTime;
        };

        usort($incomingConversations, $sortByHeat);
        usort($outgoingConversations, $sortByHeat);
        usort($closedConversations, $sortByHeat);

        $orderedOpenConversations = array_merge($incomingConversations, $outgoingConversations);
      ?>
        <div class="menu">
          <ul class="menu-list">
            <?php if (!$conversations): ?>
              <li><span>No conversations found.</span></li>
            <?php elseif (!$orderedOpenConversations): ?>
              <li><span>No open conversations.</span></li>
            <?php else: ?>
              <?php foreach ($orderedOpenConversations as $conversation): ?>
                <?php
                  $conversationLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, [
                      'conversation_id' => $conversation['id']
                  ]));
                  $lastActivity = $conversation['last_activity_at'] ?? $conversation['created_at'] ?? null;
                  $lastActivityTime = $lastActivity ? strtotime((string) $lastActivity) : null;
                  $elapsedSeconds = $lastActivityTime ? max(0, time() - $lastActivityTime) : $cooldownSeconds;
                  $cooldownPercent = $cooldownSeconds > 0
                      ? min(100, (int) round(($elapsedSeconds / $cooldownSeconds) * 100))
                      : 100;
                  $heatPercent = max(0, 100 - $cooldownPercent);
                  $colorStep = (int) round($cooldownPercent / 5) * 5;
                  $colorStep = max(0, min(100, $colorStep));

                  $participantLabel = $conversation['participant_key'] === 'unknown'
                      ? 'Unknown participants'
                      : str_replace('|', ' · ', $conversation['participant_key']);
                  $ageDays = $lastActivityTime ? (int) floor((time() - $lastActivityTime) / 86400) : null;
                  $activityLabel = $ageDays !== null ? ($ageDays . 'd') : '—';
                  $messageCount = (int) ($conversation['message_count'] ?? 0);
                  $messageLabel = $messageCount === 1 ? 'mail' : 'mails';
                  $lastFolder = (string) ($conversation['last_message_folder'] ?? '');
                  $arrowIcon = $lastFolder === 'inbox' ? 'fa-arrow-right' : 'fa-arrow-left';
                  $scopeIcon = !empty($conversation['team_id']) ? 'fa-users' : 'fa-user';
                ?>
                <li class="mb-3">
                  <div class="is-flex is-justify-content-space-between">
                    <a href="<?php echo htmlspecialchars($conversationLink); ?>" class="is-flex-grow-1 <?php echo (int) $conversation['id'] === $conversationId ? 'is-active' : ''; ?>">
                      <div class="is-flex is-justify-content-space-between">
                        <div>
                          <div class="has-text-weight-semibold">
                            <span class="icon is-small"><i class="fa-solid <?php echo $scopeIcon; ?>"></i></span>
                            <span class="icon is-small"><i class="fa-solid <?php echo $arrowIcon; ?>"></i></span>
                            <?php echo (int) $conversation['id']; ?>: <?php echo htmlspecialchars($conversation['subject'] ?? '(No subject)'); ?>
                          </div>
                          <div class="is-size-7"><?php echo htmlspecialchars($participantLabel); ?></div>
                        </div>
                        <div class="is-flex is-flex-direction-column is-align-items-flex-end is-size-7">
                          <div><?php echo htmlspecialchars($activityLabel); ?></div>
                          <div class="is-flex is-align-items-center mt-1">
                            <span class="tag is-small"><?php echo $messageCount; ?> <?php echo $messageLabel; ?></span>
                            <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/conversation_close.php" class="ml-2 is-flex is-align-items-center">
                              <?php renderCsrfField(); ?>
                              <input type="hidden" name="conversation_id" value="<?php echo (int) $conversation['id']; ?>">
                              <button type="submit" class="button is-small" aria-label="Close conversation" title="Close conversation">
                                <span class="icon"><i class="fa-solid fa-circle-xmark"></i></span>
                              </button>
                            </form>
                          </div>
                        </div>
                      </div>
                      <div class="mt-2">
                        <progress class="progress is-small is-cooldown-step-<?php echo $colorStep; ?>" value="<?php echo $heatPercent; ?>" max="100"></progress>
                      </div>
                    </a>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

        <?php if ($closedConversations): ?>
          <div class="mt-4">
            <h3 class="title is-6">Closed</h3>
            <div class="menu">
              <ul class="menu-list">
                <?php foreach ($closedConversations as $conversation): ?>
                  <?php
                    $conversationLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, [
                        'conversation_id' => $conversation['id']
                    ]));
                    $lastActivity = $conversation['last_activity_at'] ?? $conversation['created_at'] ?? null;
                    $lastActivityTime = $lastActivity ? strtotime((string) $lastActivity) : null;
                    $participantLabel = $conversation['participant_key'] === 'unknown'
                        ? 'Unknown participants'
                        : str_replace('|', ' · ', $conversation['participant_key']);
                    $ageDays = $lastActivityTime ? (int) floor((time() - $lastActivityTime) / 86400) : null;
                    $activityLabel = $ageDays !== null ? ($ageDays . 'd') : '—';
                    $messageCount = (int) ($conversation['message_count'] ?? 0);
                    $messageLabel = $messageCount === 1 ? 'mail' : 'mails';
                    $lastFolder = (string) ($conversation['last_message_folder'] ?? '');
                    $arrowIcon = $lastFolder === 'inbox' ? 'fa-arrow-right' : 'fa-arrow-left';
                    $scopeIcon = !empty($conversation['team_id']) ? 'fa-users' : 'fa-user';
                  ?>
                  <li class="mb-3">
                    <div class="is-flex is-justify-content-space-between">
                      <a href="<?php echo htmlspecialchars($conversationLink); ?>" class="is-flex-grow-1 <?php echo (int) $conversation['id'] === $conversationId ? 'is-active' : ''; ?>">
                        <div class="is-flex is-justify-content-space-between">
                          <div>
                            <div class="has-text-weight-semibold">
                              <span class="icon is-small"><i class="fa-solid <?php echo $scopeIcon; ?>"></i></span>
                              <span class="icon is-small"><i class="fa-solid <?php echo $arrowIcon; ?>"></i></span>
                              <?php echo (int) $conversation['id']; ?>: <?php echo htmlspecialchars($conversation['subject'] ?? '(No subject)'); ?>
                              <span class="tag is-small ml-2">Closed</span>
                            </div>
                            <div class="is-size-7"><?php echo htmlspecialchars($participantLabel); ?></div>
                          </div>
                          <div class="is-flex is-flex-direction-column is-align-items-flex-end is-size-7">
                            <div><?php echo htmlspecialchars($activityLabel); ?></div>
                            <div class="is-flex is-align-items-center mt-1">
                              <span class="tag is-small"><?php echo $messageCount; ?> <?php echo $messageLabel; ?></span>
                              <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/conversation_reopen.php" class="ml-2 is-flex is-align-items-center">
                                <?php renderCsrfField(); ?>
                                <input type="hidden" name="conversation_id" value="<?php echo (int) $conversation['id']; ?>">
                                <button type="submit" class="button is-small" aria-label="Reopen conversation" title="Reopen conversation">
                                  <span class="icon"><i class="fa-solid fa-rotate-left"></i></span>
                                </button>
                              </form>
                              <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/conversation_delete.php" class="ml-2 is-flex is-align-items-center" onsubmit="return confirm('Delete this conversation?');">
                                <?php renderCsrfField(); ?>
                                <input type="hidden" name="conversation_id" value="<?php echo (int) $conversation['id']; ?>">
                                <button type="submit" class="button is-small" aria-label="Delete conversation" title="Delete conversation">
                                  <span class="icon"><i class="fa-solid fa-trash"></i></span>
                                </button>
                              </form>
                            </div>
                          </div>
                        </div>
                      </a>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endif; ?>
    </div>
  </section>

  <section class="column is-6">
    <div class="box">
      <div class="level mb-3">
        <div class="level-left">
          <h2 class="title is-5">Conversation emails</h2>
        </div>
      </div>

      <?php if (!$conversationId): ?>
        <p>Select a conversation to view emails.</p>
      <?php elseif (!$conversationMessages): ?>
        <p>No emails found for this conversation.</p>
      <?php else: ?>
        <div class="content">
          <?php foreach ($conversationMessages as $messageItem): ?>
            <?php
              $messageFolder = $messageItem['folder'] ?? 'inbox';
              $displayName = $messageFolder === 'inbox'
                  ? trim(($messageItem['from_name'] ?? '') !== '' ? $messageItem['from_name'] : ($messageItem['from_email'] ?? 'Unknown'))
                  : trim((string) ($messageItem['to_emails'] ?? ''));
              $dateValue = $messageFolder === 'inbox'
                  ? ($messageItem['received_at'] ?? $messageItem['created_at'])
                  : ($messageItem['sent_at'] ?? $messageItem['created_at']);
              $dateLabel = $dateValue ? date('Y-m-d H:i', strtotime((string) $dateValue)) : '';
              $folderLabel = $folderOptions[$messageFolder] ?? ucfirst($messageFolder);
              $isUnread = empty($messageItem['is_read']) && $messageFolder === 'inbox';
              $messageBody = (string) ($messageItem['body_html'] ?? '');
              if ($messageBody === '') {
                  $messageBody = (string) ($messageItem['body'] ?? '');
              }
              $isPersonalMessage = empty($messageItem['team_id']) && !empty($messageItem['user_id']);
              $isPersonalPlaceholder = $isPersonalMessage
                  && (int) $messageItem['user_id'] !== (int) $userId;
              $placeholderLabel = $isPersonalPlaceholder
                  ? sprintf('Personal reply from %s (hidden)', $messageItem['user_name'] ?? 'user')
                  : '';
              $selectedMailbox = [
                  'id' => (int) ($messageItem['mailbox_id'] ?? 0),
                  'team_id' => $messageItem['team_id'] ?? null,
                  'user_id' => $messageItem['user_id'] ?? null,
                  'display_name' => ''
              ];
              $messageLinks = [];
              if ($pdo && !empty($messageItem['id'])) {
                  try {
                      $linkTeamId = !empty($messageItem['team_id']) ? (int) $messageItem['team_id'] : null;
                      $linkUserId = !empty($messageItem['user_id']) ? (int) $messageItem['user_id'] : null;
                      $messageLinks = fetchLinkedObjects(
                          $pdo,
                          'email',
                          (int) $messageItem['id'],
                          $linkTeamId,
                          $linkUserId
                      );
                  } catch (Throwable $error) {
                      logAction($userId, 'conversation_message_links_error', $error->getMessage());
                  }
              }
              $baseEmailUrl = BASE_PATH . '/app/controllers/communication/index.php';
              $baseQuery = [
                  'tab' => 'conversations',
                  'conversation_id' => $conversationId
              ];
              $messageFolderForLink = (string) ($messageItem['folder'] ?? 'inbox');
              $emailDetailSubjectUrl = BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query([
                  'tab' => 'email',
                  'mailbox_id' => (int) ($messageItem['mailbox_id'] ?? 0),
                  'folder' => $messageFolderForLink,
                  'message_id' => (int) ($messageItem['id'] ?? 0)
              ]);
              $emailDetailWrapperTag = 'article';
              $emailDetailWrapperClass = 'box mb-4';
              $emailDetailIncludeLinkEditor = false;
              $emailDetailShowActions = false;
              $emailDetailShowLinks = false;
              $emailDetailShowAttachments = false;
              $composeMode = false;
              $templates = [];
              $attachments = [];
              $sortKey = 'received_desc';
              $filter = '';
              $page = 1;
              $message = $messageItem;
              require __DIR__ . '/email_detail.php';
            ?>
            <div class="is-flex is-align-items-center is-size-7 mt-2">
              <span><?php echo htmlspecialchars($dateLabel); ?></span>
              <?php if ($isUnread): ?>
                <span class="tag is-small ml-2">Unread</span>
              <?php endif; ?>
              <span class="ml-2">· <?php echo htmlspecialchars($folderLabel); ?></span>
              <span class="ml-2 has-text-weight-semibold">
                <?php echo htmlspecialchars($isPersonalPlaceholder ? $placeholderLabel : $displayName); ?>
              </span>
              <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/conversation_rm_message.php" class="ml-2" onsubmit="return confirm('Remove this email from the conversation?');">
                <?php renderCsrfField(); ?>
                <input type="hidden" name="conversation_id" value="<?php echo (int) $conversationId; ?>">
                <input type="hidden" name="message_id" value="<?php echo (int) $messageItem['id']; ?>">
                <button type="submit" class="button is-small" aria-label="Remove from conversation" title="Remove from conversation">
                  <span class="icon"><i class="fa-solid fa-link-slash"></i></span>
                </button>
              </form>
            </div>
            <?php $message = null; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
