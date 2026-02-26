<?php
?>
<?php renderPageStart('Team', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraStyles' => [
        BASE_PATH . '/app/public/css/list-layout.css'
    ],
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/app/public/js/tabs.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/mailboxes.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/list-panel.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/link-editor.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <h1 class="title is-3">Team</h1>
            </div>
          </div>

          <div class="tabs is-boxed" role="tablist">
            <ul>
              <li class="<?php echo $activeTab === 'tasks' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="tasks" role="tab" aria-selected="<?php echo $activeTab === 'tasks' ? 'true' : 'false'; ?>">Tasks</a>
              </li>
              <li class="<?php echo $activeTab === 'members' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="members" role="tab" aria-selected="<?php echo $activeTab === 'members' ? 'true' : 'false'; ?>">Members</a>
              </li>
              <?php if (!empty($isTeamAdmin)): ?>
                <li class="<?php echo $activeTab === 'mailboxes' ? 'is-active' : ''; ?>">
                  <a href="#" data-tab="mailboxes" role="tab" aria-selected="<?php echo $activeTab === 'mailboxes' ? 'true' : 'false'; ?>">Mailboxes</a>
                </li>
                <li class="<?php echo $activeTab === 'templates' ? 'is-active' : ''; ?>">
                  <a href="#" data-tab="templates" role="tab" aria-selected="<?php echo $activeTab === 'templates' ? 'true' : 'false'; ?>">Templates</a>
                </li>
              <?php endif; ?>
            </ul>
          </div>

          <?php require __DIR__ . '/tasks/tasks.php'; ?>

          <div class="tab-panel <?php echo $activeTab === 'members' ? '' : 'is-hidden'; ?>" data-tab-panel="members" role="tabpanel">
            <div class="box">
              <h2 class="title is-5">Team Members</h2>
              <p>Team management will be available here soon.</p>
            </div>
          </div>

          <?php if (!empty($isTeamAdmin)): ?>
            <?php require __DIR__ . '/mailboxes/mailboxes.php'; ?>
            <?php require __DIR__ . '/templates.php'; ?>
          <?php endif; ?>
        </div>
      </section>
<?php renderPageEnd(); ?>
