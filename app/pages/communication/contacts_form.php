<?php
/**
 * Variables expected:
 * - array $formValues
 * - array $countryOptions
 * - bool $isEdit
 * - ?array $editContact
 * - string $cancelUrl
 * - string $searchQuery
 */
$cancelUrl = $cancelUrl ?? (BASE_PATH . '/app/pages/communication/index.php?tab=contacts');
$searchQuery = $searchQuery ?? '';
?>
<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <h2 class="title is-5"><?php echo $isEdit ? 'Update Contact' : 'Add Contact'; ?></h2>
    </div>
  </div>

  <form method="POST" action="<?php echo htmlspecialchars(BASE_PATH . '/app/routes/communication/save_contact.php'); ?>" class="columns is-multiline">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $isEdit ? 'update_contact' : 'create_contact'; ?>">
    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
    <?php if ($isEdit && $editContact): ?>
      <input type="hidden" name="contact_id" value="<?php echo (int) $editContact['id']; ?>">
    <?php endif; ?>

    <div class="column is-2">
      <div class="field">
        <label for="contact_firstname" class="label">Firstname *</label>
        <div class="control">
          <input type="text" id="contact_firstname" name="firstname" class="input" required value="<?php echo htmlspecialchars($formValues['firstname']); ?>">
        </div>
      </div>
    </div>

    <div class="column is-2">
      <div class="field">
        <label for="contact_surname" class="label">Surname</label>
        <div class="control">
          <input type="text" id="contact_surname" name="surname" class="input" value="<?php echo htmlspecialchars($formValues['surname']); ?>">
        </div>
      </div>
    </div>

    <div class="column is-3">
      <div class="field">
        <label for="contact_address" class="label">Address</label>
        <div class="control">
          <input type="text" id="contact_address" name="address" class="input" value="<?php echo htmlspecialchars($formValues['address']); ?>">
        </div>
      </div>
    </div>

    <div class="column is-2">
      <div class="field">
        <label for="contact_postal_code" class="label">Postal Code</label>
        <div class="control">
          <input type="text" id="contact_postal_code" name="postal_code" class="input" value="<?php echo htmlspecialchars($formValues['postal_code']); ?>">
        </div>
      </div>
    </div>

    <div class="column is-2">
      <div class="field">
        <label for="contact_city" class="label">City</label>
        <div class="control">
          <input type="text" id="contact_city" name="city" class="input" value="<?php echo htmlspecialchars($formValues['city']); ?>">
        </div>
      </div>
    </div>

    <div class="column is-1">
      <div class="field">
        <label for="contact_country" class="label">Country</label>
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
      </div>
    </div>

    <div class="column is-4">
      <div class="field">
        <label for="contact_email" class="label">Email</label>
        <div class="control">
          <input type="email" id="contact_email" name="email" class="input" value="<?php echo htmlspecialchars($formValues['email']); ?>">
        </div>
      </div>
    </div>

    <div class="column is-4">
      <div class="field">
        <label for="contact_phone" class="label">Phone</label>
        <div class="control">
          <input type="text" id="contact_phone" name="phone" class="input" value="<?php echo htmlspecialchars($formValues['phone']); ?>">
        </div>
      </div>
    </div>

    <div class="column is-4">
      <div class="field">
        <label for="contact_website" class="label">Website</label>
        <div class="control">
          <input type="url" id="contact_website" name="website" class="input" value="<?php echo htmlspecialchars($formValues['website']); ?>">
        </div>
      </div>
    </div>

    <div class="column is-12">
      <div class="field">
        <label for="contact_notes" class="label">Notes</label>
        <div class="control">
          <textarea id="contact_notes" name="notes" class="textarea" rows="6"><?php echo htmlspecialchars($formValues['notes']); ?></textarea>
        </div>
      </div>
    </div>

    <div class="column is-12">
      <div class="buttons">
        <button type="submit" class="button is-primary"><?php echo $isEdit ? 'Update Contact' : 'Add Contact'; ?></button>
        <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="button">Cancel</a>
      </div>
    </div>
  </form>
</div>
