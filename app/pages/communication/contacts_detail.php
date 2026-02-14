<?php
/**
 * Variables expected:
 * - ?array $activeContact
 * - string $baseUrl
 * - array $baseQuery
 * - int $activeTeamId
 */
?>
<?php
require_once __DIR__ . '/../../src-php/core/list_helpers.php';

$detailTitle = null;
$detailSubtitle = null;
$detailActionsHtml = null;
$detailRows = [];
$detailEmptyMessage = 'Select a contact to see the details.';

if ($activeContact) {
    $nameParts = [];
    if (!empty($activeContact['firstname'])) {
        $nameParts[] = $activeContact['firstname'];
    }
    if (!empty($activeContact['surname'])) {
        $nameParts[] = $activeContact['surname'];
    }
    $name = trim(implode(' ', $nameParts));
    $detailTitle = $name !== '' ? $name : '(Unnamed)';

    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['contact_id' => (int) $activeContact['id'], 'mode' => 'edit']));
    $detailActionsHtml = '<a class="button is-small" href="' . htmlspecialchars($editLink) . '">Edit</a>';

    $emailValue = (string) ($activeContact['email'] ?? '');
    $websiteValue = (string) ($activeContact['website'] ?? '');

    $detailRows = [
        buildDetailRow('Firstname', (string) ($activeContact['firstname'] ?? '')),
        buildDetailRow('Surname', (string) ($activeContact['surname'] ?? '')),
        buildDetailRow(
            'Email',
            $emailValue !== '' ? '<a href="mailto:' . htmlspecialchars($emailValue) . '">' . htmlspecialchars($emailValue) . '</a>' : '',
            true
        ),
        buildDetailRow('Phone', (string) ($activeContact['phone'] ?? '')),
        buildDetailRow('Address', (string) ($activeContact['address'] ?? '')),
        buildDetailRow('Postal Code', (string) ($activeContact['postal_code'] ?? '')),
        buildDetailRow('City', (string) ($activeContact['city'] ?? '')),
        buildDetailRow('Country', (string) ($activeContact['country'] ?? '')),
        buildDetailRow(
            'Website',
            $websiteValue !== ''
                ? '<a href="' . htmlspecialchars($websiteValue) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($websiteValue) . '</a>'
                : '',
            true
        ),
        buildDetailRow('Notes', (string) ($activeContact['notes'] ?? ''))
    ];
}

require __DIR__ . '/../../partials/detail_table.php';
