<?php
// Admin panel controller (server-rendered)
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../src-php/core/htmx_class.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    // Tab comes from hidden input in forms.
    $activeTab = (string) ($_POST['tab'] ?? $activeTab);
    if (!in_array($activeTab, $validTabs, true)) {
        $activeTab = 'users';
    }
}

// Load per-tab controller logic.
// Note: we still load all datasets needed by each partial, but split by concern.
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/teams.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/logs.php';

if (HTMX::isRequest() && $activeTab === 'users') {
    require __DIR__ . '/../../views/admin/users.php';
    return;
}

renderPageStart('Admin', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/app/public/js/tabs.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/mailboxes.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/list-panel.js" defer></script>'
    ]
]);
?>

<section class="section">
  <div class="container is-fluid">
    <div class="level mb-4">
      <div class="level-left">
        <h1 class="title is-3">Admin</h1>
      </div>
    </div>

    <?php if ($notice): ?>
      <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
      <div class="notification"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <div class="tabs is-boxed" role="tablist">
      <ul>
        <li class="<?php echo $activeTab === 'users' ? 'is-active' : ''; ?>">
          <a href="#" data-tab="users" role="tab" aria-selected="<?php echo $activeTab === 'users' ? 'true' : 'false'; ?>">Users</a>
        </li>
        <li class="<?php echo $activeTab === 'teams' ? 'is-active' : ''; ?>">
          <a href="#" data-tab="teams" role="tab" aria-selected="<?php echo $activeTab === 'teams' ? 'true' : 'false'; ?>">Teams</a>
        </li>
        <li class="<?php echo $activeTab === 'api-keys' ? 'is-active' : ''; ?>">
          <a href="#" data-tab="api-keys" role="tab" aria-selected="<?php echo $activeTab === 'api-keys' ? 'true' : 'false'; ?>">API Keys</a>
        </li>
        <li class="<?php echo $activeTab === 'smtp' ? 'is-active' : ''; ?>">
          <a href="#" data-tab="smtp" role="tab" aria-selected="<?php echo $activeTab === 'smtp' ? 'true' : 'false'; ?>">SMTP</a>
        </li>
        <li class="<?php echo $activeTab === 'logs' ? 'is-active' : ''; ?>">
          <a href="#" data-tab="logs" role="tab" aria-selected="<?php echo $activeTab === 'logs' ? 'true' : 'false'; ?>">Logs</a>
        </li>
      </ul>
    </div>

    <div class="tab-panel <?php echo $activeTab === 'users' ? '' : 'is-hidden'; ?>" data-tab-panel="users" role="tabpanel">
      <?php require __DIR__ . '/../../views/admin/users.php'; ?>
    </div>

    <div class="tab-panel <?php echo $activeTab === 'teams' ? '' : 'is-hidden'; ?>" data-tab-panel="teams" role="tabpanel">
      <?php require __DIR__ . '/../../views/admin/admin_teams.php'; ?>
    </div>

    <div class="tab-panel <?php echo $activeTab === 'api-keys' ? '' : 'is-hidden'; ?>" data-tab-panel="api-keys" role="tabpanel">
      <?php require __DIR__ . '/../../views/admin/admin_api_keys.php'; ?>
    </div>

    <div class="tab-panel <?php echo $activeTab === 'smtp' ? '' : 'is-hidden'; ?>" data-tab-panel="smtp" role="tabpanel">
      <?php require __DIR__ . '/../../views/admin/admin_smtp.php'; ?>
    </div>

    <div class="tab-panel <?php echo $activeTab === 'logs' ? '' : 'is-hidden'; ?>" data-tab-panel="logs" role="tabpanel">
      <?php require __DIR__ . '/../../views/admin/admin_logs.php'; ?>
    </div>
  </div>
</section>

<?php renderPageEnd();
