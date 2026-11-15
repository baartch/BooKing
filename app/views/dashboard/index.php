<?php
$fullCalendarUrl = 'https://cdn.jsdelivr.net/npm/fullcalendar@7.1.4';
$hasCalendarAssets = ($calendarError ?? '') === '' && !empty($calendarTeams);
$dashboardScripts = [
    '<script type="module" src="' . BASE_PATH . '/app/public/js/list-panel.js" defer></script>',
    '<script type="module" src="' . BASE_PATH . '/app/public/js/dashboard-email.js" defer></script>',
];
if ($hasCalendarAssets) {
    $dashboardScripts = array_merge([
        '<script defer src="' . $fullCalendarUrl . '/all/global.js"></script>',
        '<script defer src="' . $fullCalendarUrl . '/themes/monarch/global.js"></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/dashboard-calendar.js"></script>',
    ], $dashboardScripts);
}

$renderUnreadEmailTable = static function (array $rows): void {
    $emailLink = static function (array $row): string {
        $query = http_build_query([
            'tab' => 'email',
            'mailbox_id' => (int) ($row['mailbox_id'] ?? 0),
            'folder' => 'inbox',
            'message_id' => (int) ($row['id'] ?? 0),
        ]);
        return BASE_PATH . '/app/controllers/communication/index.php?' . $query;
    };

    $listRows = $rows;
    $listColumns = [
        [
            'label' => 'Mailbox',
            'isHtml' => true,
            'render' => static function (array $row): string {
                $label = (string) ($row['mailbox_label'] ?? 'Mailbox');
                $initial = (string) ($row['mailbox_initial'] ?? 'M');
                return '<span class="tag is-info is-light" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                    . '" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</span>';
            },
        ],
        ['label' => 'Sender', 'key' => 'from_email'],
        [
            'label' => 'Subject',
            'isHtml' => true,
            'render' => static function (array $row) use ($emailLink): string {
                $subject = trim((string) ($row['subject'] ?? ''));
                if ($subject === '') {
                    $subject = '(No subject)';
                }
                return '<a href="' . htmlspecialchars($emailLink($row), ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</a>';
            },
        ],
        [
            'label' => 'Date',
            'isHtml' => true,
            'render' => static function (array $row): string {
                $timestamp = trim((string) (($row['received_at'] ?? '') !== '' ? $row['received_at'] : ($row['created_at'] ?? '')));
                $datetime = dashboardEmailTimestampToIso8601($timestamp);
                $datetimeAttribute = $datetime !== null
                    ? ' datetime="' . htmlspecialchars($datetime, ENT_QUOTES, 'UTF-8') . '"'
                    : '';
                $fallback = $timestamp !== '' ? $timestamp : '—';
                return '<time data-dashboard-email-date' . $datetimeAttribute . '>'
                    . htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8') . '</time>';
            },
        ],
    ];
    $listEmptyMessage = 'No unread emails.';
    $listRowLink = static fn(array $row): array => ['href' => $emailLink($row)];
    require __DIR__ . '/../../partials/tables/table.php';
};

renderPageStart('Dashboard', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraStyles' => $hasCalendarAssets ? [
        $fullCalendarUrl . '/skeleton.css',
        $fullCalendarUrl . '/themes/monarch/theme.css',
        $fullCalendarUrl . '/themes/monarch/palettes/purple.css',
        BASE_PATH . '/app/public/css/dashboard-calendar.css',
    ] : [],
    'extraScripts' => $dashboardScripts,
]);
?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-5">
            <div class="level-left">
              <h1 class="title is-3">Dashboard</h1>
              <p class="subtitle is-6">Signed in as <?php echo htmlspecialchars((string) (($currentUser['display_name'] ?? '') !== '' ? $currentUser['display_name'] : ($currentUser['username'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
          </div>
          <div class="columns is-variable is-4">
            <div class="column is-full-mobile is-half-tablet" data-calendar-column>
              <?php if (($calendarError ?? '') !== ''): ?>
                <div class="notification is-danger" role="alert"><?php echo htmlspecialchars($calendarError, ENT_QUOTES, 'UTF-8'); ?></div>
              <?php elseif (empty($calendarTeams)): ?>
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
            <div class="column is-full-mobile is-half-tablet">
              <div class="box" data-unread-email-card>
                <h2 class="title is-4">Unread emails</h2>
                <?php if (($unreadEmailsError ?? '') !== ''): ?>
                  <div class="notification is-danger" role="alert"><?php echo htmlspecialchars($unreadEmailsError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                  <?php $renderUnreadEmailTable($unreadEmails ?? []); ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
