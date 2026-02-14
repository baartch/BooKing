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
require_once __DIR__ . '/../../src-php/core/list_helpers.php';

$listColumns = [
    buildListColumn('Name', null, static function (array $contact) use ($baseUrl, $baseQuery): string {
        $nameParts = [];
        if (!empty($contact['firstname'])) {
            $nameParts[] = $contact['firstname'];
        }
        if (!empty($contact['surname'])) {
            $nameParts[] = $contact['surname'];
        }
        $name = trim(implode(' ', $nameParts));
        $contactId = (int) ($contact['id'] ?? 0);
        $detailLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['contact_id' => $contactId]));
        return '<a href="' . htmlspecialchars($detailLink) . '">' . htmlspecialchars($name !== '' ? $name : '(Unnamed)') . '</a>';
    }, true),
    buildListColumn('Email', 'email'),
    buildListColumn('Country', 'country')
];

$listRows = $contacts;
$listEmptyMessage = 'No contacts found.';
$listRowClass = static function (array $contact) use ($activeContactId): string {
    return ((int) ($contact['id'] ?? 0) === $activeContactId) ? 'is-selected' : '';
};

$listRowActions = static function (array $contact) use ($baseUrl, $baseQuery, $searchQuery, $activeTeamId): string {
    $contactId = (int) ($contact['id'] ?? 0);
    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['contact_id' => $contactId, 'mode' => 'edit']));
    ob_start();
    ?>
      <div class="buttons are-small is-justify-content-flex-end">
        <a class="button" href="<?php echo htmlspecialchars($editLink); ?>" aria-label="Edit contact" title="Edit contact">
          <span class="icon"><i class="fa-solid fa-pen"></i></span>
        </a>
        <form method="POST" action="<?php echo BASE_PATH; ?>/app/routes/communication/delete_contact.php" onsubmit="return confirm('Delete this contact?');">
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

require __DIR__ . '/../../partials/list_table.php';
