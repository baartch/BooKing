<?php
$cancelUrl = $cancelUrl ?? (BASE_PATH . '/app/controllers/team/index.php?tab=shows');
$searchQuery = $searchQuery ?? '';
$showLinks = $showLinks ?? [];

$linkEditorLinks = [];
foreach ($showLinks as $link) {
    $linkEditorLinks[] = [
        'type' => (string) ($link['type'] ?? ''),
        'id' => (int) ($link['id'] ?? 0),
        'label' => (string) ($link['label'] ?? ''),
    ];
}

$localModalId = 'link-editor-show-0';
if ($isEdit && $editShow) {
    $localModalId = 'link-editor-show-' . (int) $editShow['id'];
}
?>
<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <h2 class="title is-5"><?php echo $isEdit ? 'Update Show' : 'Add Show'; ?></h2>
    </div>
  </div>

  <form method="POST" action="<?php echo htmlspecialchars(BASE_PATH . '/app/controllers/team/shows_save.php'); ?>">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $isEdit ? 'update_show' : 'create_show'; ?>">
    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
    <input type="hidden" name="team_id" value="<?php echo isset($activeTeamId) ? (int) $activeTeamId : 0; ?>">
    <?php if ($isEdit && $editShow): ?>
      <input type="hidden" name="show_id" value="<?php echo (int) $editShow['id']; ?>">
    <?php endif; ?>

    <div class="detail-meta mb-4">
      <div class="detail-meta-info">
        <div class="detail-meta-row">
          <span class="detail-meta-label">Links:</span>
          <span class="detail-meta-value detail-link-list">
            <?php if (empty($showLinks)): ?>
              <span class="has-text-grey is-size-7">No links yet</span>
            <?php else: ?>
              <?php foreach ($showLinks as $link): ?>
                <span class="detail-link-pill">
                  <span><?php echo htmlspecialchars((string) ($link['label'] ?? '')); ?></span>
                </span>
              <?php endforeach; ?>
            <?php endif; ?>
            <a href="#" class="detail-link-edit" data-link-editor-trigger data-link-editor-modal-id="<?php echo htmlspecialchars($localModalId); ?>" title="Edit links">
              <span class="icon is-small"><i class="fa-solid fa-pen fa-2xs"></i></span>
            </a>
          </span>
        </div>
      </div>
    </div>

    <div data-link-editor-collector id="show-link-collector">
      <?php foreach ($showLinks as $link): ?>
        <input type="hidden" name="link_items[]" value="<?php echo htmlspecialchars((string) ($link['type'] ?? '') . ':' . (int) ($link['id'] ?? 0)); ?>">
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="link_type" id="show_fallback_link_type" value="">
    <input type="hidden" name="link_id" id="show_fallback_link_id" value="">

    <div class="table-container">
      <table class="table is-fullwidth">
        <tbody>
          <tr>
            <th>Name</th>
            <td><input type="text" id="show_name" name="name" class="input" value="<?php echo htmlspecialchars((string) ($formValues['name'] ?? '')); ?>"></td>
          </tr>
          <tr>
            <th>Date *</th>
            <td><input type="date" id="show_date" name="show_date" class="input" required value="<?php echo htmlspecialchars((string) ($formValues['show_date'] ?? '')); ?>"></td>
          </tr>
          <tr>
            <th>Time</th>
            <td><input type="time" id="show_time" name="show_time" class="input" value="<?php echo htmlspecialchars((string) ($formValues['show_time'] ?? '')); ?>"></td>
          </tr>
          <tr>
            <th>Venue</th>
            <td><input type="text" id="show_venue_text" name="venue_text" class="input" value="<?php echo htmlspecialchars((string) ($formValues['venue_text'] ?? '')); ?>"></td>
          </tr>
          <tr>
            <th>Artist Fee</th>
            <td><input type="number" min="0" step="0.01" id="show_artist_fee" name="artist_fee" class="input" value="<?php echo htmlspecialchars((string) ($formValues['artist_fee'] ?? '')); ?>"></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="field">
      <label for="show_notes" class="label">Notes</label>
      <div class="control">
        <textarea id="show_notes" name="notes" class="textarea" rows="5"><?php echo htmlspecialchars((string) ($formValues['notes'] ?? '')); ?></textarea>
      </div>
    </div>

    <div class="buttons">
      <button type="submit" class="button is-primary"><?php echo $isEdit ? 'Update Show' : 'Add Show'; ?></button>
      <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="button">Cancel</a>
    </div>
  </form>

  <?php
    $linkEditorSourceType = 'show';
    $linkEditorSourceId = $isEdit && $editShow ? (int) $editShow['id'] : 0;
    $linkEditorMailboxId = 0;
    $linkEditorSearchTypes = 'contact,venue,email,task,show';
    $linkEditorLinks = $linkEditorLinks;
    $linkEditorLocalOnly = true;
    $linkEditorCollectorSelector = '#show-link-collector';
    require __DIR__ . '/../../../partials/link_editor_modal.php';
  ?>

  <script>
    document.addEventListener('link-editor:local-saved', function (event) {
      const detail = event && event.detail ? event.detail : null;
      if (!detail || detail.collectorSelector !== '#show-link-collector') {
        return;
      }
      const links = Array.isArray(detail.links) ? detail.links : [];
      const venueLink = links.find(function (link) { return link && link.type === 'venue'; });
      const typeInput = document.getElementById('show_fallback_link_type');
      const idInput = document.getElementById('show_fallback_link_id');
      if (!typeInput || !idInput) {
        return;
      }
      if (venueLink && venueLink.id) {
        typeInput.value = 'venue';
        idInput.value = String(venueLink.id);
      } else {
        typeInput.value = '';
        idInput.value = '';
      }
    });
  </script>
</div>
