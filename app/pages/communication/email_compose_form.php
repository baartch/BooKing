<?php
?>
<form method="POST" action="<?php echo BASE_PATH; ?>/app/routes/email/send.php">
  <?php renderCsrfField(); ?>
  <input type="hidden" name="mailbox_id" value="<?php echo (int) $selectedMailbox['id']; ?>">
  <input type="hidden" name="folder" value="<?php echo htmlspecialchars($folder); ?>">
  <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
  <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
  <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
  <input type="hidden" name="tab" value="email">
  <input type="hidden" name="draft_id" value="<?php echo $message && ($message['folder'] ?? '') === 'drafts' ? (int) $message['id'] : ''; ?>">

  <div class="field" data-parent-lookup data-lookup-mode="recipient" data-lookup-url="<?php echo BASE_PATH; ?>/app/routes/communication/parent_lookup.php">
    <label for="email_to" class="label">To</label>
    <div class="dropdown is-fullwidth">
      <div class="dropdown-trigger control">
        <input type="text" id="email_to" name="to_emails" class="input" value="<?php echo htmlspecialchars($composeValues['to_emails']); ?>" required data-parent-input>
      </div>
    </div>
  </div>
  <div class="field">
    <label for="email_cc" class="label">Cc</label>
    <div class="control">
      <input type="text" id="email_cc" name="cc_emails" class="input" value="<?php echo htmlspecialchars($composeValues['cc_emails']); ?>">
    </div>
  </div>
  <div class="field">
    <label for="email_bcc" class="label">Bcc</label>
    <div class="control">
      <input type="text" id="email_bcc" name="bcc_emails" class="input" value="<?php echo htmlspecialchars($composeValues['bcc_emails']); ?>">
    </div>
  </div>
  <div class="field" data-parent-lookup data-lookup-mode="parent" data-lookup-url="<?php echo BASE_PATH; ?>/app/routes/communication/parent_lookup.php">
    <label for="email_parent" class="label">Parent</label>
    <div class="dropdown is-fullwidth">
      <div class="dropdown-trigger control">
        <input type="text" id="email_parent" name="parent_label" class="input" value="<?php echo htmlspecialchars($composeValues['parent_label'] ?? ''); ?>" placeholder="Search contacts or venues" data-parent-input>
        <input type="hidden" name="parent_type" value="<?php echo htmlspecialchars($composeValues['parent_type'] ?? ''); ?>" data-parent-type>
        <input type="hidden" name="parent_id" value="<?php echo htmlspecialchars($composeValues['parent_id'] ?? ''); ?>" data-parent-id>
      </div>
    </div>
  </div>

  <div class="field">
    <label for="email_subject" class="label">Subject</label>
    <div class="control">
      <input type="text" id="email_subject" name="subject" class="input" value="<?php echo htmlspecialchars($composeValues['subject']); ?>">
    </div>
  </div>
  <div class="field">
    <div class="control">
      <label class="checkbox">
        <input type="checkbox" name="start_new_conversation" value="1">
        Start a new conversation
      </label>
    </div>
  </div>
  <div class="field">
    <label for="email_body" class="label">Body</label>
    <div class="control">
      <textarea id="email_body" name="body" class="textarea" rows="10"><?php echo htmlspecialchars($composeValues['body']); ?></textarea>
    </div>
  </div>
  <div class="buttons">
    <button type="submit" class="button is-primary" name="action" value="send_email">Send</button>
    <button type="submit" class="button" name="action" value="save_draft">Save Draft</button>
  </div>
</form>
