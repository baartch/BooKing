<?php
/**
 * Variables expected:
 * - array $personalMailboxes
 * - string $baseUrl
 * - array $baseQuery
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$listColumns = [
    buildListColumn('Name', 'name'),
    buildListColumn('Displayname', 'display_name')
];

$listRows = $personalMailboxes;
$listEmptyMessage = 'No mailboxes configured yet.';

$listRowLink = static function (array $mailbox) use ($baseUrl, $baseQuery): array {
    $mailboxId = (int) ($mailbox['id'] ?? 0);
    $detailLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['mailbox_id' => $mailboxId]));

    return [
        'href' => $detailLink,
        'hx-get' => $detailLink,
        'hx-target' => '#profile-mailbox-detail-panel',
        'hx-swap' => 'innerHTML',
        'hx-push-url' => $detailLink
    ];
};

$listRowActions = static function (array $mailbox) use ($baseUrl, $baseQuery): string {
    $mailboxId = (int) ($mailbox['id'] ?? 0);
    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['mailbox_id' => $mailboxId, 'mode' => 'edit']));
    ob_start();
    ?>
      <div class="buttons are-small is-justify-content-flex-end">
        <form method="GET" action="<?php echo htmlspecialchars($editLink); ?>">
          <button type="submit" class="button" aria-label="Edit mailbox" title="Edit mailbox">
            <span class="icon"><i class="fa-solid fa-pen"></i></span>
          </button>
        </form>
        <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/profile/index.php?tab=mailboxes" onsubmit="return confirm('Delete this mailbox?');">
          <?php renderCsrfField(); ?>
          <input type="hidden" name="action" value="delete_mailbox">
          <input type="hidden" name="mailbox_id" value="<?php echo (int) $mailboxId; ?>">
          <button type="submit" class="button" aria-label="Delete mailbox" title="Delete mailbox">
            <span class="icon"><i class="fa-solid fa-trash"></i></span>
          </button>
        </form>
      </div>
    <?php
    return (string) ob_get_clean();
};

$listActionsLabel = 'Actions';

require __DIR__ . '/../../../partials/tables/table.php';
