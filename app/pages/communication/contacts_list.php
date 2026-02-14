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
?>
<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <h2 class="title is-5">Contacts</h2>
    </div>
    <div class="level-right">
      <form method="GET" action="<?php echo htmlspecialchars($baseUrl); ?>" data-contacts-search-form>
        <input type="hidden" name="tab" value="contacts">
        <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>">
        <div class="field has-addons">
          <div class="control has-icons-left is-expanded">
            <input
              type="text"
              class="input"
              name="q"
              placeholder="Search contacts"
              value="<?php echo htmlspecialchars($searchQuery); ?>"
            >
            <span class="icon is-left"><i class="fa-solid fa-magnifying-glass"></i></span>
          </div>
          <p class="control">
            <span class="button is-static">Ctrl+K</span>
          </p>
        </div>
      </form>
    </div>
  </div>
  <div class="level mb-3">
    <div class="level-left"></div>
    <div class="level-right">
      <a href="<?php echo htmlspecialchars($baseUrl . '?' . http_build_query(array_merge($baseQuery, ['contact_id' => 0, 'team_id' => $activeTeamId]))); ?>" class="button is-primary">Add Contact</a>
    </div>
  </div>

  <?php if (!$contacts): ?>
    <p>No contacts found.</p>
  <?php else: ?>
    <div class="table-container">
      <table class="table is-fullwidth is-striped is-hoverable">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>City</th>
            <th>Country</th>
            <th>Updated</th>
            <th class="has-text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contacts as $contact): ?>
            <?php
              $nameParts = [];
              if (!empty($contact['firstname'])) {
                  $nameParts[] = $contact['firstname'];
              }
              if (!empty($contact['surname'])) {
                  $nameParts[] = $contact['surname'];
              }
              $name = trim(implode(' ', $nameParts));
              $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['contact_id' => (int) $contact['id'], 'team_id' => $activeTeamId]));
              $isActive = (int) $contact['id'] === $activeContactId;
              $updatedLabel = '';
              if (!empty($contact['updated_at'])) {
                  $updatedTime = strtotime((string) $contact['updated_at']);
                  if ($updatedTime !== false) {
                      $updatedLabel = date('Y-m-d', $updatedTime);
                  }
              }
            ?>
            <tr class="<?php echo $isActive ? 'is-selected' : ''; ?>">
              <td><?php echo htmlspecialchars($name !== '' ? $name : '(Unnamed)'); ?></td>
              <td><?php echo htmlspecialchars((string) ($contact['email'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string) ($contact['phone'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string) ($contact['city'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars((string) ($contact['country'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars($updatedLabel); ?></td>
              <td class="has-text-right">
                <div class="buttons are-small is-justify-content-flex-end">
                  <a class="button" href="<?php echo htmlspecialchars($editLink); ?>" aria-label="Edit contact" title="Edit contact">
                    <span class="icon"><i class="fa-solid fa-pen"></i></span>
                  </a>
                  <form method="POST" action="<?php echo BASE_PATH; ?>/app/routes/communication/delete_contact.php" onsubmit="return confirm('Delete this contact?');">
                    <?php renderCsrfField(); ?>
                    <input type="hidden" name="contact_id" value="<?php echo (int) $contact['id']; ?>">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <input type="hidden" name="team_id" value="<?php echo (int) $activeTeamId; ?>">
                    <button type="submit" class="button" aria-label="Delete contact" title="Delete contact">
                      <span class="icon"><i class="fa-solid fa-trash"></i></span>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
