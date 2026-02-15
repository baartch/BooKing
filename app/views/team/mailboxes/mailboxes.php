<?php
/** @var string $activeTab */

$baseUrl = BASE_PATH . '/app/controllers/team/index.php';
$baseQuery = ['tab' => 'mailboxes'];
$mode = (string) ($_GET['mode'] ?? '');
$selectedMailboxId = (int) ($_GET['mailbox_id'] ?? 0);

$activeMailbox = null;
if ($selectedMailboxId > 0) {
    foreach ($mailboxes as $mailbox) {
        if ((int) ($mailbox['id'] ?? 0) === $selectedMailboxId) {
            $activeMailbox = $mailbox;
            break;
        }
    }
}

$isEdit = $mode === 'edit' && $selectedMailboxId > 0 && $activeMailbox !== null;
$showForm = $mode === 'new' || $isEdit;

$editMailbox = $showForm && $isEdit ? $activeMailbox : null;

$allowedEncryptions = ['ssl', 'tls', 'none'];
$defaultImapPort = 993;
$defaultSmtpPort = 587;
$formValues = mailboxFormDefaults($defaultImapPort, $defaultSmtpPort);
if ($editMailbox) {
    $formValues = mailboxFormValuesFromRow($editMailbox, $defaultImapPort, $defaultSmtpPort);
}

$cancelQuery = array_filter([
    'tab' => 'mailboxes',
    'mailbox_id' => $selectedMailboxId > 0 ? $selectedMailboxId : null
], static fn($value) => $value !== null && $value !== '');
$cancelUrl = $baseUrl . '?' . http_build_query($cancelQuery);

$listTitle = 'Mailboxes';
$listSummaryTags = [sprintf('%d mailboxes', count($mailboxes))];
$addMailboxUrl = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['mode' => 'new']));
$listPrimaryActionHtml = '<a href="' . htmlspecialchars($addMailboxUrl) . '" class="button is-primary">Add Mailbox</a>';

$listContentPath = __DIR__ . '/list.php';
$detailContentPath = $showForm ? __DIR__ . '/form.php' : __DIR__ . '/detail.php';
$detailWrapperId = 'team-mailbox-detail-panel';

if (HTMX::isRequest()) {
    require $detailContentPath;
    return;
}
?>
<div class="tab-panel <?php echo $activeTab === 'mailboxes' ? '' : 'is-hidden'; ?>" data-tab-panel="mailboxes" role="tabpanel">
  <?php if (!empty($notice['mailboxes'])): ?>
    <div class="notification"><?php echo htmlspecialchars($notice['mailboxes']); ?></div>
  <?php endif; ?>

  <?php foreach ($errors['mailboxes'] as $error): ?>
    <div class="notification"><?php echo htmlspecialchars($error); ?></div>
  <?php endforeach; ?>

  <?php require __DIR__ . '/../../../partials/tables/two_column.php'; ?>
</div>
