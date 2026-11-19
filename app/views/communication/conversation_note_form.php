<?php
require_once __DIR__ . '/../../models/core/link_helpers.php';
$noteFormId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $noteFormId);
$noteId = (int) ($noteEdit['id'] ?? 0);
$collectorId = $noteFormId . '-links';
$pillsId = $noteFormId . '-link-pills';
$noteLinks = $noteEdit['links'] ?? [];
$linkIcons = getLinkIcons();
$occurredAt = $noteEdit['occurred_at'] ?? date('Y-m-d H:i:s');
$occurredAtInput = date('Y-m-d\TH:i', strtotime((string) $occurredAt));
$linkEditorSourceType = 'conversation_note';
$linkEditorSourceId = $noteId;
$linkEditorMailboxId = 0;
$linkEditorLinks = $noteLinks;
$linkEditorSearchTypes = 'contact,venue,show,task,email';
$linkEditorLocalOnly = true;
$linkEditorCollectorSelector = '#' . $collectorId;
?>
<form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/communication/conversation.php" class="mb-4">
  <?php renderCsrfField(); ?>
  <input type="hidden" name="action" value="save_note">
  <input type="hidden" name="conversation_id" value="<?php echo (int) $conversationId; ?>">
  <input type="hidden" name="note_id" value="<?php echo $noteId; ?>">
  <div class="field">
    <label class="label is-small" for="<?php echo $noteFormId; ?>-body">Note</label>
    <div class="control"><textarea class="textarea" id="<?php echo $noteFormId; ?>-body" name="body" rows="4" required><?php echo htmlspecialchars((string) ($noteEdit['body'] ?? '')); ?></textarea></div>
  </div>
  <div class="field">
    <label class="label is-small" for="<?php echo $noteFormId; ?>-date">Call date and time</label>
    <div class="control"><input class="input" id="<?php echo $noteFormId; ?>-date" type="datetime-local" name="occurred_at" value="<?php echo htmlspecialchars($occurredAtInput); ?>" required></div>
  </div>
  <div class="detail-meta mb-4">
    <div class="detail-meta-info"><div class="detail-meta-row">
      <span class="detail-meta-label">Links:</span>
      <span class="detail-meta-value detail-link-list" id="<?php echo htmlspecialchars($pillsId); ?>">
        <?php if (!$noteLinks): ?><span class="has-text-grey is-size-7">No links yet</span><?php endif; ?>
        <?php foreach ($noteLinks as $link): ?>
          <span class="detail-link-pill"><span class="icon is-small"><i class="fa-solid <?php echo htmlspecialchars($linkIcons[$link['type']] ?? 'fa-link'); ?>"></i></span><span><?php echo htmlspecialchars($link['label']); ?></span></span>
        <?php endforeach; ?>
      </span>
      <a href="#" class="detail-link-edit" data-link-editor-trigger data-link-editor-modal-id="<?php echo htmlspecialchars('link-editor-conversation_note-' . $noteId); ?>" title="Edit links" aria-label="Edit links"><span class="icon is-small"><i class="fa-solid fa-pen fa-2xs"></i></span></a>
    </div></div>
  </div>
  <div id="<?php echo htmlspecialchars($collectorId); ?>" data-link-editor-collector data-link-editor-pills-selector="<?php echo htmlspecialchars('#' . $pillsId); ?>"><?php foreach ($noteLinks as $link): ?><input type="hidden" name="link_items[]" value="<?php echo htmlspecialchars($link['type'] . ':' . (int) $link['id']); ?>"><?php endforeach; ?></div>
  <button type="submit" class="button is-primary"><?php echo $noteId > 0 ? 'Save changes' : 'Save note'; ?></button>
</form>
<?php require __DIR__ . '/../../partials/link_editor_modal.php'; ?>
