<?php
/**
 * Variables expected:
 * - ?array $activeVenue
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$baseUrl = BASE_PATH . '/app/controllers/venues/index.php';
$activeTeamId = (int) ($activeTeamId ?? 0);
$selectedVenueRating = $selectedVenueRating ?? null;
$page = $page ?? 1;
$pageSize = $pageSize ?? 25;
$filter = $filter ?? '';
$venueTaskTriggers = $venueTaskTriggers ?? [];
$triggerNotice = $triggerNotice ?? '';

$detailTitle = null;
$detailSubtitle = null;
$detailActionsHtml = null;
$detailRows = [];
$detailEmptyMessage = 'Select a venue to see the details.';

if ($activeVenue) {
    $detailTitle = (string) ($activeVenue['name'] ?? '');
    $detailSubtitle = (string) ($activeVenue['city'] ?? '');

    $editLink = BASE_PATH . '/app/controllers/venues/add.php?edit=' . (int) $activeVenue['id'];
    $detailActionsHtml = '<a class="button is-small" href="' . htmlspecialchars($editLink) . '">Edit</a>';

    $contactValue = (string) ($activeVenue['contact_person'] ?? '');
    $contactLines = [];
    if ($contactValue !== '') {
        $contactLines[] = htmlspecialchars($contactValue);
    }

    $emailValue = trim((string) ($activeVenue['contact_email'] ?? ''));
    if ($emailValue !== '') {
        $composeUrl = BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query([
            'tab' => 'email',
            'compose' => 1,
            'to' => $emailValue
        ]);
        $contactLines[] = '<a href="' . htmlspecialchars($composeUrl) . '">' . htmlspecialchars($emailValue) . '</a>';
    }

    $phoneValue = trim((string) ($activeVenue['contact_phone'] ?? ''));
    if ($phoneValue !== '') {
        $contactLines[] = htmlspecialchars($phoneValue);
    }

    $contactValue = $contactLines ? implode('<br>', $contactLines) : '';

    $websiteValue = trim((string) ($activeVenue['website'] ?? ''));
    $websiteLabel = $websiteValue;
    if ($websiteValue !== '') {
        if (!preg_match('/^https?:\/\//i', $websiteValue)) {
            $websiteValue = 'https://' . $websiteValue;
        }
        $websiteLabel = '<a href="' . htmlspecialchars($websiteValue) . '" target="_blank" rel="noopener noreferrer">'
            . htmlspecialchars(trim((string) ($activeVenue['website'] ?? '')))
            . '</a>';
    }

    $latitude = $activeVenue['latitude'] ?? null;
    $longitude = $activeVenue['longitude'] ?? null;
    $coordinateLabel = '';
    if (!empty($latitude) && !empty($longitude)) {
        $lat = number_format((float) $latitude, 6, '.', '');
        $lng = number_format((float) $longitude, 6, '.', '');
        $mapLink = BASE_PATH . '/app/controllers/map/index.php?' . http_build_query([
            'lat' => $lat,
            'lng' => $lng,
            'zoom' => 13,
            'team_id' => $activeTeamId > 0 ? $activeTeamId : null
        ]);
        $coordinateLabel = '<a href="' . htmlspecialchars($mapLink) . '">' . htmlspecialchars($lat . ', ' . $lng) . '</a>';
    }

    $ratingHtml = '';
    if ($activeTeamId > 0) {
        $currentRating = $selectedVenueRating['rating'] ?? '';
        $currentPage = (int) ($page ?? 1);
        $currentPageSize = (int) ($pageSize ?? 25);

        ob_start();
        ?>
        <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/venues/rate.php">
          <?php renderCsrfField(); ?>
          <input type="hidden" name="venue_id" value="<?php echo (int) $activeVenue['id']; ?>">
          <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>">
          <input type="hidden" name="page" value="<?php echo (int) $currentPage; ?>">
          <input type="hidden" name="per_page" value="<?php echo (int) $currentPageSize; ?>">
          <?php if (!empty($filter)): ?>
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
          <?php endif; ?>
          <div class="field has-addons">
            <div class="control">
              <div class="select is-small">
                <select name="rating">
                  <option value="">Select rating</option>
                  <option value="A"<?php echo $currentRating === 'A' ? ' selected' : ''; ?>>A - fits perfect</option>
                  <option value="B"<?php echo $currentRating === 'B' ? ' selected' : ''; ?>>B - fits partially</option>
                  <option value="C"<?php echo $currentRating === 'C' ? ' selected' : ''; ?>>C - does not fit</option>
                </select>
              </div>
            </div>
            <div class="control">
              <button type="submit" class="button is-small">Save</button>
            </div>
          </div>
        </form>
        <?php
        $ratingHtml = (string) ob_get_clean();
    }

    $triggerRows = [];
    foreach ($venueTaskTriggers as $trigger) {
        $triggerDay = (int) ($trigger['trigger_day'] ?? 0);
        $triggerMonth = (int) ($trigger['trigger_month'] ?? 0);
        $dateLabel = $triggerDay && $triggerMonth
            ? sprintf('%02d.%02d.', $triggerDay, $triggerMonth)
            : '—';
        $triggerRows[] = [
            'title' => (string) ($trigger['title'] ?? 'Untitled'),
            'priority' => (string) ($trigger['priority'] ?? 'B'),
            'date' => $dateLabel,
            'id' => (int) ($trigger['id'] ?? 0)
        ];
    }

    $triggerFormDefaults = [
        'title' => 'Follow up ' . (string) ($activeVenue['name'] ?? ''),
        'description' => '',
        'priority' => 'B',
        'trigger_day' => (int) date('j'),
        'trigger_month' => (int) date('n')
    ];

    $detailRows = [
        buildDetailRow('Address', (string) ($activeVenue['address'] ?? '')),
        buildDetailRow('City', (string) ($activeVenue['city'] ?? '')),
        buildDetailRow('State', (string) ($activeVenue['state'] ?? '')),
        buildDetailRow('Postal Code', (string) ($activeVenue['postal_code'] ?? '')),
        buildDetailRow('Country', (string) ($activeVenue['country'] ?? '')),
        buildDetailRow('Coordinates', $coordinateLabel, $coordinateLabel !== ''),
        buildDetailRow('Team rating', $ratingHtml, true),
        buildDetailRow('Contact', $contactValue, true),
        buildDetailRow('Website', $websiteLabel, $websiteValue !== ''),
        buildDetailRow('Notes', (string) ($activeVenue['notes'] ?? '')),
        buildDetailRow('Created', (string) ($activeVenue['created_at'] ?? '')),
        buildDetailRow('Updated', (string) ($activeVenue['updated_at'] ?? ''))
    ];
}
?>

<?php require __DIR__ . '/../../../partials/tables/detail.php'; ?>

<?php if ($activeVenue && $activeTeamId > 0): ?>
  <div class="box">
    <div class="level mb-3">
      <div class="level-left">
        <h3 class="title is-5">Task triggers</h3>
      </div>
    </div>

    <?php if ($triggerNotice): ?>
      <div class="notification"><?php echo htmlspecialchars($triggerNotice); ?></div>
    <?php endif; ?>

    <?php if (!$triggerRows): ?>
      <p class="has-text-grey">No task triggers yet.</p>
    <?php else: ?>
      <div class="table-container">
        <table class="table is-fullwidth is-striped">
          <thead>
            <tr>
              <th>Title</th>
              <th>Priority</th>
              <th>Date</th>
              <th class="has-text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($triggerRows as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['priority']); ?></td>
                <td><?php echo htmlspecialchars($row['date']); ?></td>
                <td class="has-text-right">
                  <div class="buttons are-small is-justify-content-flex-end">
                    <form method="GET" action="<?php echo $baseUrl; ?>">
                      <input type="hidden" name="venue_id" value="<?php echo (int) $activeVenue['id']; ?>">
                      <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>">
                      <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
                      <input type="hidden" name="per_page" value="<?php echo (int) $pageSize; ?>">
                      <?php if ($filter !== ''): ?>
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                      <?php endif; ?>
                      <input type="hidden" name="trigger_id" value="<?php echo (int) $row['id']; ?>">
                      <button type="submit" class="button" aria-label="Edit trigger" title="Edit trigger">
                        <span class="icon"><i class="fa-solid fa-pen"></i></span>
                      </button>
                    </form>
                    <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/venues/task_trigger_delete.php" onsubmit="return confirm('Delete this trigger?');">
                      <?php renderCsrfField(); ?>
                      <input type="hidden" name="venue_id" value="<?php echo (int) $activeVenue['id']; ?>">
                      <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>">
                      <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
                      <input type="hidden" name="per_page" value="<?php echo (int) $pageSize; ?>">
                      <?php if ($filter !== ''): ?>
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                      <?php endif; ?>
                      <input type="hidden" name="task_id" value="<?php echo (int) $row['id']; ?>">
                      <button type="submit" class="button" aria-label="Delete trigger" title="Delete trigger">
                        <span class="icon"><i class="fa-solid fa-trash"></i></span>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php
      $editingTrigger = null;
      $editingTriggerId = (int) ($_GET['trigger_id'] ?? 0);
      if ($editingTriggerId > 0) {
          foreach ($venueTaskTriggers as $trigger) {
              if ((int) ($trigger['id'] ?? 0) === $editingTriggerId) {
                  $editingTrigger = $trigger;
                  break;
              }
          }
      }

      $formValues = $triggerFormDefaults;
      if ($editingTrigger) {
          $formValues = [
              'title' => (string) ($editingTrigger['title'] ?? ''),
              'description' => (string) ($editingTrigger['description'] ?? ''),
              'priority' => (string) ($editingTrigger['priority'] ?? 'B'),
              'trigger_day' => (int) ($editingTrigger['trigger_day'] ?? 0),
              'trigger_month' => (int) ($editingTrigger['trigger_month'] ?? 0)
          ];
      }
    ?>

    <div class="box mt-4">
      <div class="level mb-3">
        <div class="level-left">
          <h4 class="title is-6"><?php echo $editingTrigger ? 'Edit trigger' : 'Add trigger'; ?></h4>
        </div>
      </div>

      <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/venues/task_trigger_save.php">
        <?php renderCsrfField(); ?>
        <input type="hidden" name="action" value="<?php echo $editingTrigger ? 'update_trigger' : 'create_trigger'; ?>">
        <input type="hidden" name="venue_id" value="<?php echo (int) $activeVenue['id']; ?>">
        <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>">
        <input type="hidden" name="page" value="<?php echo (int) $page; ?>">
        <input type="hidden" name="per_page" value="<?php echo (int) $pageSize; ?>">
        <?php if ($filter !== ''): ?>
          <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
        <?php endif; ?>
        <?php if ($editingTrigger): ?>
          <input type="hidden" name="task_id" value="<?php echo (int) $editingTrigger['id']; ?>">
        <?php endif; ?>

        <div class="table-container">
          <table class="table is-fullwidth">
            <tbody>
              <tr>
                <th>Title *</th>
                <td>
                  <div class="control">
                    <input type="text" name="title" class="input" required value="<?php echo htmlspecialchars($formValues['title']); ?>">
                  </div>
                </td>
              </tr>
              <tr>
                <th>Priority *</th>
                <td>
                  <div class="control">
                    <div class="select is-fullwidth">
                      <select name="priority" required>
                        <option value="A"<?php echo $formValues['priority'] === 'A' ? ' selected' : ''; ?>>A - High</option>
                        <option value="B"<?php echo $formValues['priority'] === 'B' ? ' selected' : ''; ?>>B - Medium</option>
                        <option value="C"<?php echo $formValues['priority'] === 'C' ? ' selected' : ''; ?>>C - Low</option>
                      </select>
                    </div>
                  </div>
                </td>
              </tr>
              <tr>
                <th>Day *</th>
                <td>
                  <div class="control">
                    <div class="select">
                      <select name="trigger_day" required>
                        <?php for ($day = 1; $day <= 31; $day++): ?>
                          <option value="<?php echo $day; ?>" <?php echo $formValues['trigger_day'] === $day ? 'selected' : ''; ?>>
                            <?php echo $day; ?>
                          </option>
                        <?php endfor; ?>
                      </select>
                    </div>
                  </div>
                </td>
              </tr>
              <tr>
                <th>Month *</th>
                <td>
                  <div class="control">
                    <div class="select">
                      <select name="trigger_month" required>
                        <?php
                          $months = [
                            1 => 'January',
                            2 => 'February',
                            3 => 'March',
                            4 => 'April',
                            5 => 'May',
                            6 => 'June',
                            7 => 'July',
                            8 => 'August',
                            9 => 'September',
                            10 => 'October',
                            11 => 'November',
                            12 => 'December'
                          ];
                        ?>
                        <?php foreach ($months as $monthValue => $monthLabel): ?>
                          <option value="<?php echo $monthValue; ?>" <?php echo $formValues['trigger_month'] === $monthValue ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($monthLabel); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="field">
          <label class="label">Description</label>
          <div class="control">
            <textarea name="description" class="textarea" rows="4"><?php echo htmlspecialchars($formValues['description']); ?></textarea>
          </div>
        </div>

        <div class="buttons">
          <button type="submit" class="button is-primary"><?php echo $editingTrigger ? 'Update trigger' : 'Add trigger'; ?></button>
          <?php if ($editingTrigger): ?>
            <a href="<?php echo htmlspecialchars($baseUrl . '?' . http_build_query(array_filter([
                'venue_id' => (int) $activeVenue['id'],
                'page' => $page,
                'per_page' => $pageSize,
                'team_id' => $activeTeamId,
                'filter' => $filter !== '' ? $filter : null
            ], static fn($value) => $value !== null && $value !== ''))); ?>" class="button">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>
