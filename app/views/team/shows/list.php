<?php
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$listColumns = [
    buildListColumn('Date', 'show_date'),
];

$listRows = $shows;
$listEmptyMessage = 'No shows found.';

$listRowLink = static function (array $show) use ($baseUrl, $baseQuery): array {
    $showId = (int) ($show['id'] ?? 0);
    $detailLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['show_id' => $showId]));

    return [
        'href' => $detailLink,
        'hx-get' => $detailLink,
        'hx-target' => '#team-show-detail-panel',
        'hx-swap' => 'innerHTML',
        'hx-push-url' => $detailLink,
    ];
};

$listRowActions = static function (array $show) use ($baseUrl, $baseQuery, $activeTeamId, $searchQuery): string {
    $showId = (int) ($show['id'] ?? 0);
    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, [
        'show_id' => $showId,
        'mode' => 'edit',
    ]));

    ob_start();
    ?>
    <div class="buttons are-small is-justify-content-flex-end">
      <form method="GET" action="<?php echo htmlspecialchars($editLink); ?>">
        <button type="submit" class="button" aria-label="Edit show" title="Edit show">
          <span class="icon"><i class="fa-solid fa-pen"></i></span>
        </button>
      </form>
    </div>
    <?php

    return (string) ob_get_clean();
};

$listActionsLabel = 'Actions';

require __DIR__ . '/../../../partials/tables/table.php';
