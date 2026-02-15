<?php
?>
<?php renderPageStart($editVenue ? 'Edit Venue' : 'Add Venue', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/app/public/js/venue-form.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <div>
                <h1 class="title is-3"><?php echo $editVenue ? 'Edit Venue' : 'Add Venue'; ?></h1>
                <p class="subtitle is-6">Manage venue details below.</p>
              </div>
            </div>
          </div>

          <?php if ($notice): ?>
            <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <?php if ($mapboxSearchNotice !== ''): ?>
            <div class="notification"><?php echo htmlspecialchars($mapboxSearchNotice); ?></div>
          <?php endif; ?>

          <form method="GET" action="" id="mapbox_search_form" class="is-hidden">
            <input type="hidden" name="mapbox_search" value="1">
            <input type="hidden" name="mapbox_address" id="mapbox_address" value="<?php echo htmlspecialchars($mapboxSearchAddress !== '' ? $mapboxSearchAddress : $formValues['address']); ?>">
            <input type="hidden" name="mapbox_city" id="mapbox_city" value="<?php echo htmlspecialchars($mapboxSearchCity !== '' ? $mapboxSearchCity : $formValues['city']); ?>">
            <input type="hidden" name="mapbox_country" id="mapbox_country" value="<?php echo htmlspecialchars($mapboxSearchCountry !== '' ? $mapboxSearchCountry : $formValues['country']); ?>">
            <?php if ($editVenue): ?>
              <input type="hidden" name="edit" value="<?php echo (int) $editVenue['id']; ?>">
            <?php endif; ?>
            <input type="hidden" name="name" id="mapbox_name" value="<?php echo htmlspecialchars($formValues['name']); ?>">
            <input type="hidden" name="postal_code" id="mapbox_postal_code" value="<?php echo htmlspecialchars($formValues['postal_code']); ?>">
            <input type="hidden" name="state" id="mapbox_state" value="<?php echo htmlspecialchars($formValues['state']); ?>">
            <input type="hidden" name="latitude" id="mapbox_latitude" value="<?php echo htmlspecialchars($formValues['latitude']); ?>">
            <input type="hidden" name="longitude" id="mapbox_longitude" value="<?php echo htmlspecialchars($formValues['longitude']); ?>">
            <input type="hidden" name="type" id="mapbox_type" value="<?php echo htmlspecialchars($formValues['type']); ?>">
            <input type="hidden" name="contact_email" id="mapbox_contact_email" value="<?php echo htmlspecialchars($formValues['contact_email']); ?>">
            <input type="hidden" name="contact_phone" id="mapbox_contact_phone" value="<?php echo htmlspecialchars($formValues['contact_phone']); ?>">
            <input type="hidden" name="contact_person" id="mapbox_contact_person" value="<?php echo htmlspecialchars($formValues['contact_person']); ?>">
            <input type="hidden" name="capacity" id="mapbox_capacity" value="<?php echo htmlspecialchars($formValues['capacity']); ?>">
            <input type="hidden" name="website" id="mapbox_website" value="<?php echo htmlspecialchars($formValues['website']); ?>">
            <input type="hidden" name="notes" id="mapbox_notes" value="<?php echo htmlspecialchars($formValues['notes']); ?>">
          </form>

          <div class="box mb-4">
            <form method="GET" action="" class="columns is-multiline is-vcentered">
              <div class="column is-5">
                <label for="web_search" class="label">Search the web</label>
                <div class="control has-icons-left">
                  <input type="text" id="web_search" name="web_search" class="input" placeholder="Search the web for venue info" value="<?php echo htmlspecialchars($webSearchQuery); ?>">
                  <span class="icon is-left"><i class="fa-solid fa-magnifying-glass"></i></span>
                </div>
              </div>
              <div class="column is-3">
                <label for="web_search_country" class="label">Search country</label>
                <div class="control">
                  <div class="select is-fullwidth">
                    <select id="web_search_country" name="web_search_country">
                      <?php foreach ($countryOptions as $country): ?>
                        <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $webSearchCountry === $country ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($country); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="column is-2">
                <label class="label">&nbsp;</label>
                <div class="control">
                  <button type="submit" class="button is-primary is-fullwidth">
                    <span class="icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <span>Search</span>
                  </button>
                </div>
              </div>
            </form>

            <form method="GET" action="" class="columns is-multiline is-vcentered">
              <div class="column is-5">
                <label for="mapbox_address_input" class="label">Address</label>
                <div class="control">
                  <input type="text" id="mapbox_address_input" name="mapbox_address" class="input" placeholder="Street address" value="<?php echo htmlspecialchars($mapboxSearchAddress !== '' ? $mapboxSearchAddress : $formValues['address']); ?>">
                </div>
              </div>
              <div class="column is-3">
                <label for="mapbox_city_input" class="label">City</label>
                <div class="control">
                  <input type="text" id="mapbox_city_input" name="mapbox_city" class="input" placeholder="City" value="<?php echo htmlspecialchars($mapboxSearchCity !== '' ? $mapboxSearchCity : $formValues['city']); ?>">
                </div>
              </div>
              <div class="column is-2">
                <label for="mapbox_country_input" class="label">Country</label>
                <div class="control">
                  <div class="select is-fullwidth">
                    <select id="mapbox_country_input" name="mapbox_country">
                      <?php foreach ($countryOptions as $country): ?>
                        <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $mapboxSearchCountry === $country ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($country); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="column is-2">
                <label class="label">&nbsp;</label>
                <div class="control">
                  <button type="submit" class="button" name="mapbox_search" value="1">
                    <span class="icon"><i class="fa-solid fa-location-crosshairs"></i></span>
                    <span>Mapbox</span>
                  </button>
                </div>
              </div>
              <?php if ($editVenue): ?>
                <input type="hidden" name="edit" value="<?php echo (int) $editVenue['id']; ?>">
              <?php endif; ?>
              <?php foreach ($fields as $field): ?>
                <?php if (!in_array($field, ['address', 'city', 'country'], true)): ?>
                  <input type="hidden" name="<?php echo htmlspecialchars($field); ?>" value="<?php echo htmlspecialchars($formValues[$field]); ?>">
                <?php endif; ?>
              <?php endforeach; ?>
            </form>
          </div>

          <div class="box">
            <form method="POST" action="" class="columns is-multiline">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="action" value="<?php echo $editVenue ? 'update' : 'create'; ?>">
              <?php if ($editVenue): ?>
                <input type="hidden" name="venue_id" value="<?php echo (int) $editVenue['id']; ?>">
              <?php endif; ?>

              <div class="column is-12">
                <h2 class="title is-5 mb-2">Venue</h2>
                <hr class="mt-0">
              </div>

              <div class="column is-4">
                <div class="field">
                  <label for="name" class="label">Name *</label>
                  <div class="control">
                    <input type="text" id="name" name="name" class="input" required value="<?php echo htmlspecialchars($formValues['name']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-2">
                <div class="field">
                  <label for="type" class="label">Type</label>
                  <div class="control">
                    <div class="select is-fullwidth">
                      <select id="type" name="type">
                        <option value="">Select type</option>
                        <?php foreach ($venueTypes as $type): ?>
                          <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $formValues['type'] === $type ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="column is-4">
                <div class="field">
                  <label for="website" class="label">Website</label>
                  <div class="control">
                    <input type="url" id="website" name="website" class="input" value="<?php echo htmlspecialchars($formValues['website']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-2">
                <div class="field">
                  <label for="capacity" class="label">Capacity</label>
                  <div class="control">
                    <input type="number" step="1" id="capacity" name="capacity" class="input" value="<?php echo htmlspecialchars($formValues['capacity']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-12 pt-5">
                <h2 class="title is-5 mb-2">Address &amp; location</h2>
                <hr class="mt-0">
              </div>

              <div class="column is-8">
                <div class="field">
                  <label for="address" class="label">Address</label>
                  <div class="field has-addons">
                    <div class="control is-expanded">
                      <input type="text" id="address" name="address" class="input" value="<?php echo htmlspecialchars($formValues['address']); ?>">
                    </div>
                    <div class="control">
                      <button type="submit" form="mapbox_search_form" class="button" id="address_mapbox_button" aria-label="Search address" disabled>
                        <span class="icon"><i class="fa-solid fa-location-crosshairs"></i></span>
                      </button>
                    </div>
                  </div>
                  <p class="help">Use the crosshair button to look up coordinates (requires address + city).</p>
                </div>
              </div>

              <div class="column is-4">
                <div class="field">
                  <label for="city" class="label">City *</label>
                  <div class="control">
                    <input type="text" id="city" name="city" class="input" required value="<?php echo htmlspecialchars($formValues['city']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-3">
                <div class="field">
                  <label for="postal_code" class="label">Postal Code</label>
                  <div class="control">
                    <input type="text" id="postal_code" name="postal_code" class="input" value="<?php echo htmlspecialchars($formValues['postal_code']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-3">
                <div class="field">
                  <label for="state" class="label">State</label>
                  <div class="control">
                    <input type="text" id="state" name="state" class="input" value="<?php echo htmlspecialchars($formValues['state']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-2">
                <div class="field">
                  <label for="country" class="label">Country</label>
                  <div class="control">
                    <div class="select is-fullwidth">
                      <select id="country" name="country">
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

              <div class="column is-2">
                <div class="field">
                  <label for="latitude" class="label">Lat</label>
                  <div class="control">
                    <input type="number" step="0.000001" id="latitude" name="latitude" class="input" value="<?php echo htmlspecialchars($formValues['latitude']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-2">
                <div class="field">
                  <label for="longitude" class="label">Lng</label>
                  <div class="control">
                    <input type="number" step="0.000001" id="longitude" name="longitude" class="input" value="<?php echo htmlspecialchars($formValues['longitude']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-12 pt-5">
                <h2 class="title is-5 mb-2">Contact</h2>
                <hr class="mt-0">
              </div>

              <div class="column is-4">
                <div class="field">
                  <label for="contact_person" class="label">Contact Person</label>
                  <div class="control">
                    <input type="text" id="contact_person" name="contact_person" class="input" value="<?php echo htmlspecialchars($formValues['contact_person']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-4">
                <div class="field">
                  <label for="contact_phone" class="label">Contact Phone</label>
                  <div class="control">
                    <input type="text" id="contact_phone" name="contact_phone" class="input" value="<?php echo htmlspecialchars($formValues['contact_phone']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-4">
                <div class="field">
                  <label for="contact_email" class="label">Contact Email</label>
                  <div class="control">
                    <input type="email" id="contact_email" name="contact_email" class="input" value="<?php echo htmlspecialchars($formValues['contact_email']); ?>">
                  </div>
                </div>
              </div>

              <div class="column is-12 pt-5">
                <h2 class="title is-5 mb-2">Notes</h2>
                <hr class="mt-0">
              </div>

              <div class="column is-12">
                <div class="field">
                  <label for="notes" class="label">Notes</label>
                  <div class="control">
                    <textarea id="notes" name="notes" class="textarea" rows="3"><?php echo htmlspecialchars($formValues['notes']); ?></textarea>
                  </div>
                </div>
              </div>

              <div class="column is-12">
                <div class="buttons">
                  <button type="submit" class="button is-primary"><?php echo $editVenue ? 'Update Venue' : 'Add Venue'; ?></button>
                  <a href="<?php echo BASE_PATH; ?>/app/controllers/venues/index.php" class="button">Cancel</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
