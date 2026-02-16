<?php
/**
 * Variables expected:
 * - array $venues
 * - int $venuesPage
 * - int $venuesPerPage
 * - string $venuesQuery
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$mapIcon = '<span class="icon"><i class="fa-solid fa-location-dot"></i></span>';

$listColumns = [
    buildListColumn('Marker Icon', null, static function (array $venue) use ($mapIcon): string {
        $latitude = $venue['latitude'] ?? null;
        $longitude = $venue['longitude'] ?? null;
        if (empty($latitude) || empty($longitude)) {
            return '';
        }
        $lat = number_format((float) $latitude, 6, '.', '');
        $lng = number_format((float) $longitude, 6, '.', '');
        $mapLink = BASE_PATH . '/app/controllers/map/index.php?' . http_build_query([
            'lat' => $lat,
            'lng' => $lng,
            'zoom' => 13
        ]);

        return '<a href="' . htmlspecialchars($mapLink) . '" class="icon" aria-label="Open map" title="Open map">' . $mapIcon . '</a>';
    }, true),
    buildListColumn('Name', 'name'),
    buildListColumn('City', null, static function (array $venue): string {
        return (string) ($venue['city'] ?? '');
    }),
    buildListColumn('Country', null, static function (array $venue): string {
        return (string) ($venue['country'] ?? '');
    }),
    buildListColumn('Contact', null, static function (array $venue): string {
        $buttons = [];
        if (!empty($venue['website'])) {
            $websiteUrl = (string) $venue['website'];
            $buttons[] = '<div class="dropdown" data-email-menu>'
                . '<div class="dropdown-trigger">'
                . '<button type="button" class="button is-small" aria-label="Website" title="Website" aria-haspopup="true" data-email-menu-trigger>'
                . '<span class="icon"><i class="fa-solid fa-globe"></i></span>'
                . '</button>'
                . '</div>'
                . '<div class="dropdown-menu" role="menu">'
                . '<div class="dropdown-content">'
                . '<a class="dropdown-item" href="' . htmlspecialchars($websiteUrl) . '" target="_blank" rel="noopener noreferrer">Open</a>'
                . '<button type="button" class="dropdown-item" data-copy-value="' . htmlspecialchars($websiteUrl) . '">Copy URL</button>'
                . '</div>'
                . '</div>'
                . '</div>';
        }
        if (!empty($venue['contact_email'])) {
            $emailAddress = (string) $venue['contact_email'];
            $composeUrl = BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query([
                'tab' => 'email',
                'compose' => 1,
                'to' => $emailAddress
            ]);
            $buttons[] = '<div class="dropdown" data-email-menu>'
                . '<div class="dropdown-trigger">'
                . '<button type="button" class="button is-small" aria-label="Email" title="Email" aria-haspopup="true" data-email-menu-trigger>'
                . '<span class="icon"><i class="fa-solid fa-envelope"></i></span>'
                . '</button>'
                . '</div>'
                . '<div class="dropdown-menu" role="menu">'
                . '<div class="dropdown-content">'
                . '<a class="dropdown-item" href="' . htmlspecialchars($composeUrl) . '">Send eMail</a>'
                . '<button type="button" class="dropdown-item" data-copy-email="' . htmlspecialchars($emailAddress) . '">Copy eMail address</button>'
                . '</div>'
                . '</div>'
                . '</div>';
        }
        if (!empty($venue['contact_phone'])) {
            $phoneNumber = (string) $venue['contact_phone'];
            $buttons[] = '<div class="dropdown" data-email-menu>'
                . '<div class="dropdown-trigger">'
                . '<button type="button" class="button is-small" aria-label="Phone" title="Phone" aria-haspopup="true" data-email-menu-trigger>'
                . '<span class="icon"><i class="fa-solid fa-phone"></i></span>'
                . '</button>'
                . '</div>'
                . '<div class="dropdown-menu" role="menu">'
                . '<div class="dropdown-content">'
                . '<a class="dropdown-item" href="tel:' . htmlspecialchars($phoneNumber) . '">Call</a>'
                . '<button type="button" class="dropdown-item" data-copy-value="' . htmlspecialchars($phoneNumber) . '">Copy phone number</button>'
                . '</div>'
                . '</div>'
                . '</div>';
        }

        if (!$buttons) {
            return '';
        }

        return '<div class="buttons are-small">' . implode('', $buttons) . '</div>';
    }, true)
];

$listRows = $venues;
$listEmptyMessage = 'No venues found.';

$listRowLink = static function (array $venue) use ($venuesPage, $venuesPerPage, $venuesQuery): array {
    $venueId = (int) ($venue['id'] ?? 0);
    $params = [
        'venue_id' => $venueId,
        'page' => $venuesPage,
        'per_page' => $venuesPerPage
    ];
    if ($venuesQuery !== '') {
        $params['filter'] = $venuesQuery;
    }
    $detailLink = BASE_PATH . '/app/controllers/venues/index.php?' . http_build_query($params);

    return [
        'href' => $detailLink,
        'hx-get' => $detailLink,
        'hx-target' => '#venues-detail-panel',
        'hx-swap' => 'innerHTML',
        'hx-push-url' => $detailLink
    ];
};

$listRowActions = static function (array $venue) use ($venuesPage, $venuesPerPage, $venuesQuery, $currentUser): string {
    $venueId = (int) ($venue['id'] ?? 0);
    $editLink = BASE_PATH . '/app/controllers/venues/add.php?edit=' . $venueId;
    $detailLink = BASE_PATH . '/app/controllers/venues/index.php?' . http_build_query([
        'venue_id' => $venueId,
        'page' => $venuesPage,
        'per_page' => $venuesPerPage,
        'filter' => $venuesQuery !== '' ? $venuesQuery : null
    ]);
    ob_start();
    ?>
      <div class="buttons are-small is-justify-content-flex-end">
        <a class="button" href="<?php echo htmlspecialchars($editLink); ?>" aria-label="Edit venue" title="Edit venue">
          <span class="icon"><i class="fa-solid fa-pen"></i></span>
        </a>
        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
          <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/venues/index.php" onsubmit="return confirm('Delete this venue?');">
            <?php renderCsrfField(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="venue_id" value="<?php echo $venueId; ?>">
            <button type="submit" class="button" aria-label="Delete venue" title="Delete venue">
              <span class="icon"><i class="fa-solid fa-trash"></i></span>
            </button>
          </form>
        <?php endif; ?>
      </div>
    <?php
    return (string) ob_get_clean();
};

$listActionsLabel = 'Actions';

require __DIR__ . '/../../../partials/tables/table.php';
