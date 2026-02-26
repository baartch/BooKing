<?php
/**
 * Variables expected:
 * - array $teams
 * - string $baseUrl
 * - array $baseQuery
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$listColumns = [
    buildListColumn('Team', 'name'),
    buildListColumn('Description', null, static function (array $team): string {
        $description = (string) ($team['description'] ?? '');
        return $description !== '' ? $description : '—';
    }, false)
];

$listRows = $teams;
$listEmptyMessage = 'No teams found.';

$listRowLink = static function (array $team) use ($baseUrl, $baseQuery): array {
    $teamId = (int) ($team['id'] ?? 0);
    $detailLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['team_id' => $teamId]));

    return [
        'href' => $detailLink,
        'hx-get' => $detailLink,
        'hx-target' => '#admin-teams-detail-panel',
        'hx-swap' => 'innerHTML',
        'hx-push-url' => $detailLink
    ];
};

$listRowActions = static function (array $team) use ($baseUrl, $baseQuery): string {
    $teamId = (int) ($team['id'] ?? 0);
    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['team_id' => $teamId, 'mode' => 'edit']));
    ob_start();
    ?>
      <div class="buttons are-small is-justify-content-flex-end">
        <form method="GET" action="<?php echo htmlspecialchars($editLink); ?>">
          <button type="submit" class="button" aria-label="Edit team" title="Edit team">
            <span class="icon"><i class="fa-solid fa-pen"></i></span>
          </button>
        </form>
        <form method="POST" action="" onsubmit="return confirm('Delete this team?');">
          <?php renderCsrfField(); ?>
          <input type="hidden" name="action" value="delete_team">
          <input type="hidden" name="tab" value="teams">
          <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
          <button type="submit" class="button" aria-label="Delete team" title="Delete team">
            <span class="icon"><i class="fa-solid fa-trash"></i></span>
          </button>
        </form>
      </div>
    <?php
    return (string) ob_get_clean();
};

$listActionsLabel = 'Actions';

require __DIR__ . '/../../../partials/tables/table.php';
