<?php
/**
 * Variables expected:
 * - ?array $activeUser
 * - array $activeUserTeams
 * - string $baseUrl
 * - array $baseQuery
 */
require_once __DIR__ . '/../../src-php/core/list_helpers.php';

$detailTitle = null;
$detailSubtitle = null;
$detailActionsHtml = null;
$detailRows = [];
$detailEmptyMessage = 'Select a user to see the details.';

if ($activeUser) {
    $detailTitle = (string) ($activeUser['display_name'] ?? $activeUser['username'] ?? '');
    $detailSubtitle = (string) ($activeUser['username'] ?? '');

    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['edit_user_id' => (int) $activeUser['id']]));
    $detailActionsHtml = '<a class="button is-small" href="' . htmlspecialchars($editLink) . '">Edit</a>';

    $detailRows = [
        buildDetailRow('Email', (string) ($activeUser['username'] ?? '')),
        buildDetailRow('Displayname', (string) ($activeUser['display_name'] ?? '')),
        buildDetailRow('Role', ucfirst((string) ($activeUser['role'] ?? ''))),
        buildDetailRow('Teams', $activeUserTeams ? implode(', ', $activeUserTeams) : 'No teams'),
        buildDetailRow('Created', (string) ($activeUser['created_at'] ?? ''))
    ];
}
?>

<?php require __DIR__ . '/../../partials/tables/detail.php'; ?>
