<?php
?>
<section class="column email-column email-detail-column">
  <?php if (!$selectedMailbox): ?>
    <div class="email-detail-empty">
      <span class="icon is-large has-text-grey"><i class="fa-solid fa-envelope fa-2x"></i></span>
      <p class="has-text-grey mt-2">Pick a mailbox to view details.</p>
    </div>
  <?php elseif ($composeMode): ?>
    <div class="level mb-3">
      <div class="level-left">
        <h2 class="title is-5">Compose</h2>
      </div>
    </div>

    <?php $composeCancelUrl = $baseEmailUrl . '?' . http_build_query($baseQuery); ?>

    <?php if ($templates): ?>
      <form method="GET" action="<?php echo htmlspecialchars($baseEmailUrl); ?>" class="field has-addons mb-4">
        <input type="hidden" name="tab" value="email">
        <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
        <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
        <input type="hidden" name="compose" value="1">
        <input type="hidden" name="page" value="1">
        <div class="control is-expanded">
          <div class="select is-fullwidth">
            <select name="template_id">
              <option value="">Select template</option>
              <?php foreach ($templates as $template): ?>
                <option value="<?php echo (int) $template['id']; ?>" <?php echo $templateId === (int) $template['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($template['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="control">
          <button type="submit" class="button">Use Template</button>
        </div>
      </form>
    <?php endif; ?>

    <?php require __DIR__ . '/email_compose_form.php'; ?>
  <?php elseif ($message): ?>
    <?php
      $senderName = trim((string) ($message['from_name'] ?? ''));
      $senderEmail = trim((string) ($message['from_email'] ?? ''));
      $senderLabel = $senderEmail !== '' ? $senderEmail : $senderName;
      if ($senderName !== '' && $senderEmail !== '') {
          $senderLabel = $senderName . ' <' . $senderEmail . '>';
      } elseif ($senderName !== '') {
          $senderLabel = $senderName;
      }

      $recipientLabel = trim((string) ($message['to_emails'] ?? ''));

      $dateLabel = $message['received_at'] ?? $message['sent_at'] ?? $message['created_at'] ?? '';

      $linkItems = [];
      if (!empty($messageLinks)) {
          foreach ($messageLinks as $link) {
              if ($link['type'] === 'contact') {
                  $url = BASE_PATH . '/app/pages/communication/index.php?tab=contacts&q=' . urlencode($link['label']);
                  $linkItems[] = ['label' => $link['label'], 'url' => $url];
              } elseif ($link['type'] === 'venue') {
                  $url = BASE_PATH . '/app/pages/venues/index.php?q=' . urlencode($link['label']);
                  $linkItems[] = ['label' => $link['label'], 'url' => $url];
              } elseif ($link['type'] === 'email') {
                  $url = $baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, [
                      'message_id' => $link['id']
                  ]));
                  $linkItems[] = ['label' => $link['label'], 'url' => $url];
              }
          }
      }

      $initials = '';
      if ($senderName !== '') {
          $parts = explode(' ', $senderName);
          $initials = strtoupper(substr($parts[0], 0, 1));
          if (count($parts) > 1) {
              $initials .= strtoupper(substr(end($parts), 0, 1));
          }
      } elseif ($senderEmail !== '') {
          $initials = strtoupper(substr($senderEmail, 0, 1));
      }
    ?>

    <!-- Header: Subject + Actions -->
    <div class="email-detail-header">
      <div class="email-detail-subject-row">
        <h2 class="email-detail-subject"><?php echo htmlspecialchars($message['subject'] ?? '(No subject)'); ?></h2>
        <div class="field has-addons email-detail-actions">
          <div class="control">
            <a
              href="<?php echo htmlspecialchars($baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, ['compose' => 1, 'reply' => $message['id']]))); ?>"
              class="button is-small"
              aria-label="Reply"
              title="Reply"
            >
              <span class="icon is-small"><i class="fa-solid fa-reply"></i></span>
            </a>
          </div>

          <div class="control">
            <a
              href="<?php echo htmlspecialchars($baseEmailUrl . '?' . http_build_query(array_merge($baseQuery, ['compose' => 1, 'forward' => $message['id']]))); ?>"
              class="button is-small"
              aria-label="Forward"
              title="Forward"
            >
              <span class="icon is-small"><i class="fa-solid fa-forward"></i></span>
            </a>
          </div>

          <form class="control" method="POST" action="<?php echo BASE_PATH; ?>/app/routes/email/mark_unread.php">
            <?php renderCsrfField(); ?>
            <input type="hidden" name="email_id" value="<?php echo (int) $message['id']; ?>">
            <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
            <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
            <input type="hidden" name="tab" value="email">
            <button type="submit" class="button is-small" aria-label="Mark as unread" title="Mark as unread">
              <span class="icon is-small"><i class="fa-solid fa-envelope"></i></span>
            </button>
          </form>

          <form class="control" method="POST" action="<?php echo BASE_PATH; ?>/app/routes/email/delete.php">
            <?php renderCsrfField(); ?>
            <input type="hidden" name="email_id" value="<?php echo (int) $message['id']; ?>">
            <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
            <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
            <input type="hidden" name="tab" value="email">
            <button type="submit" class="button is-small" aria-label="Move email to trash" title="Move email to trash">
              <span class="icon is-small"><i class="fa-solid fa-trash"></i></span>
            </button>
          </form>
        </div>
      </div>

      <!-- Metadata: Sender / Recipients / Date / Links -->
      <div class="email-detail-meta">
        <div class="email-detail-avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div class="email-detail-meta-info">
          <div class="email-detail-meta-row">
            <span class="email-detail-meta-label">From</span>
            <span class="email-detail-meta-value"><?php echo htmlspecialchars($senderLabel); ?></span>
          </div>
          <?php if ($recipientLabel !== ''): ?>
            <div class="email-detail-meta-row">
              <span class="email-detail-meta-label">To</span>
              <span class="email-detail-meta-value"><?php echo htmlspecialchars($recipientLabel); ?></span>
            </div>
          <?php endif; ?>
          <div class="email-detail-meta-row">
            <span class="email-detail-meta-label">Date</span>
            <span class="email-detail-meta-value"><?php echo htmlspecialchars($dateLabel); ?></span>
          </div>
          <?php if (!empty($linkItems)): ?>
            <div class="email-detail-meta-row">
              <span class="email-detail-meta-label">Links</span>
              <span class="email-detail-meta-value">
                <?php foreach ($linkItems as $index => $link): ?>
                  <a href="<?php echo htmlspecialchars($link['url']); ?>" class="email-detail-link-tag">
                    <?php echo htmlspecialchars($link['label']); ?>
                  </a>
                <?php endforeach; ?>
              </span>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Body -->
    <div class="email-detail-body content">
      <?php
        $messageBody = (string) ($message['body_html'] ?? '');
        if ($messageBody === '') {
            $messageBody = (string) ($message['body'] ?? '');
        }
        if ($messageBody !== '' && $messageBody !== strip_tags($messageBody)) {
            echo $messageBody;
        } else {
            echo nl2br(htmlspecialchars($messageBody));
        }
      ?>
    </div>

    <!-- Attachments -->
    <?php if ($attachments): ?>
      <div class="email-detail-attachments">
        <span class="email-detail-attachments-label">
          <span class="icon is-small"><i class="fa-solid fa-paperclip"></i></span>
          <span><?php echo count($attachments); ?> attachment<?php echo count($attachments) !== 1 ? 's' : ''; ?></span>
        </span>
        <div class="email-detail-attachments-list">
          <?php foreach ($attachments as $attachment): ?>
            <a href="<?php echo BASE_PATH; ?>/app/routes/email/attachment.php?id=<?php echo (int) $attachment['id']; ?>" class="email-detail-attachment-chip">
              <span class="icon is-small"><i class="fa-solid fa-file"></i></span>
              <span><?php echo htmlspecialchars($attachment['filename'] ?? 'Attachment'); ?></span>
              <span class="email-detail-attachment-size"><?php echo htmlspecialchars(formatBytes((int) $attachment['file_size'])); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="email-detail-empty">
      <span class="icon is-large has-text-grey"><i class="fa-solid fa-envelope-open fa-2x"></i></span>
      <p class="has-text-grey mt-2">Select an email to view its details.</p>
    </div>
  <?php endif; ?>
</section>
