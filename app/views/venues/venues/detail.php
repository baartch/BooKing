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
    if (!empty($activeVenue['contact_email'])) {
        $contactValue .= ($contactValue !== '' ? "\n" : '') . $activeVenue['contact_email'];
    }
    if (!empty($activeVenue['contact_phone'])) {
        $contactValue .= ($contactValue !== '' ? "\n" : '') . $activeVenue['contact_phone'];
    }

    $detailRows = [
        buildDetailRow('Address', (string) ($activeVenue['address'] ?? '')),
        buildDetailRow('City', (string) ($activeVenue['city'] ?? '')),
        buildDetailRow('State', (string) ($activeVenue['state'] ?? '')),
        buildDetailRow('Postal Code', (string) ($activeVenue['postal_code'] ?? '')),
        buildDetailRow('Country', (string) ($activeVenue['country'] ?? '')),
        buildDetailRow('Contact', $contactValue),
        buildDetailRow('Website', (string) ($activeVenue['website'] ?? '')),
        buildDetailRow('Notes', (string) ($activeVenue['notes'] ?? '')),
        buildDetailRow('Created', (string) ($activeVenue['created_at'] ?? '')),
        buildDetailRow('Updated', (string) ($activeVenue['updated_at'] ?? ''))
    ];
}
?>

<?php require __DIR__ . '/../../../partials/tables/detail.php'; ?>
