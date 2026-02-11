<?php
/**
 * Expects:
 * - array $formValues
 * - array $allowedEncryptions
 * - array $teams (optional)
 * - bool $showTeamSelect (optional)
 * - array|null $editMailbox (optional)
 */
$teams = $teams ?? [];
$showTeamSelect = $showTeamSelect ?? false;
$editMailbox = $editMailbox ?? null;
$useSameCredentials = !empty($formValues['imap_username'])
    ? ($formValues['smtp_username'] ?? '') === $formValues['imap_username']
    : true;
$showMailboxName = $showMailboxName ?? true;
$requireImap = $requireImap ?? true;
$requireSmtp = $requireSmtp ?? true;
$showImap = $showImap ?? true;
$showOptions = $showOptions ?? true;
?>

<?php if ($showTeamSelect): ?>
  <div class="column is-4">
    <div class="field">
      <label for="team_id" class="label">Team</label>
      <div class="control">
        <div class="select is-fullwidth">
          <select id="team_id" name="team_id" required>
            <option value="">Select a team</option>
            <?php foreach ($teams as $team): ?>
              <option value="<?php echo (int) $team['id']; ?>" <?php echo (int) ($formValues['team_id'] ?? 0) === (int) $team['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($team['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($showMailboxName): ?>
<div class="column is-4">
  <div class="field">
    <label for="mailbox_name" class="label">Mailbox Name</label>
    <div class="control">
      <input type="text" id="mailbox_name" name="name" class="input" value="<?php echo htmlspecialchars((string) ($formValues['name'] ?? '')); ?>" required>
    </div>
  </div>
</div>
<div class="column is-4">
  <div class="field">
    <label for="mailbox_display_name" class="label">Display Name</label>
    <div class="control">
      <input type="text" id="mailbox_display_name" name="display_name" class="input" value="<?php echo htmlspecialchars((string) ($formValues['display_name'] ?? '')); ?>">
    </div>
    <p class="help">Shown as the sender name when you send mail.</p>
  </div>
</div>
<?php else: ?>
  <input type="hidden" name="name" value="<?php echo htmlspecialchars((string) ($formValues['name'] ?? '')); ?>">
  <input type="hidden" name="display_name" value="<?php echo htmlspecialchars((string) ($formValues['display_name'] ?? '')); ?>">
<?php endif; ?>

<?php if ($showImap): ?>
<div class="column is-12">
  <h3 class="title is-5">IMAP Settings</h3>
</div>

<div class="column is-4">
  <div class="field">
    <label for="imap_host" class="label">IMAP Host</label>
    <div class="control">
      <input type="text" id="imap_host" name="imap_host" class="input" value="<?php echo htmlspecialchars((string) ($formValues['imap_host'] ?? '')); ?>" <?php echo $requireImap ? 'required' : ''; ?>>
    </div>
  </div>
</div>

<div class="column is-2">
  <div class="field">
    <label for="imap_port" class="label">IMAP Port</label>
    <div class="control">
      <input type="number" id="imap_port" name="imap_port" class="input" value="<?php echo (int) ($formValues['imap_port'] ?? 993); ?>" min="1" max="65535" <?php echo $requireImap ? 'required' : ''; ?>>
    </div>
  </div>
</div>

<div class="column is-3">
  <div class="field">
    <label for="imap_username" class="label">IMAP Username</label>
    <div class="control">
      <input type="text" id="imap_username" name="imap_username" class="input" value="<?php echo htmlspecialchars((string) ($formValues['imap_username'] ?? '')); ?>" data-imap-username <?php echo $requireImap ? 'required' : ''; ?>>
    </div>
  </div>
</div>

<div class="column is-3">
  <div class="field">
    <label for="imap_password" class="label">IMAP Password</label>
    <div class="control">
      <input type="password" id="imap_password" name="imap_password" class="input" autocomplete="new-password" data-imap-password <?php echo $editMailbox || !$requireImap ? '' : 'required'; ?>>
    </div>
    <?php if ($editMailbox): ?>
      <p class="help">Leave blank to keep the current password.</p>
    <?php endif; ?>
  </div>
</div>

<div class="column is-3">
  <div class="field">
    <label for="imap_encryption" class="label">IMAP Encryption</label>
    <div class="control">
      <div class="select is-fullwidth">
        <select id="imap_encryption" name="imap_encryption" <?php echo $requireImap ? 'required' : ''; ?>>
          <?php foreach ($allowedEncryptions as $option): ?>
            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($formValues['imap_encryption'] ?? 'ssl') === $option ? 'selected' : ''; ?>>
              <?php echo strtoupper($option); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
  <input type="hidden" name="imap_host" value="<?php echo htmlspecialchars((string) ($formValues['imap_host'] ?? '')); ?>">
  <input type="hidden" name="imap_port" value="<?php echo (int) ($formValues['imap_port'] ?? 993); ?>">
  <input type="hidden" name="imap_username" value="<?php echo htmlspecialchars((string) ($formValues['imap_username'] ?? '')); ?>">
  <input type="hidden" name="imap_password" value="">
  <input type="hidden" name="imap_encryption" value="<?php echo htmlspecialchars((string) ($formValues['imap_encryption'] ?? 'ssl')); ?>">
<?php endif; ?>

<?php if ($showOptions): ?>
<div class="column is-12">
  <div class="field">
    <label class="checkbox">
      <input type="checkbox" name="delete_after_retrieve" value="1" <?php echo !empty($formValues['delete_after_retrieve']) ? 'checked' : ''; ?>>
      Delete messages on server after retrieving
    </label>
  </div>
  <div class="field">
    <label class="checkbox">
      <input type="checkbox" name="store_sent_on_server" value="1" <?php echo !empty($formValues['store_sent_on_server']) ? 'checked' : ''; ?>>
      Store sent mail on server in Sent
    </label>
  </div>
  <div class="field">
    <label class="checkbox">
      <input type="checkbox" name="use_same_credentials" value="1" data-mailbox-same-credentials <?php echo $useSameCredentials ? 'checked' : ''; ?>>
      Use the same username and password for SMTP and IMAP
    </label>
  </div>
  <div class="field">
    <label class="checkbox">
      <input type="checkbox" name="auto_start_conversation_inbound" value="1" <?php echo !empty($formValues['auto_start_conversation_inbound']) ? 'checked' : ''; ?>>
      Automatically start a new conversation on inbound
    </label>
  </div>
</div>
<?php else: ?>
  <input type="hidden" name="delete_after_retrieve" value="<?php echo !empty($formValues['delete_after_retrieve']) ? '1' : '0'; ?>">
  <input type="hidden" name="store_sent_on_server" value="<?php echo !empty($formValues['store_sent_on_server']) ? '1' : '0'; ?>">
  <input type="hidden" name="auto_start_conversation_inbound" value="<?php echo !empty($formValues['auto_start_conversation_inbound']) ? '1' : '0'; ?>">
  <input type="hidden" name="use_same_credentials" value="<?php echo $useSameCredentials ? '1' : '0'; ?>">
<?php endif; ?>

<div class="column is-12">
  <h3 class="title is-5">SMTP Settings</h3>
</div>

<div class="column is-4">
  <div class="field">
    <label for="smtp_host" class="label">SMTP Host</label>
    <div class="control">
      <input type="text" id="smtp_host" name="smtp_host" class="input" value="<?php echo htmlspecialchars((string) ($formValues['smtp_host'] ?? '')); ?>" <?php echo $requireSmtp ? 'required' : ''; ?>>
    </div>
  </div>
</div>

<div class="column is-2">
  <div class="field">
    <label for="smtp_port" class="label">SMTP Port</label>
    <div class="control">
      <input type="number" id="smtp_port" name="smtp_port" class="input" value="<?php echo (int) ($formValues['smtp_port'] ?? 587); ?>" min="1" max="65535" <?php echo $requireSmtp ? 'required' : ''; ?>>
    </div>
  </div>
</div>

<div class="column is-3" data-smtp-credentials>
  <div class="field">
    <label for="smtp_username" class="label">SMTP Username</label>
    <div class="control">
      <input type="text" id="smtp_username" name="smtp_username" class="input" value="<?php echo htmlspecialchars((string) ($formValues['smtp_username'] ?? '')); ?>" data-smtp-username <?php echo $requireSmtp ? 'required' : ''; ?>>
    </div>
  </div>
</div>

<div class="column is-3" data-smtp-credentials>
  <div class="field">
    <label for="smtp_password" class="label">SMTP Password</label>
    <div class="control">
      <input type="password" id="smtp_password" name="smtp_password" class="input" autocomplete="new-password" data-smtp-password <?php echo $editMailbox || !$requireSmtp ? '' : 'required'; ?>>
    </div>
    <?php if ($editMailbox): ?>
      <p class="help">Leave blank to keep the current password.</p>
    <?php endif; ?>
  </div>
</div>

<div class="column is-3">
  <div class="field">
    <label for="smtp_encryption" class="label">SMTP Encryption</label>
    <div class="control">
      <div class="select is-fullwidth">
        <select id="smtp_encryption" name="smtp_encryption" <?php echo $requireSmtp ? 'required' : ''; ?>>
          <?php foreach ($allowedEncryptions as $option): ?>
            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($formValues['smtp_encryption'] ?? 'tls') === $option ? 'selected' : ''; ?>>
              <?php echo strtoupper($option); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
</div>
