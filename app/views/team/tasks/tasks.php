<?php
/** @var string $activeTab */
$baseUrl = BASE_PATH . '/app/controllers/team/index.php';
$mode = (string) ($_GET['mode'] ?? '');
$selectedTaskId = (int) ($_GET['task_id'] ?? 0);
$searchQuery = $searchQuery ?? '';
$activeTeamId = (int) ($activeTeamId ?? 0);
$taskLinks = $taskLinks ?? [];
$baseQuery = array_filter([
    'tab' => 'tasks',
    'team_id' => $activeTeamId > 0 ? $activeTeamId : null,
    'q' => $searchQuery !== '' ? $searchQuery : null
], static fn($value) => $value !== null && $value !== '');
$activeTask = null;

if ($selectedTaskId > 0) {
    foreach ($tasks as $task) {
        if ((int) ($task['id'] ?? 0) === $selectedTaskId) {
            $activeTask = $task;
            break;
        }
    }

    if (!$activeTask && isset($pdo) && $activeTeamId > 0) {
        try {
            $activeTask = fetchTeamTask($pdo, $activeTeamId, $selectedTaskId);
        } catch (Throwable $error) {
            $activeTask = null;
        }
    }
}

$isEdit = $mode === 'edit' && $selectedTaskId > 0 && $activeTask !== null;
$showForm = $mode === 'new' || $isEdit;
$editTask = $showForm && $isEdit ? $activeTask : null;

$formValues = [
    'title' => '',
    'description' => '',
    'priority' => 'B',
    'due_date' => ''
];

if ($editTask) {
    $formValues = [
        'title' => (string) ($editTask['title'] ?? ''),
        'description' => (string) ($editTask['description'] ?? ''),
        'priority' => (string) ($editTask['priority'] ?? 'B'),
        'due_date' => (string) ($editTask['due_date'] ?? '')
    ];
}

$cancelQuery = array_filter([
    'tab' => 'tasks',
    'team_id' => $activeTeamId > 0 ? $activeTeamId : null,
    'task_id' => $selectedTaskId > 0 ? $selectedTaskId : null,
    'q' => $searchQuery !== '' ? $searchQuery : null
], static fn($value) => $value !== null && $value !== '');
$cancelUrl = $baseUrl . '?' . http_build_query($cancelQuery);

$listTitle = 'Tasks';
$listSummaryTags = [sprintf('%d tasks', count($tasks))];
$addTaskUrl = $baseUrl . '?' . http_build_query(array_merge($baseQuery, [
    'mode' => 'new'
]));
$listPrimaryActionHtml = '<a href="' . htmlspecialchars($addTaskUrl) . '" class="button is-primary">Add Task</a>';

$listSearch = [
    'action' => $baseUrl,
    'inputName' => 'q',
    'inputValue' => $searchQuery,
    'placeholder' => 'Search for tasks…',
    'inputId' => 'task-filter',
    'hiddenFields' => [
        'tab' => 'tasks',
        'team_id' => $activeTeamId
    ]
];

$listContentPath = __DIR__ . '/list.php';
$detailContentPath = $showForm ? __DIR__ . '/form.php' : __DIR__ . '/detail.php';

if ($showForm) {
    $taskLinks = $taskLinks ?? [];
}
$detailWrapperId = 'team-task-detail-panel';

if (HTMX::isRequest()) {
    require $detailContentPath;
    return;
}
?>

<div class="tab-panel <?php echo $activeTab === 'tasks' ? '' : 'is-hidden'; ?>" data-tab-panel="tasks" role="tabpanel">
  <?php if (!empty($notice['tasks'])): ?>
    <div class="notification"><?php echo htmlspecialchars($notice['tasks']); ?></div>
  <?php endif; ?>

  <?php foreach ($errors['tasks'] as $error): ?>
    <div class="notification"><?php echo htmlspecialchars($error); ?></div>
  <?php endforeach; ?>

  <?php if ($activeTeamId > 0 && isset($userTeams) && count($userTeams) > 1): ?>
    <div class="box">
      <div class="field">
        <label class="label">Team</label>
        <div class="control">
          <div class="select">
            <select onchange="window.location.href=this.value;">
              <?php foreach ($userTeams as $team): ?>
                <?php
                  $teamId = (int) ($team['id'] ?? 0);
                  $teamUrl = $baseUrl . '?' . http_build_query(array_filter([
                    'tab' => 'tasks',
                    'team_id' => $teamId,
                    'q' => $searchQuery !== '' ? $searchQuery : null
                  ], static fn($value) => $value !== null && $value !== ''));
                ?>
                <option value="<?php echo htmlspecialchars($teamUrl); ?>" <?php echo $teamId === (int) $activeTeamId ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars((string) ($team['name'] ?? ('Team #' . $teamId))); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <p class="help">Tasks are scoped to the selected team.</p>
      </div>
    </div>
  <?php endif; ?>

  <?php require __DIR__ . '/../../../partials/tables/two_column.php'; ?>
</div>
