<?php
?>
<?php renderPageStart('Profile', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/app/public/js/tabs.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/appearance.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <h1 class="title is-3">Profile</h1>
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
              <li class="<?php echo $activeTab === 'venues' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="venues" role="tab" aria-selected="<?php echo $activeTab === 'venues' ? 'true' : 'false'; ?>">Venues</a>
              </li>
              <li class="<?php echo $activeTab === 'mailboxes' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="mailboxes" role="tab" aria-selected="<?php echo $activeTab === 'mailboxes' ? 'true' : 'false'; ?>">Mailboxes</a>
              </li>
              <li class="<?php echo $activeTab === 'appearance' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="appearance" role="tab" aria-selected="<?php echo $activeTab === 'appearance' ? 'true' : 'false'; ?>">Appearance</a>
              </li>
            </ul>
          </div>

          <?php require __DIR__ . '/venues.php'; ?>
          <?php require __DIR__ . '/mailboxes.php'; ?>
          <?php require __DIR__ . '/appearance.php'; ?>
        </div>
      </section>
<?php renderPageEnd(); ?>
