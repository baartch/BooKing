<?php
/**
 * Variables expected:
 * - array $mailboxes
 * - string $baseUrl
 * - array $baseQuery
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$listColumns = [
    buildListColumn('Name', 'name'),
    buildListColumn('Displayname', 'display_name'),
    buildListColumn('Team', 'team_name')
];

$listRows = $mailboxes;
$listEmptyMessage = 'No mailboxes configured yet.';

$listRowLink = static function (array $mailbox) use ($baseUrl, $baseQuery): array {
    $mailboxId = (int) ($mailbox['id'] ?? 0);
    $detailLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['mailbox_id' => $mailboxId]));

    return [
        'href' => $detailLink,
        'hx-get' => $detailLink,
        'hx-target' => '#team-mailbox-detail-panel',
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
        <a class="button" href="<?php echo htmlspecialchars($editLink); ?>" aria-label="Edit mailbox" title="Edit mailbox">
          <span class="icon"><i class="fa-solid fa-pen"></i></span>
        </a>
        <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/team/index.php?tab=mailboxes" onsubmit="return confirm('Delete this mailbox?');">
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
