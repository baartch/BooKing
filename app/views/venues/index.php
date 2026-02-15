<?php
?>
<?php renderPageStart('Venues', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/app/public/js/venues.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <?php require __DIR__ . '/../../partials/venues/header.php'; ?>

          <?php if ($notice): ?>
            <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <?php require __DIR__ . '/../../partials/venues/import_modal.php'; ?>

          <div class="box">
            <?php require __DIR__ . '/../../partials/venues/filter_bar.php'; ?>
            <?php require __DIR__ . '/../../partials/venues/venues_table.php'; ?>
            <?php require __DIR__ . '/../../partials/venues/pagination.php'; ?>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
