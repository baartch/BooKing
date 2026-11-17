<?php
$noteFormId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $noteFormId);
$noteId = (int) ($noteEdit['id'] ?? 0);
$collectorId = $noteFormId . '-links';
$selectedParticipants = array_map('intval', array_column($noteEdit['participants'] ?? [], 'id'));
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
  <div class="field">
    <label class="label is-small" for="<?php echo $noteFormId; ?>-participants">Participants</label>
    <div class="control"><select class="select is-multiple is-fullwidth" id="<?php echo $noteFormId; ?>-participants" name="participants[]" multiple size="4">
      <?php foreach ($noteContactOptions as $contact): ?>
        <?php $label = trim($contact['firstname'] . ' ' . $contact['surname']); ?>
        <option value="<?php echo (int) $contact['id']; ?>" <?php echo in_array((int) $contact['id'], $selectedParticipants, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label . ($contact['email'] ? ' · ' . $contact['email'] : '')); ?></option>
      <?php endforeach; ?>
    </select></div>
    <p class="help">Hold Ctrl or Command to select multiple contacts.</p>
  </div>
  <div class="field">
    <label class="label is-small" for="<?php echo $noteFormId; ?>-new-participants">Add new participants as contacts</label>
    <div class="control"><textarea class="textarea" id="<?php echo $noteFormId; ?>-new-participants" name="new_participants" rows="2" placeholder="One contact name per line"></textarea></div>
    <?php if (!$noteTeams): ?><p class="help">No accessible team is available for creating new contacts.</p><?php endif; ?>
  </div>
  <?php if (count($noteTeams) > 1): ?>
    <div class="field"><label class="label is-small" for="<?php echo $noteFormId; ?>-team">Team for new contacts</label>
      <div class="control"><div class="select"><select id="<?php echo $noteFormId; ?>-team" name="contact_team_id"><option value="">Choose a team</option><?php foreach ($noteTeams as $team): ?><option value="<?php echo (int) $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option><?php endforeach; ?></select></div></div>
    </div>
  <?php endif; ?>
  <div class="field"><label class="label is-small">Related objects</label><div id="<?php echo $collectorId; ?>" data-link-editor-collector><?php foreach ($noteLinks as $link): ?><input type="hidden" name="link_items[]" value="<?php echo htmlspecialchars($link['type'] . ':' . (int) $link['id']); ?>"><?php endforeach; ?></div>
    <button type="button" class="button is-small" data-link-editor-trigger data-link-editor-modal-id="<?php echo htmlspecialchars('link-editor-conversation_note-' . $noteId); ?>"><span class="icon"><i class="fa-solid fa-link"></i></span><span>Choose linked objects</span></button>
  </div>
  <button type="submit" class="button is-primary"><?php echo $noteId > 0 ? 'Save changes' : 'Save note'; ?></button>
</form>
<?php require __DIR__ . '/../../partials/link_editor_modal.php'; ?>
