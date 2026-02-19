<?php if (($currentUser['role'] ?? '') === 'admin'): ?>
  <div class="modal" data-import-modal data-initial-open="<?php echo $showImportModal ? 'true' : 'false'; ?>">
    <div class="modal-background" data-import-close></div>
    <div class="modal-card venue-import-modal">
      <header class="modal-card-head">
        <p class="modal-card-title">Import Venues (JSON)</p>
        <button class="delete" aria-label="close" data-import-close></button>
      </header>
      <section class="modal-card-body">
        <form method="POST" action="" id="import_form">
          <?php renderCsrfField(); ?>
          <input type="hidden" name="action" value="import">
          <div class="columns is-variable is-4">
            <div class="column is-7">
              <div class="field">
                <div class="control">
                  <textarea class="textarea" name="import_json" placeholder="Paste JSON here" rows="12"><?php echo htmlspecialchars($importPayload); ?></textarea>
                </div>
              </div>
            </div>
            <div class="column is-5">
              <p class="help">Example JSON ("url" or "website" is accepted)</p>
              <pre class="venue-import-example"><code>[
  {
    "name": "Example Venue",
    "address": "Street 1",
    "postal_code": "8000",
    "city": "Zurich",
    "state": "ZH",
    "country": "CH",
    "latitude": 47.3769,
    "longitude": 8.5417,
    "type": "Club",
    "website": "https://example.com",
    "person": "Maria Example",
    "phone": "+41 44 123 45 67",
    "email": "hello@example.com",
    "notes": "Door code 1234"
  }
]</code></pre>
            </div>
          </div>
        </form>
      </section>
      <footer class="modal-card-foot">
        <button type="button" class="button" data-import-close>Close</button>
        <button type="submit" class="button" form="import_form">Import</button>
      </footer>
    </div>
  </div>
<?php endif; ?>
