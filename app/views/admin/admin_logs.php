<?php
require_once __DIR__ . '/../../models/auth/admin_check.php';

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

<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <div class="content">
        <h2 class="title is-5 mb-1">Logs</h2>
        <p class="help">Most recent application actions. Showing <?php echo htmlspecialchars((string) $logsPerPage); ?> per page.</p>
      </div>
    </div>
    <div class="level-right">
      <form method="GET" action="" class="field has-addons">
        <input type="hidden" name="tab" value="logs">
        <input type="hidden" name="logs_per_page" value="<?php echo (int) $logsPerPage; ?>">
        <div class="control">
          <input class="input" type="text" name="logs_q" placeholder="Search (username/action/details)" value="<?php echo htmlspecialchars($logsQuery); ?>">
        </div>
        <div class="control">
          <button class="button" type="submit">Search</button>
        </div>
        <?php if ($logsQuery !== ''): ?>
          <div class="control">
            <a class="button" href="<?php echo htmlspecialchars($baseUrl); ?>">Clear</a>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div class="table-container">
    <table class="table is-fullwidth is-striped is-hoverable">
      <thead>
        <tr>
          <th>Timestamp</th>
          <th>Username</th>
          <th>Action</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logsRows)): ?>
          <tr>
            <td colspan="4">No logs found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($logsRows as $row): ?>
            <?php
              $timestamp = (string) ($row['timestamp'] ?? '');
              $username = (string) ($row['username'] ?? '');
              $action = (string) ($row['action'] ?? '');
              $details = (string) ($row['details'] ?? '');
              $usernameLabel = $username !== '' ? $username : 'System';
            ?>
            <tr>
              <td><?php echo htmlspecialchars($timestamp); ?></td>
              <td><?php echo htmlspecialchars($usernameLabel); ?></td>
              <td><code><?php echo htmlspecialchars($action); ?></code></td>
              <td>
                <?php if ($details === ''): ?>
                  <span class="has-text-grey">(empty)</span>
                <?php else: ?>
                  <details>
                    <summary>View</summary>
                    <pre class="is-family-monospace"><?php echo htmlspecialchars($details); ?></pre>
                  </details>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

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
</div>
