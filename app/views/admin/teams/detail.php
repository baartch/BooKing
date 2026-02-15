<?php
/**
 * Variables expected:
 * - ?array $activeTeam
 * - array $activeTeamMembers
 * - array $activeTeamAdmins
 * - string $baseUrl
 * - array $baseQuery
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$detailTitle = null;
$detailSubtitle = null;
$detailActionsHtml = null;
$detailRows = [];
$detailEmptyMessage = 'Select a team to see the details.';

if ($activeTeam) {
    $detailTitle = (string) ($activeTeam['name'] ?? '');
    $detailSubtitle = $activeTeam['created_at'] ?? null;

    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['team_id' => (int) $activeTeam['id'], 'mode' => 'edit']));
    $detailActionsHtml = '<a class="button is-small" href="' . htmlspecialchars($editLink) . '">Edit</a>';

    $detailRows = [
        buildDetailRow('Description', (string) ($activeTeam['description'] ?? '')),
        buildDetailRow('Members', $activeTeamMembers ? implode(', ', $activeTeamMembers) : 'No members'),
        buildDetailRow('Admins', $activeTeamAdmins ? implode(', ', $activeTeamAdmins) : 'No admins'),
        buildDetailRow('Created', (string) ($activeTeam['created_at'] ?? ''))
    ];
}
?>

<?php require __DIR__ . '/../../../partials/tables/detail.php'; ?>
