<?php
/** @var string $activeTab */
?>
<div class="tab-panel <?php echo $activeTab === 'mailboxes' ? '' : 'is-hidden'; ?>" data-tab-panel="mailboxes" role="tabpanel">
  <div class="level mb-4">
    <div class="level-left">
      <h2 class="title is-4">Mailboxes</h2>
    </div>
    <div class="level-right">
      <a href="<?php echo BASE_PATH; ?>/app/controllers/profile/mailbox_form.php" class="button is-primary">Add Mailbox</a>
    </div>
  </div>

  <div class="box">
    <h3 class="title is-5">Configured Mailboxes</h3>
    <?php if (!$personalMailboxes): ?>
      <p>No mailboxes configured yet.</p>
    <?php else: ?>
      <div class="table-container">
        <table class="table is-fullwidth is-striped is-hoverable">
          <thead>
            <tr>
              <th>Name</th>
              <th>Display Name</th>
              <th>IMAP Host</th>
              <th>IMAP Port</th>
              <th>IMAP User</th>
              <th>IMAP Encryption</th>
              <th>SMTP Host</th>
              <th>SMTP Port</th>
              <th>SMTP User</th>
              <th>SMTP Encryption</th>
              <th>Delete After Retrieve</th>
              <th>Store Sent on Server</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($personalMailboxes as $mailbox): ?>
              <tr>
                <td><?php echo htmlspecialchars($mailbox['name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['display_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['imap_host'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['imap_port'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['imap_username'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars(strtoupper($mailbox['imap_encryption'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars($mailbox['smtp_host'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['smtp_port'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mailbox['smtp_username'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars(strtoupper($mailbox['smtp_encryption'] ?? '')); ?></td>
                <td><?php echo !empty($mailbox['delete_after_retrieve']) ? 'Yes' : 'No'; ?></td>
                <td><?php echo !empty($mailbox['store_sent_on_server']) ? 'Yes' : 'No'; ?></td>
                <td>
                  <div class="buttons are-small">
                    <a href="<?php echo BASE_PATH; ?>/app/controllers/profile/mailbox_form.php?edit_mailbox_id=<?php echo (int) $mailbox['id']; ?>" class="button" aria-label="Edit mailbox" title="Edit mailbox">
                      <span class="icon"><i class="fa-solid fa-pen"></i></span>
                    </a>
                    <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/profile/index.php?tab=mailboxes" onsubmit="return confirm('Delete this mailbox?');">
                      <?php renderCsrfField(); ?>
                      <input type="hidden" name="action" value="delete_mailbox">
                      <input type="hidden" name="mailbox_id" value="<?php echo (int) $mailbox['id']; ?>">
                      <button type="submit" class="button" aria-label="Delete mailbox" title="Delete mailbox">
                        <span class="icon"><i class="fa-solid fa-trash"></i></span>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
