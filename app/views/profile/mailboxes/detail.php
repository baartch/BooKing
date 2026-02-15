<?php
/**
 * Variables expected:
 * - ?array $activeMailbox
 * - string $baseUrl
 * - array $baseQuery
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$detailTitle = null;
$detailSubtitle = null;
$detailActionsHtml = null;
$detailRows = [];
$detailEmptyMessage = 'Select a mailbox to see the details.';

if ($activeMailbox) {
    $detailTitle = (string) ($activeMailbox['name'] ?? '');
    $detailSubtitle = (string) ($activeMailbox['display_name'] ?? '');

    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['mailbox_id' => (int) $activeMailbox['id'], 'mode' => 'edit']));
    $detailActionsHtml = '<a class="button is-small" href="' . htmlspecialchars($editLink) . '">Edit</a>';

    $detailRows = [
        buildDetailRow('Team', (string) ($activeMailbox['team_name'] ?? '')),
        buildDetailRow('IMAP Host', (string) ($activeMailbox['imap_host'] ?? '')),
        buildDetailRow('IMAP Port', (string) ($activeMailbox['imap_port'] ?? '')),
        buildDetailRow('IMAP Username', (string) ($activeMailbox['imap_username'] ?? '')),
        buildDetailRow('IMAP Encryption', strtoupper((string) ($activeMailbox['imap_encryption'] ?? ''))),
        buildDetailRow('SMTP Host', (string) ($activeMailbox['smtp_host'] ?? '')),
        buildDetailRow('SMTP Port', (string) ($activeMailbox['smtp_port'] ?? '')),
        buildDetailRow('SMTP Username', (string) ($activeMailbox['smtp_username'] ?? '')),
        buildDetailRow('SMTP Encryption', strtoupper((string) ($activeMailbox['smtp_encryption'] ?? ''))),
        buildDetailRow('Delete After Retrieve', !empty($activeMailbox['delete_after_retrieve']) ? 'Yes' : 'No'),
        buildDetailRow('Store Sent on Server', !empty($activeMailbox['store_sent_on_server']) ? 'Yes' : 'No'),
        buildDetailRow('Auto Start Conversation', !empty($activeMailbox['auto_start_conversation_inbound']) ? 'Yes' : 'No')
    ];
}
?>

<?php require __DIR__ . '/../../../partials/tables/detail.php'; ?>
