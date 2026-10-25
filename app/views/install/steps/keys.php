<?php
/**
 * Optional API keys step: Mapbox (map display/geocoding) and
 * Brave Search (venue websearch/spellcheck). All fields optional.
 * Expects: $installerUrl, $csrfToken.
 */
?>
<p class="mb-4">
    The following API keys are optional — you can add or change them later under
    Admin &rarr; Settings. Without them, the map and the venue websearch are
    unavailable; everything else works.
</p>

<form method="post" action="<?php echo htmlspecialchars($installerUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="save_keys">

    <div class="field">
        <label class="label" for="mapbox_api_key_public">Mapbox public API key (map display)</label>
        <div class="control">
            <input class="input" type="text" id="mapbox_api_key_public" name="mapbox_api_key_public"
                   value="<?php echo htmlspecialchars((string) ($_POST['mapbox_api_key_public'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="mapbox_api_key_server">Mapbox server API key (geocoding)</label>
        <div class="control">
            <input class="input" type="text" id="mapbox_api_key_server" name="mapbox_api_key_server"
                   value="<?php echo htmlspecialchars((string) ($_POST['mapbox_api_key_server'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="brave_search_api_key">Brave Search API key (venue websearch)</label>
        <div class="control">
            <input class="input" type="text" id="brave_search_api_key" name="brave_search_api_key"
                   value="<?php echo htmlspecialchars((string) ($_POST['brave_search_api_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="brave_spellcheck_api_key">Brave Spellcheck API key</label>
        <div class="control">
            <input class="input" type="text" id="brave_spellcheck_api_key" name="brave_spellcheck_api_key"
                   value="<?php echo htmlspecialchars((string) ($_POST['brave_spellcheck_api_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <button type="submit" class="button is-primary is-fullwidth">Finish installation</button>
</form>
