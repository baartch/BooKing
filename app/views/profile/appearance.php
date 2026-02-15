<?php
?>
<div class="tab-panel <?php echo $activeTab === 'appearance' ? '' : 'is-hidden'; ?>" data-tab-panel="appearance" role="tabpanel">
  <div class="columns is-multiline">
    <div class="column is-12">
      <div class="box">
        <h2 class="title is-5">Appearance</h2>
        <div class="field">
          <label class="label" for="appearance_theme">Theme</label>
          <div class="control">
            <div class="select">
              <select id="appearance_theme" name="appearance_theme" data-appearance-theme>
                <option value="system">System</option>
                <option value="light">Light</option>
                <option value="dark">Dark</option>
              </select>
            </div>
          </div>
          <p class="help">Choose a theme preference for this browser.</p>
        </div>
      </div>
    </div>
  </div>
</div>
