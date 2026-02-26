<?php
/**
 * Variables expected:
 * - array $tasks
 * - string $baseUrl
 * - array $baseQuery
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$listColumns = [
    buildListColumn('Title', 'title'),
    buildListColumn('Priority', 'priority'),
    buildListColumn('Due', null, static function (array $task): string {
        $due = (string) ($task['due_date'] ?? '');
        return $due !== '' ? $due : '—';
    })
];

$listRows = $tasks;
$listEmptyMessage = 'No tasks found.';

$listRowLink = static function (array $task) use ($baseUrl, $baseQuery): array {
    $taskId = (int) ($task['id'] ?? 0);
    $detailLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['task_id' => $taskId]));

    return [
        'href' => $detailLink,
        'hx-get' => $detailLink,
        'hx-target' => '#team-task-detail-panel',
        'hx-swap' => 'innerHTML',
        'hx-push-url' => $detailLink
    ];
};

$listRowActions = static function (array $task) use ($baseUrl, $baseQuery, $activeTeamId, $searchQuery): string {
    $taskId = (int) ($task['id'] ?? 0);
    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, [
        'task_id' => $taskId,
        'mode' => 'edit'
    ]));
    ob_start();
    ?>
      <div class="buttons are-small is-justify-content-flex-end">
        <a class="button" href="<?php echo htmlspecialchars($editLink); ?>" aria-label="Edit task" title="Edit task">
          <span class="icon"><i class="fa-solid fa-pen"></i></span>
        </a>
        <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/team/tasks_delete.php" onsubmit="return confirm('Delete this task?');">
          <?php renderCsrfField(); ?>
          <input type="hidden" name="task_id" value="<?php echo (int) $taskId; ?>">
          <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>">
          <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
          <button type="submit" class="button" aria-label="Delete task" title="Delete task">
            <span class="icon"><i class="fa-solid fa-trash"></i></span>
          </button>
        </form>
      </div>
    <?php
    return (string) ob_get_clean();
};

$listActionsLabel = 'Actions';

require __DIR__ . '/../../../partials/tables/table.php';
