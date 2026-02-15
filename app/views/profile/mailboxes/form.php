<?php
/**
 * Variables expected:
 * - bool $isEdit
 * - array $formValues
 * - array $allowedEncryptions
 * - string $cancelUrl
 * - ?array $editMailbox
 */
$isEdit = (bool) ($isEdit ?? false);
$cancelUrl = $cancelUrl ?? (BASE_PATH . '/app/controllers/profile/index.php?tab=mailboxes');
$editMailbox = $editMailbox ?? null;
$useSameCredentials = !empty($formValues['imap_username'])
    ? ($formValues['smtp_username'] ?? '') === $formValues['imap_username']
    : true;
?>
<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <h2 class="title is-5"><?php echo $isEdit ? 'Edit Mailbox' : 'Add Mailbox'; ?></h2>
    </div>
  </div>

  <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/profile/mailbox_form.php">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $isEdit ? 'update_mailbox' : 'create_mailbox'; ?>">
    <?php if ($isEdit && $editMailbox): ?>
      <input type="hidden" name="mailbox_id" value="<?php echo (int) $editMailbox['id']; ?>">
    <?php endif; ?>

    <div class="table-container">
      <table class="table is-fullwidth">
        <tbody>
          <tr>
            <th>Mailbox Name</th>
            <td>
              <div class="control">
                <input type="text" id="mailbox_name" name="name" class="input" value="<?php echo htmlspecialchars((string) ($formValues['name'] ?? '')); ?>" required>
              </div>
            </td>
          </tr>
          <tr>
            <th>Display Name</th>
            <td>
              <div class="control">
                <input type="text" id="mailbox_display_name" name="display_name" class="input" value="<?php echo htmlspecialchars((string) ($formValues['display_name'] ?? '')); ?>">
              </div>
              <p class="help">Shown as the sender name when you send mail.</p>
            </td>
          </tr>
          <tr>
            <th colspan="2">IMAP Settings</th>
          </tr>
          <tr>
            <th>IMAP Host</th>
            <td>
              <div class="control">
                <input type="text" id="imap_host" name="imap_host" class="input" value="<?php echo htmlspecialchars((string) ($formValues['imap_host'] ?? '')); ?>" required>
              </div>
            </td>
          </tr>
          <tr>
            <th>IMAP Port</th>
            <td>
              <div class="control">
                <input type="number" id="imap_port" name="imap_port" class="input" value="<?php echo (int) ($formValues['imap_port'] ?? 993); ?>" min="1" max="65535" required>
              </div>
            </td>
          </tr>
          <tr>
            <th>IMAP Username</th>
            <td>
              <div class="control">
                <input type="text" id="imap_username" name="imap_username" class="input" value="<?php echo htmlspecialchars((string) ($formValues['imap_username'] ?? '')); ?>" data-imap-username required>
              </div>
            </td>
          </tr>
          <tr>
            <th>IMAP Password</th>
            <td>
              <div class="control">
                <input type="password" id="imap_password" name="imap_password" class="input" autocomplete="new-password" data-imap-password <?php echo $editMailbox ? '' : 'required'; ?>>
              </div>
              <?php if ($editMailbox): ?>
                <p class="help">Leave blank to keep the current password.</p>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th>IMAP Encryption</th>
            <td>
              <div class="control">
                <div class="select is-fullwidth">
                  <select id="imap_encryption" name="imap_encryption" required>
                    <?php foreach ($allowedEncryptions as $option): ?>
                      <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($formValues['imap_encryption'] ?? 'ssl') === $option ? 'selected' : ''; ?>>
                        <?php echo strtoupper($option); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </td>
          </tr>
          <tr>
            <th>Delete After Retrieve</th>
            <td>
              <label class="checkbox">
                <input type="checkbox" name="delete_after_retrieve" value="1" <?php echo !empty($formValues['delete_after_retrieve']) ? 'checked' : ''; ?>>
                Delete messages on server after retrieving
              </label>
            </td>
          </tr>
          <tr>
            <th>Store Sent on Server</th>
            <td>
              <label class="checkbox">
                <input type="checkbox" name="store_sent_on_server" value="1" <?php echo !empty($formValues['store_sent_on_server']) ? 'checked' : ''; ?>>
                Store sent mail on server in Sent
              </label>
            </td>
          </tr>
          <tr>
            <th>Use Same Credentials</th>
            <td>
              <label class="checkbox">
                <input type="checkbox" name="use_same_credentials" value="1" data-mailbox-same-credentials <?php echo $useSameCredentials ? 'checked' : ''; ?>>
                Use the same username and password for SMTP and IMAP
              </label>
            </td>
          </tr>
          <tr>
            <th>Auto Start Conversation</th>
            <td>
              <label class="checkbox">
                <input type="checkbox" name="auto_start_conversation_inbound" value="1" <?php echo !empty($formValues['auto_start_conversation_inbound']) ? 'checked' : ''; ?>>
                Automatically start a new conversation on inbound
              </label>
            </td>
          </tr>
          <tr>
            <th colspan="2">SMTP Settings</th>
          </tr>
          <tr>
            <th>SMTP Host</th>
            <td>
              <div class="control">
                <input type="text" id="smtp_host" name="smtp_host" class="input" value="<?php echo htmlspecialchars((string) ($formValues['smtp_host'] ?? '')); ?>" required>
              </div>
            </td>
          </tr>
          <tr>
            <th>SMTP Port</th>
            <td>
              <div class="control">
                <input type="number" id="smtp_port" name="smtp_port" class="input" value="<?php echo (int) ($formValues['smtp_port'] ?? 587); ?>" min="1" max="65535" required>
              </div>
            </td>
          </tr>
          <tr data-smtp-credentials>
            <th>SMTP Username</th>
            <td>
              <div class="control">
                <input type="text" id="smtp_username" name="smtp_username" class="input" value="<?php echo htmlspecialchars((string) ($formValues['smtp_username'] ?? '')); ?>" data-smtp-username required>
              </div>
            </td>
          </tr>
          <tr data-smtp-credentials>
            <th>SMTP Password</th>
            <td>
              <div class="control">
                <input type="password" id="smtp_password" name="smtp_password" class="input" autocomplete="new-password" data-smtp-password <?php echo $editMailbox ? '' : 'required'; ?>>
              </div>
              <?php if ($editMailbox): ?>
                <p class="help">Leave blank to keep the current password.</p>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th>SMTP Encryption</th>
            <td>
              <div class="control">
                <div class="select is-fullwidth">
                  <select id="smtp_encryption" name="smtp_encryption" required>
                    <?php foreach ($allowedEncryptions as $option): ?>
                      <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($formValues['smtp_encryption'] ?? 'tls') === $option ? 'selected' : ''; ?>>
                        <?php echo strtoupper($option); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="buttons">
      <button type="submit" class="button is-primary"><?php echo $isEdit ? 'Update Mailbox' : 'Create Mailbox'; ?></button>
      <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="button">Cancel</a>
    </div>
  </form>
</div>
