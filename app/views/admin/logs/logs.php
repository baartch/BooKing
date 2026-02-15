<?php
require_once __DIR__ . '/../../models/auth/admin_check.php';
require_once __DIR__ . '/../../models/core/list_helpers.php';
require_once __DIR__ . '/../../models/core/htmx_class.php';

if (!isset($logsRows, $logsPagination)):
?>
  <?php return; ?>
<?php endif; ?>

<?php
$logsPage = (int) ($logsPagination['page'] ?? 1);
$logsPerPage = (int) ($logsPagination['perPage'] ?? 100);
$logsTotal = (int) ($logsPagination['total'] ?? 0);
$logsTotalPages = (int) ($logsPagination['totalPages'] ?? 1);
$logsQuery = (string) ($logsPagination['query'] ?? '');

$baseUrl = BASE_PATH . '/app/controllers/admin/index.php?tab=logs';
$buildUrl = static function (int $page) use ($baseUrl, $logsPerPage, $logsQuery): string {
    $params = [
        'tab' => 'logs',
        'logs_page' => max(1, $page),
        'logs_per_page' => $logsPerPage
    ];

    if ($logsQuery !== '') {
        $params['logs_q'] = $logsQuery;
    }

    return BASE_PATH . '/app/controllers/admin/index.php?' . http_build_query($params);
};
?>

<?php
  $selectedLogId = (int) ($_GET['log_id'] ?? 0);
  $selectedLog = null;
  if ($selectedLogId > 0) {
      foreach ($logsRows as $row) {
          if ((int) ($row['id'] ?? 0) === $selectedLogId) {
              $selectedLog = $row;
              break;
          }
      }
  }

  $listTitle = 'Logs';
  $listSummaryTags = [
      sprintf('%d entries', $logsTotal),
      sprintf('Showing %d per page', $logsPerPage)
  ];
  $listSearch = [
      'action' => $baseUrl,
      'inputName' => 'logs_q',
      'inputValue' => $logsQuery,
      'placeholder' => 'Search (username/action/details)',
      'inputId' => 'logs-search',
      'hiddenFields' => [
          'tab' => 'logs',
          'logs_per_page' => $logsPerPage
      ]
  ];
  $listPrimaryActionHtml = null;

  $listRows = $logsRows;
  $listColumns = [
      buildListColumn('Timestamp', 'timestamp'),
      buildListColumn('Username', null, static function (array $row): string {
          $username = (string) ($row['username'] ?? '');
          return $username !== '' ? $username : 'System';
      }),
      buildListColumn('Action', null, static function (array $row): string {
          $action = (string) ($row['action'] ?? '');
          return '<code>' . htmlspecialchars($action) . '</code>';
      }, true)
  ];
  $listEmptyMessage = 'No logs found.';
  $listRowLink = static function (array $row) use ($logsPage, $logsPerPage, $logsQuery): array {
      $params = [
          'tab' => 'logs',
          'logs_page' => $logsPage,
          'logs_per_page' => $logsPerPage,
          'log_id' => (int) ($row['id'] ?? 0)
      ];
      if ($logsQuery !== '') {
          $params['logs_q'] = $logsQuery;
      }
      $detailLink = BASE_PATH . '/app/controllers/admin/index.php?' . http_build_query($params);
      return [
          'href' => $detailLink,
          'hx-get' => $detailLink,
          'hx-target' => '#admin-logs-detail',
          'hx-swap' => 'innerHTML',
          'hx-push-url' => ''
      ];
  };

  $detailRows = [];
  if ($selectedLog) {
      $details = (string) ($selectedLog['details'] ?? '');
      $detailRows = [
          buildDetailRow('Timestamp', (string) ($selectedLog['timestamp'] ?? '')),
          buildDetailRow('Username', (string) (($selectedLog['username'] ?? '') !== '' ? $selectedLog['username'] : 'System')),
          buildDetailRow('Action', '<code>' . htmlspecialchars((string) ($selectedLog['action'] ?? '')) . '</code>', true),
          buildDetailRow('Details', $details === '' ? '(empty)' : $details)
      ];
  }

  $detailTitle = $selectedLog ? 'Log Details' : null;
  $detailSubtitle = $selectedLog ? 'Entry #' . (int) ($selectedLog['id'] ?? 0) : null;
  $detailEmptyMessage = 'Select a log entry to see details.';

  $listContentPath = __DIR__ . '/logs/list.php';
  $detailContentPath = __DIR__ . '/logs/detail.php';
  $detailWrapperId = 'admin-logs-detail';
?>

<?php if (HTMX::isRequest() && !empty($renderLogsDetailOnly)): ?>
  <?php require $detailContentPath; ?>
  <?php return; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../partials/tables/two_column.php'; ?>

<nav class="level" aria-label="Pagination">
  <div class="level-left">
    <p class="help">Total: <?php echo htmlspecialchars((string) $logsTotal); ?> entries</p>
  </div>
  <div class="level-right">
    <div class="buttons has-addons">
      <?php if ($logsPage <= 1): ?>
        <span class="button is-static">Previous</span>
      <?php else: ?>
        <a class="button" href="<?php echo htmlspecialchars($buildUrl($logsPage - 1)); ?>">Previous</a>
      <?php endif; ?>

      <span class="button is-static">Page <?php echo htmlspecialchars((string) $logsPage); ?> / <?php echo htmlspecialchars((string) $logsTotalPages); ?></span>

      <?php if ($logsPage >= $logsTotalPages): ?>
        <span class="button is-static">Next</span>
      <?php else: ?>
        <a class="button" href="<?php echo htmlspecialchars($buildUrl($logsPage + 1)); ?>">Next</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
