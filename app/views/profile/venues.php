<?php
?>
<div class="tab-panel <?php echo $activeTab === 'venues' ? '' : 'is-hidden'; ?>" data-tab-panel="venues" role="tabpanel">
  <div class="columns is-multiline">
    <div class="column is-12">
      <div class="box">
        <h2 class="title is-5">Venues List</h2>
        <form method="POST" action="" class="columns is-multiline">
          <?php renderCsrfField(); ?>
          <input type="hidden" name="action" value="update_page_size">
          <div class="column is-4">
            <div class="field">
              <label for="venues_page_size" class="label">Venues per page (25-500)</label>
              <div class="control">
                <input
                  type="number"
                  id="venues_page_size"
                  name="venues_page_size"
                  class="input"
                  min="<?php echo (int) $minPageSize; ?>"
                  max="<?php echo (int) $maxPageSize; ?>"
                  value="<?php echo (int) $currentPageSize; ?>"
                  required
                >
              </div>
            </div>
          </div>
          <div class="column is-12">
            <button type="submit" class="button is-primary">Update Page Size</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
