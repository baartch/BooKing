<?php
require_once __DIR__ . '/../../models/auth/admin_check.php';

if (!isset($settingsStatus)):
?>
  <?php return; ?>
<?php endif; ?>

<div class="box">
  <div class="content">
    <h2 class="title is-5">API Keys</h2>
    <p>Store tokens used for map tiles and integrations.</p>
  </div>

  <form method="POST" action="">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="save_api_keys">
    <input type="hidden" name="tab" value="api-keys">
    <div class="field">
      <label for="brave_search_api_key" class="label">Brave Search</label>
      <div class="control">
        <input
          type="password"
          id="brave_search_api_key"
          name="brave_search_api_key"
          class="input"
          placeholder="<?php echo $settingsStatus['brave_search_api_key'] ? 'Saved' : 'Not set'; ?>"
        >
      </div>
    </div>
    <div class="field">
      <label for="brave_spellcheck_api_key" class="label">Brave Spellcheck</label>
      <div class="control">
        <input
          type="password"
          id="brave_spellcheck_api_key"
          name="brave_spellcheck_api_key"
          class="input"
          placeholder="<?php echo $settingsStatus['brave_spellcheck_api_key'] ? 'Saved' : 'Not set'; ?>"
        >
      </div>
    </div>
    <div class="field">
      <label for="mapbox_api_key_public" class="label">Mapbox Public Token</label>
      <div class="control">
        <input
          type="password"
          id="mapbox_api_key_public"
          name="mapbox_api_key_public"
          class="input"
          placeholder="<?php echo $settingsStatus['mapbox_api_key_public'] ? 'Saved' : 'Not set'; ?>"
        >
      </div>
      <p class="help">Used by the map UI. Create a public token with URL restrictions and map/tiles scopes.</p>
    </div>
    <div class="field">
      <label for="mapbox_api_key_server" class="label">Mapbox Server Token</label>
      <div class="control">
        <input
          type="password"
          id="mapbox_api_key_server"
          name="mapbox_api_key_server"
          class="input"
          placeholder="<?php echo $settingsStatus['mapbox_api_key_server'] ? 'Saved' : 'Not set'; ?>"
        >
      </div>
      <p class="help">Used for server-side geocoding/search. Use a secret token without URL restriction.</p>
    </div>
    <button type="submit" class="button is-primary">Save API Keys</button>
  </form>
</div>
