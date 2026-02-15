<?php
?>
<?php renderPageStart('Dashboard', ['bodyClass' => 'is-flex is-flex-direction-column is-fullheight']); ?>
      <section class="section">
        <div class="container">
          <div class="level mb-5">
            <div class="level-left">
              <h1 class="title is-3">Dashboard</h1>
              <p class="subtitle is-6">Signed in as <?php echo htmlspecialchars((string) (($currentUser['display_name'] ?? '') !== '' ? $currentUser['display_name'] : ($currentUser['username'] ?? ''))); ?></p>
            </div>
          </div>
          <div class="box">
            <p>Dashboard content coming soon.</p>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
