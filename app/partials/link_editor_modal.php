<?php
/**
 * Reusable link editor modal.
 *
 * Expected variables (set before including):
 *   $linkEditorSourceType  - string: 'email', 'contact', or 'venue'
 *   $linkEditorSourceId    - int:    ID of the source record
 *   $linkEditorMailboxId   - int:    mailbox ID (only needed for email sources, 0 otherwise)
 *   $linkEditorLinks       - array:  current links [{type, id, label}, ...]
 *   $linkEditorConversationId - int|null: current conversation ID (email only)
 *   $linkEditorConversationLabel - string: current conversation label (email only)
 */

$linkEditorSourceType = $linkEditorSourceType ?? '';
$linkEditorSourceId = (int) ($linkEditorSourceId ?? 0);
$linkEditorMailboxId = (int) ($linkEditorMailboxId ?? 0);
$linkEditorLinks = $linkEditorLinks ?? [];
$linkEditorConversationId = $linkEditorConversationId ?? null;
$linkEditorConversationLabel = $linkEditorConversationLabel ?? '';
$linkEditorSearchTypes = $linkEditorSearchTypes ?? 'contact,venue';

$linkEditorSearchUrl = BASE_PATH . '/app/routes/communication/link_search.php';
$linkEditorSaveUrl = BASE_PATH . '/app/routes/communication/save_links.php';
?>
<?php
$linkEditorModalId = htmlspecialchars('link-editor-' . $linkEditorSourceType . '-' . $linkEditorSourceId);
?>
<div class="modal" data-link-editor-modal id="<?php echo $linkEditorModalId; ?>">
  <div class="modal-background" data-link-editor-close></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Edit Links</p>
      <button class="delete" aria-label="close" data-link-editor-close></button>
    </header>
    <section class="modal-card-body">
      <div
        data-link-editor
        data-source-type="<?php echo htmlspecialchars($linkEditorSourceType); ?>"
        data-source-id="<?php echo (int) $linkEditorSourceId; ?>"
        data-mailbox-id="<?php echo (int) $linkEditorMailboxId; ?>"
        data-search-url="<?php echo htmlspecialchars($linkEditorSearchUrl); ?>"
        data-save-url="<?php echo htmlspecialchars($linkEditorSaveUrl); ?>"
        data-csrf-token="<?php echo htmlspecialchars(getCsrfToken()); ?>"
        data-links="<?php echo htmlspecialchars(json_encode($linkEditorLinks, JSON_UNESCAPED_UNICODE)); ?>"
        data-conversation-id="<?php echo $linkEditorConversationId !== null ? (int) $linkEditorConversationId : ''; ?>"
        data-conversation-label="<?php echo htmlspecialchars($linkEditorConversationLabel); ?>"
        data-link-editor-types="<?php echo htmlspecialchars($linkEditorSearchTypes); ?>"
      >
        <!-- Contact / Venue links -->
        <div class="field">
          <label class="label is-small">Links</label>
          <div data-link-editor-tags class="field is-grouped is-grouped-multiline mb-2"></div>
          <div class="control has-icons-left">
            <input class="input is-small" type="text" placeholder="Search contacts, venues, or emails…" data-link-editor-search>
            <span class="icon is-small is-left"><i class="fa-solid fa-search"></i></span>
          </div>
          <div class="dropdown is-fullwidth" data-link-editor-dropdown>
            <div class="dropdown-menu" role="menu" style="position:relative; width:100%;">
              <div class="dropdown-content" data-link-editor-results></div>
            </div>
          </div>
        </div>

        <?php if ($linkEditorSourceType === 'email'): ?>
          <!-- Conversation assignment -->
          <div class="field">
            <label class="label is-small">Conversation</label>
            <div data-link-editor-conversation-tag class="mb-2"></div>
            <div class="control has-icons-left">
              <input class="input is-small" type="text" placeholder="Search conversations…" data-link-editor-conversation-search>
              <span class="icon is-small is-left"><i class="fa-solid fa-search"></i></span>
            </div>
            <div class="dropdown is-fullwidth" data-link-editor-conversation-dropdown>
              <div class="dropdown-menu" role="menu" style="position:relative; width:100%;">
                <div class="dropdown-content" data-link-editor-conversation-results></div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <p class="help is-danger is-hidden" data-link-editor-error></p>
      </div>
    </section>
    <footer class="modal-card-foot">
      <button type="button" class="button" data-link-editor-close>Cancel</button>
      <button type="button" class="button is-primary" data-link-editor-save>Save</button>
    </footer>
  </div>
</div>
