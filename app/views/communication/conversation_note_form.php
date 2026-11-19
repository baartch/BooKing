<?php
require_once __DIR__ . '/../../models/core/link_helpers.php';
require_once __DIR__ . '/../../partials/linked_objects.php';
$noteFormId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $noteFormId);
$noteId = (int) ($noteEdit['id'] ?? 0);
$collectorId = $noteFormId . '-links';
$pillsId = $noteFormId . '-link-pills';
$noteLinks = $noteEdit['links'] ?? [];
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
      <span class="detail-meta-value">
        <?php echo renderLinkedObjects([
            'items' => $noteLinks,
            'modalId' => 'link-editor-conversation_note-' . $noteId,
            'listId' => $pillsId,
        ]); ?>
      </span>
    </div></div>
  </div>
  <div id="<?php echo htmlspecialchars($collectorId); ?>" data-link-editor-collector data-link-editor-pills-selector="<?php echo htmlspecialchars('#' . $pillsId); ?>"><?php foreach ($noteLinks as $link): ?><input type="hidden" name="link_items[]" value="<?php echo htmlspecialchars($link['type'] . ':' . (int) $link['id']); ?>"><?php endforeach; ?></div>
  <button type="submit" class="button is-primary"><?php echo $noteId > 0 ? 'Save changes' : 'Save note'; ?></button>
</form>
<?php require __DIR__ . '/../../partials/link_editor_modal.php'; ?>
