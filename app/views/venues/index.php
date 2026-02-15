<?php
?>
<?php renderPageStart('Venues', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/app/public/js/venues.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/list-panel.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <?php require __DIR__ . '/venues.php'; ?>
        </div>
      </section>
<?php renderPageEnd(); ?>
