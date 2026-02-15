<?php
require_once __DIR__ . '/../../models/core/list_helpers.php';

$baseUrl = BASE_PATH . '/app/controllers/venues/index.php';
$mode = (string) ($_GET['mode'] ?? '');
$selectedVenueId = (int) ($_GET['venue_id'] ?? 0);

$activeVenue = null;
if ($selectedVenueId > 0) {
    foreach ($venues as $venue) {
        if ((int) ($venue['id'] ?? 0) === $selectedVenueId) {
            $activeVenue = $venue;
            break;
        }
    }
}

$showForm = $mode === 'settings';

$venuesPage = (int) ($page ?? 1);
$venuesPerPage = (int) ($pageSize ?? 25);
$venuesTotal = (int) ($totalVenues ?? 0);
$venuesTotalPages = (int) ($totalPages ?? 1);
$venuesQuery = (string) ($filter ?? '');

$buildUrl = static function (int $pageNumber) use ($baseUrl, $venuesPerPage, $venuesQuery): string {
    $params = [
        'page' => max(1, $pageNumber),
        'per_page' => $venuesPerPage
    ];

    if ($venuesQuery !== '') {
        $params['filter'] = $venuesQuery;
    }

    return $baseUrl . '?' . http_build_query($params);
};

$listTitle = 'Venues';
$listSummaryTags = [
    sprintf('%d venues', $venuesTotal),
    sprintf('Showing %d per page', $venuesPerPage)
];
$listPrimaryActionHtml = null;

if (($currentUser['role'] ?? '') === 'admin') {
    $addUrl = BASE_PATH . '/app/controllers/venues/add.php';
    $listPrimaryActionHtml = '<div class="buttons">'
        . '<a href="' . htmlspecialchars($addUrl) . '" class="button is-primary">Add Venue</a>'
        . '<button type="button" class="button" data-import-toggle>Import</button>'
        . '</div>';
}

$listSearch = [
    'action' => $baseUrl,
    'inputName' => 'filter',
    'inputValue' => $venuesQuery,
    'placeholder' => 'Search venues...',
    'inputId' => 'venues-search',
    'hiddenFields' => [
        'per_page' => $venuesPerPage
    ]
];

$listContentPath = __DIR__ . '/venues/list.php';
$detailContentPath = $showForm ? __DIR__ . '/venues/form.php' : __DIR__ . '/venues/detail.php';
$importModalPath = __DIR__ . '/../../partials/venues/import_modal.php';
$detailWrapperId = 'venues-detail-panel';

if (HTMX::isRequest()) {
    require $detailContentPath;
    return;
}
?>
<?php if (($currentUser['role'] ?? '') === 'admin'): ?>
  <?php require $importModalPath; ?>
<?php endif; ?>
<?php if (!empty($notice)): ?>
  <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
  <div class="notification"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<?php require __DIR__ . '/../../partials/tables/two_column.php'; ?>

<nav class="level" aria-label="Pagination">
  <div class="level-left">
    <p class="help">Total: <?php echo htmlspecialchars((string) $venuesTotal); ?> venues</p>
  </div>
  <div class="level-right">
    <div class="buttons has-addons">
      <?php if ($venuesPage <= 1): ?>
        <span class="button is-static">Previous</span>
      <?php else: ?>
        <a class="button" href="<?php echo htmlspecialchars($buildUrl($venuesPage - 1)); ?>">Previous</a>
      <?php endif; ?>

      <span class="button is-static">Page <?php echo htmlspecialchars((string) $venuesPage); ?> / <?php echo htmlspecialchars((string) $venuesTotalPages); ?></span>

      <?php if ($venuesPage >= $venuesTotalPages): ?>
        <span class="button is-static">Next</span>
      <?php else: ?>
        <a class="button" href="<?php echo htmlspecialchars($buildUrl($venuesPage + 1)); ?>">Next</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
