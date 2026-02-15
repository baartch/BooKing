<?php
require_once __DIR__ . '/../../models/auth/admin_check.php';
require_once __DIR__ . '/../../src-php/communication/mailbox_helpers.php';

$errors = $errors ?? [];
$notice = $notice ?? '';
$activeTab = $activeTab ?? 'users';

$allowedEncryptions = ['ssl', 'tls', 'none'];
$defaultImapPort = 993;
$defaultSmtpPort = 587;

$formValues = mailboxFormDefaults($defaultImapPort, $defaultSmtpPort);
$formValues['name'] = 'Global SMTP';

if (!isset($smtpMailbox)) {
    $smtpMailbox = null;
}

if ($smtpMailbox) {
    $formValues = mailboxFormValuesFromRow($smtpMailbox, $defaultImapPort, $defaultSmtpPort);
}

$showTeamSelect = false;
$showMailboxName = false;
$showImap = false;
$showOptions = false;
$requireImap = false;
$requireSmtp = true;
$editMailbox = $smtpMailbox;
?>

<div class="box">
  <h2 class="title is-5">Global SMTP Settings</h2>
  <p class="mb-4">Use these credentials to send app-level emails (OTP, alarms, system notifications).</p>

  <form method="POST" action="" class="columns is-multiline">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="save_global_smtp">
    <input type="hidden" name="tab" value="smtp">
    <input type="hidden" name="mailbox_id" value="<?php echo $smtpMailbox ? (int) $smtpMailbox['id'] : 0; ?>">
    <?php require __DIR__ . '/../../partials/mailbox_form_fields.php'; ?>

    <div class="column is-12">
      <div class="buttons">
        <button type="submit" class="button is-primary">Save SMTP Settings</button>
      </div>
    </div>
  </form>
</div>
