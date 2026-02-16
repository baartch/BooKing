<?php
/**
 * Variables expected:
 * - array $formValues
 * - array $countryOptions
 * - bool $isEdit
 * - ?array $editContact
 * - string $cancelUrl
 * - string $searchQuery
 * - array $contactLinks
 */
$cancelUrl = $cancelUrl ?? (BASE_PATH . '/app/controllers/communication/index.php?tab=contacts');
$searchQuery = $searchQuery ?? '';
$contactLinks = $contactLinks ?? [];
$linkIcons = [
  'contact' => 'fa-user',
  'venue' => 'fa-location-dot',
  'email' => 'fa-envelope'
];
?>
<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <h2 class="title is-5"><?php echo $isEdit ? 'Update Contact' : 'Add Contact'; ?></h2>
    </div>
  </div>

  <form method="POST" action="<?php echo htmlspecialchars(BASE_PATH . '/app/controllers/communication/contact_save.php'); ?>">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $isEdit ? 'update_contact' : 'create_contact'; ?>">
    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
    <input type="hidden" name="team_id" value="<?php echo isset($activeTeamId) ? (int) $activeTeamId : 0; ?>">
    <?php if ($isEdit && $editContact): ?>
      <input type="hidden" name="contact_id" value="<?php echo (int) $editContact['id']; ?>">
    <?php endif; ?>

    <?php if ($isEdit && $editContact): ?>
      <div class="detail-meta mb-4">
        <div class="detail-meta-info">
          <div class="detail-meta-row">
            <span class="detail-meta-label">Links:</span>
            <span class="detail-meta-value detail-link-list">
              <?php if (!$contactLinks): ?>
                <span class="has-text-grey is-size-7">No links yet</span>
              <?php else: ?>
                <?php foreach ($contactLinks as $link): ?>
                  <?php
                    $linkUrl = '#';
                    if ($link['type'] === 'contact') {
                        $linkUrl = BASE_PATH . '/app/controllers/communication/index.php?tab=contacts&contact_id=' . (int) $link['id'];
                    } elseif ($link['type'] === 'venue') {
                        $linkUrl = BASE_PATH . '/app/controllers/venues/index.php?q=' . urlencode($link['label']);
                    } elseif ($link['type'] === 'email') {
                        $linkUrl = BASE_PATH . '/app/controllers/communication/index.php?tab=email&message_id=' . (int) $link['id'];
                    }
                  ?>
                  <a href="<?php echo htmlspecialchars($linkUrl); ?>" class="detail-link-pill">
                    <span class="icon is-small"><i class="fa-solid <?php echo htmlspecialchars($linkIcons[$link['type']] ?? 'fa-link'); ?>"></i></span>
                    <span><?php echo htmlspecialchars($link['label']); ?></span>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
              <a href="#" class="detail-link-edit" data-link-editor-trigger data-link-editor-modal-id="<?php echo htmlspecialchars('link-editor-contact-' . (int) ($editContact['id'] ?? 0)); ?>" title="Edit links">
                <span class="icon is-small"><i class="fa-solid fa-pen fa-2xs"></i></span>
              </a>
            </span>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="table-container">
      <table class="table is-fullwidth">
        <tbody>
          <tr>
            <th>Firstname *</th>
            <td>
              <div class="control">
                <input type="text" id="contact_firstname" name="firstname" class="input" required value="<?php echo htmlspecialchars($formValues['firstname']); ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>Surname</th>
            <td>
              <div class="control">
                <input type="text" id="contact_surname" name="surname" class="input" value="<?php echo htmlspecialchars($formValues['surname']); ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>Address</th>
            <td>
              <div class="control">
                <input type="text" id="contact_address" name="address" class="input" value="<?php echo htmlspecialchars($formValues['address']); ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>Postal Code</th>
            <td>
              <div class="control">
                <input type="text" id="contact_postal_code" name="postal_code" class="input" value="<?php echo htmlspecialchars($formValues['postal_code']); ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>City</th>
            <td>
              <div class="control">
                <input type="text" id="contact_city" name="city" class="input" value="<?php echo htmlspecialchars($formValues['city']); ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>Country</th>
            <td>
              <div class="control">
                <div class="select is-fullwidth">
                  <select id="contact_country" name="country">
                    <option value="">Select country</option>
                    <?php foreach ($countryOptions as $country): ?>
                      <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $formValues['country'] === $country ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($country); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </td>
          </tr>
          <tr>
            <th>Email</th>
            <td>
              <div class="control">
                <input type="email" id="contact_email" name="email" class="input" value="<?php echo htmlspecialchars($formValues['email']); ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>Phone</th>
            <td>
              <div class="control">
                <input type="text" id="contact_phone" name="phone" class="input" value="<?php echo htmlspecialchars($formValues['phone']); ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>Website</th>
            <td>
              <div class="control">
                <input type="url" id="contact_website" name="website" class="input" value="<?php echo htmlspecialchars($formValues['website']); ?>">
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="field">
      <label for="contact_notes" class="label">Notes</label>
      <div class="control">
        <textarea id="contact_notes" name="notes" class="textarea" rows="6"><?php echo htmlspecialchars($formValues['notes']); ?></textarea>
      </div>
    </div>

    <div class="buttons">
      <button type="submit" class="button is-primary"><?php echo $isEdit ? 'Update Contact' : 'Add Contact'; ?></button>
      <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="button">Cancel</a>
    </div>
  </form>

  <?php if ($isEdit && $editContact): ?>
    <?php
      $linkEditorSourceType = 'contact';
      $linkEditorSourceId = (int) $editContact['id'];
      $linkEditorMailboxId = 0;
      $linkEditorSearchTypes = 'contact,venue,email';
      $linkEditorLinks = [];
      if (!empty($contactLinks)) {
          foreach ($contactLinks as $link) {
              $linkEditorLinks[] = [
                  'type' => $link['type'],
                  'id' => (int) $link['id'],
                  'label' => $link['label']
              ];
          }
      }
      require __DIR__ . '/../../partials/link_editor_modal.php';
    ?>
  <?php endif; ?>
</div>
