<?php
/**
 * Variables expected:
 * - ?array $activeVenue
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

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

    $detailRows = [
        buildDetailRow('Address', (string) ($activeVenue['address'] ?? '')),
        buildDetailRow('City', (string) ($activeVenue['city'] ?? '')),
        buildDetailRow('State', (string) ($activeVenue['state'] ?? '')),
        buildDetailRow('Postal Code', (string) ($activeVenue['postal_code'] ?? '')),
        buildDetailRow('Country', (string) ($activeVenue['country'] ?? '')),
        buildDetailRow('Contact', $contactValue, true),
        buildDetailRow('Website', $websiteLabel, $websiteValue !== ''),
        buildDetailRow('Notes', (string) ($activeVenue['notes'] ?? '')),
        buildDetailRow('Created', (string) ($activeVenue['created_at'] ?? '')),
        buildDetailRow('Updated', (string) ($activeVenue['updated_at'] ?? ''))
    ];
}
?>

<?php require __DIR__ . '/../../../partials/tables/detail.php'; ?>
