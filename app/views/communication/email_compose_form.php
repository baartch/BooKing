<?php
?>
<form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/email/send.php">
  <?php renderCsrfField(); ?>
  <?php if (!empty($composeConversationId)): ?>
    <input type="hidden" name="conversation_id" value="<?php echo (int) $composeConversationId; ?>">
  <?php endif; ?>

  <?php if (isset($teamMailboxes) && count($teamMailboxes) > 1): ?>
    <div class="field">
      <label for="compose_mailbox_id" class="label">From mailbox</label>
      <div class="control">
        <div class="select is-fullwidth">
          <select id="compose_mailbox_id" name="mailbox_id">
            <?php foreach ($teamMailboxes as $mailbox): ?>
              <?php
                $displayLabel = trim((string) ($mailbox['display_name'] ?? ''));
                $nameLabel = $displayLabel !== '' ? $displayLabel : ($mailbox['name'] ?? '');
                $label = $mailbox['user_id']
                    ? 'Personal · ' . $nameLabel
                    : (($mailbox['team_name'] ?? 'Team') . ' · ' . $nameLabel);
              ?>
              <option value="<?php echo (int) $mailbox['id']; ?>" <?php echo (int) $selectedMailbox['id'] === (int) $mailbox['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  <?php else: ?>
    <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
  <?php endif; ?>

  <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
  <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
  <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
  <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
  <input type="hidden" name="tab" value="email">
  <input type="hidden" name="draft_id" value="<?php echo $message && ($message['folder'] ?? '') === 'drafts' ? (int) $message['id'] : ''; ?>">

  <div class="field">
    <div class="control has-icons-left has-icons-right">
      <div class="dropdown is-fullwidth email-recipient-dropdown" data-email-lookup data-lookup-url="<?php echo BASE_PATH; ?>/app/controllers/email/email_recipient_lookup.php">
        <div class="dropdown-trigger">
          <input
            class="input"
            type="text"
            id="email_to"
            name="to_emails"
            placeholder="To"
            value="<?php echo htmlspecialchars($composeValues['to_emails']); ?>"
            required
            data-email-input
          >
          <span class="icon is-small is-left">
            <i class="fas fa-envelope"></i>
          </span>
          <span class="icon is-small is-right is-hidden" data-email-icon>
            <i class="fas fa-exclamation-triangle"></i>
          </span>
        </div>
        <div class="dropdown-menu is-hidden" role="menu">
          <div class="dropdown-content"></div>
        </div>
      </div>
    </div>
    <p class="help is-danger is-hidden" data-email-help>This email is invalid</p>
  </div>
  <div class="field">
    <div class="control has-icons-left has-icons-right">
      <div class="dropdown is-fullwidth email-recipient-dropdown" data-email-lookup data-lookup-url="<?php echo BASE_PATH; ?>/app/controllers/email/email_recipient_lookup.php">
        <div class="dropdown-trigger">
          <input
            class="input"
            type="text"
            id="email_cc"
            name="cc_emails"
            placeholder="Cc"
            value="<?php echo htmlspecialchars($composeValues['cc_emails']); ?>"
            data-email-input
          >
          <span class="icon is-small is-left">
            <i class="fas fa-envelope"></i>
          </span>
          <span class="icon is-small is-right is-hidden" data-email-icon>
            <i class="fas fa-exclamation-triangle"></i>
          </span>
        </div>
        <div class="dropdown-menu is-hidden" role="menu">
          <div class="dropdown-content"></div>
        </div>
      </div>
    </div>
    <p class="help is-danger is-hidden" data-email-help>This email is invalid</p>
  </div>
  <div class="field">
    <div class="control has-icons-left has-icons-right">
      <div class="dropdown is-fullwidth email-recipient-dropdown" data-email-lookup data-lookup-url="<?php echo BASE_PATH; ?>/app/controllers/email/email_recipient_lookup.php">
        <div class="dropdown-trigger">
          <input
            class="input"
            type="text"
            id="email_bcc"
            name="bcc_emails"
            placeholder="Bcc"
            value="<?php echo htmlspecialchars($composeValues['bcc_emails']); ?>"
            data-email-input
          >
          <span class="icon is-small is-left">
            <i class="fas fa-envelope"></i>
          </span>
          <span class="icon is-small is-right is-hidden" data-email-icon>
            <i class="fas fa-exclamation-triangle"></i>
          </span>
        </div>
        <div class="dropdown-menu is-hidden" role="menu">
          <div class="dropdown-content"></div>
        </div>
      </div>
    </div>
    <p class="help is-danger is-hidden" data-email-help>This email is invalid</p>
  </div>
  <div class="field">
    <div class="control">
      <input type="text" id="email_subject" name="subject" class="input" placeholder="Subject" value="<?php echo htmlspecialchars($composeValues['subject']); ?>">
    </div>
  </div>
  <div class="field">
    <div class="control">
      <label class="checkbox">
        <input type="checkbox" name="start_new_conversation" value="1">
        Start a new conversation
      </label>
    </div>
    <p class="is-size-7 email-link-metadata is-hidden" data-email-links>
      Links: <span data-email-links-list></span>
    </p>
    <div data-email-link-inputs></div>
  </div>
  <div class="field">
    <label for="email_body" class="label">Body</label>
    <div class="control">
      <textarea id="email_body" name="body" class="textarea" rows="10"><?php echo htmlspecialchars($composeValues['body']); ?></textarea>
    </div>
  </div>
  <div class="field">
    <div class="control">
      <div class="buttons has-addons">
        <button type="submit" class="button is-primary" name="action" value="send_email">Send</button>
        <button type="submit" class="button" name="action" value="save_draft">Save Draft</button>
        <a href="<?php echo htmlspecialchars($composeCancelUrl ?? ($baseEmailUrl . '?' . http_build_query($baseQuery))); ?>" class="button">Cancel</a>
      </div>
    </div>
  </div>
</form>
