<?php
/**
 * Variables expected:
 * - ?array $activeVenue
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$activeTeamId = (int) ($activeTeamId ?? 0);
$selectedVenueRating = $selectedVenueRating ?? null;
$page = $page ?? 1;
$pageSize = $pageSize ?? 25;
$filter = $filter ?? '';

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
