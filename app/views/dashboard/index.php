<?php
$fullCalendarUrl = 'https://cdn.jsdelivr.net/npm/fullcalendar@7.1.4';
$hasCalendarAssets = ($calendarError ?? '') === '' && !empty($calendarTeams);
renderPageStart('Dashboard', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraStyles' => $hasCalendarAssets ? [
        $fullCalendarUrl . '/skeleton.css',
        $fullCalendarUrl . '/themes/monarch/theme.css',
        $fullCalendarUrl . '/themes/monarch/palettes/purple.css',
        BASE_PATH . '/app/public/css/dashboard-calendar.css',
    ] : [],
    'extraScripts' => $hasCalendarAssets ? [
        '<script defer src="' . $fullCalendarUrl . '/all/global.js"></script>',
        '<script defer src="' . $fullCalendarUrl . '/themes/monarch/global.js"></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/dashboard-calendar.js"></script>',
    ] : [],
]);
?>
      <section class="section">
        <div class="container">
          <div class="level mb-5">
            <div class="level-left">
              <h1 class="title is-3">Dashboard</h1>
              <p class="subtitle is-6">Signed in as <?php echo htmlspecialchars((string) (($currentUser['display_name'] ?? '') !== '' ? $currentUser['display_name'] : ($currentUser['username'] ?? ''))); ?></p>
            </div>
          </div>
          <?php if ($calendarError !== ''): ?>
            <div class="notification is-danger" role="alert"><?php echo htmlspecialchars($calendarError, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php elseif (!$calendarTeams): ?>
            <div class="box">Join a team to see its shows and tasks here.</div>
          <?php else: ?>
            <div class="box dashboard-calendar"
                 data-dashboard-calendar
                 data-calendar-events-url="<?php echo htmlspecialchars(BASE_PATH . '/app/controllers/dashboard/calendar-events.php', ENT_QUOTES, 'UTF-8'); ?>">
              <?php if (count($calendarTeams) > 1): ?>
                <fieldset class="mb-4">
                  <legend class="label">Teams</legend>
                  <?php foreach ($calendarTeams as $team): ?>
                    <label class="checkbox mr-4">
                      <input type="checkbox" checked data-calendar-team-toggle
                             data-team-id="<?php echo (int) $team['id']; ?>"
                             data-team-name="<?php echo htmlspecialchars((string) $team['name'], ENT_QUOTES, 'UTF-8'); ?>">
                      <?php echo htmlspecialchars((string) $team['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </label>
                  <?php endforeach; ?>
                </fieldset>
              <?php else: ?>
                <span hidden data-calendar-team
                      data-team-id="<?php echo (int) $calendarTeams[0]['id']; ?>"
                      data-team-name="<?php echo htmlspecialchars((string) $calendarTeams[0]['name'], ENT_QUOTES, 'UTF-8'); ?>"></span>
              <?php endif; ?>
              <div data-calendar-error class="notification is-danger is-hidden" role="alert"></div>
              <div data-calendar-loading class="mb-3 is-hidden" role="status">Loading calendar events…</div>
              <div data-calendar-empty class="notification is-light is-hidden" role="status"></div>
              <div data-calendar></div>
            </div>
            <dialog class="dashboard-calendar-dialog" data-calendar-dialog aria-labelledby="calendar-dialog-title">
              <div class="box">
                <h2 id="calendar-dialog-title" class="title is-4" data-calendar-dialog-title></h2>
                <p class="subtitle is-6" data-calendar-dialog-team></p>
                <p class="has-text-weight-semibold" data-calendar-dialog-description-label></p>
                <p class="dashboard-calendar-description mb-4" data-calendar-dialog-description></p>
                <div class="buttons">
                  <a class="button is-link" data-calendar-dialog-link>View details</a>
                  <button class="button" type="button" data-calendar-dialog-close>Close</button>
                </div>
              </div>
            </dialog>
          <?php endif; ?>
        </div>
      </section>
<?php renderPageEnd(); ?>
