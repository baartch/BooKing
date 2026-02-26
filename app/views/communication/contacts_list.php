<?php
/**
 * Variables expected:
 * - array $contacts
 * - int $activeContactId
 * - int $activeTeamId
 * - string $baseUrl
 * - array $baseQuery
 * - string $searchQuery
 */
require_once __DIR__ . '/../../models/core/list_helpers.php';

$listColumns = [
    buildListColumn('Name', null, static function (array $contact): string {
        $nameParts = [];
        if (!empty($contact['firstname'])) {
            $nameParts[] = $contact['firstname'];
        }
        if (!empty($contact['surname'])) {
            $nameParts[] = $contact['surname'];
        }
        $name = trim(implode(' ', $nameParts));
        return $name !== '' ? $name : '(Unnamed)';
    }, false),
    buildListColumn('Email', 'email'),
    buildListColumn('Country', 'country')
];

$listRows = $contacts;
$listEmptyMessage = 'No contacts found.';

$listRowLink = static function (array $contact) use ($baseUrl, $baseQuery): array {
    $contactId = (int) ($contact['id'] ?? 0);
    $detailLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['contact_id' => $contactId]));

    return [
        'href' => $detailLink,
        'hx-get' => $detailLink,
        'hx-target' => '#contact-detail-panel',
        'hx-swap' => 'innerHTML',
        'hx-push-url' => $detailLink
    ];
};

$listRowActions = static function (array $contact) use ($baseUrl, $baseQuery, $searchQuery, $activeTeamId): string {
    $contactId = (int) ($contact['id'] ?? 0);
    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['contact_id' => $contactId, 'mode' => 'edit']));
    ob_start();
    ?>
      <div class="buttons are-small is-justify-content-flex-end">
        <form method="GET" action="<?php echo htmlspecialchars($editLink); ?>">
          <button type="submit" class="button" aria-label="Edit contact" title="Edit contact">
            <span class="icon"><i class="fa-solid fa-pen"></i></span>
          </button>
        </form>
        <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/contact_delete.php" onsubmit="return confirm('Delete this contact?');">
          <?php renderCsrfField(); ?>
          <input type="hidden" name="contact_id" value="<?php echo (int) $contactId; ?>">
          <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
          <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>">
          <button type="submit" class="button" aria-label="Delete contact" title="Delete contact">
            <span class="icon"><i class="fa-solid fa-trash"></i></span>
          </button>
        </form>
      </div>
    <?php
    return (string) ob_get_clean();
};

$listActionsLabel = 'Actions';

require __DIR__ . '/../../partials/tables/table.php';
