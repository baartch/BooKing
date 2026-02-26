<?php
/**
 * Variables expected:
 * - bool $isEdit
 * - array $formValues
 * - string $cancelUrl
 * - string $searchQuery
 * - array $taskLinks
 */
$cancelUrl = $cancelUrl ?? (BASE_PATH . '/app/controllers/team/index.php?tab=tasks');
$searchQuery = $searchQuery ?? '';
$taskLinks = $taskLinks ?? [];
?>
<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <h2 class="title is-5"><?php echo $isEdit ? 'Update Task' : 'Add Task'; ?></h2>
    </div>
  </div>

  <form method="POST" action="<?php echo htmlspecialchars(BASE_PATH . '/app/controllers/team/tasks_save.php'); ?>">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $isEdit ? 'update_task' : 'create_task'; ?>">
    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
    <input type="hidden" name="team_id" value="<?php echo isset($activeTeamId) ? (int) $activeTeamId : 0; ?>">
    <?php if ($isEdit && $editTask): ?>
      <input type="hidden" name="task_id" value="<?php echo (int) $editTask['id']; ?>">
    <?php endif; ?>

    <?php if ($isEdit && $editTask): ?>
      <div class="detail-meta mb-4">
        <div class="detail-meta-info">
          <div class="detail-meta-row">
            <span class="detail-meta-label">Links:</span>
            <span class="detail-meta-value detail-link-list">
              <?php if (empty($taskLinks ?? [])): ?>
                <span class="has-text-grey is-size-7">No links yet</span>
              <?php else: ?>
                <?php foreach ($taskLinks as $link): ?>
                  <?php
                    $linkUrl = '#';
                    if ($link['type'] === 'contact') {
                        $linkUrl = BASE_PATH . '/app/controllers/communication/index.php?tab=contacts&contact_id=' . (int) $link['id'];
                    } elseif ($link['type'] === 'venue') {
                        $linkUrl = BASE_PATH . '/app/controllers/venues/index.php?q=' . urlencode($link['label']);
                    } elseif ($link['type'] === 'email') {
                        $linkUrl = BASE_PATH . '/app/controllers/communication/index.php?tab=email&message_id=' . (int) $link['id'];
                    } elseif ($link['type'] === 'task') {
                        $linkUrl = BASE_PATH . '/app/controllers/team/index.php?tab=tasks&task_id=' . (int) $link['id'];
                    }
                  ?>
                  <?php
                    $linkIcon = 'fa-link';
                    if ($link['type'] === 'contact') {
                        $linkIcon = 'fa-user';
                    } elseif ($link['type'] === 'venue') {
                        $linkIcon = 'fa-location-dot';
                    } elseif ($link['type'] === 'email') {
                        $linkIcon = 'fa-envelope';
                    } elseif ($link['type'] === 'task') {
                        $linkIcon = 'fa-list-check';
                    }
                  ?>
                  <a href="<?php echo htmlspecialchars($linkUrl); ?>" class="detail-link-pill">
                    <span class="icon is-small"><i class="fa-solid <?php echo htmlspecialchars($linkIcon); ?>"></i></span>
                    <span><?php echo htmlspecialchars($link['label']); ?></span>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
              <a href="#" class="detail-link-edit" data-link-editor-trigger data-link-editor-modal-id="<?php echo htmlspecialchars('link-editor-task-' . (int) ($editTask['id'] ?? 0)); ?>" title="Edit links">
                <span class="icon is-small"><i class="fa-solid fa-pen fa-2xs"></i></span>
              </a>
            </span>
          </div>
        </div>
      </div>
    <?php endif; ?>
    <div data-link-editor-collector>
      <input type="hidden" name="link_items[]" value="">
      <?php foreach ($taskLinks as $link): ?>
        <input type="hidden" name="link_items[]" value="<?php echo htmlspecialchars($link['type'] . ':' . (int) $link['id']); ?>">
      <?php endforeach; ?>
    </div>

    <div class="table-container">
      <table class="table is-fullwidth">
        <tbody>
          <tr>
            <th>Title *</th>
            <td>
              <div class="control">
                <input type="text" id="task_title" name="title" class="input" required value="<?php echo htmlspecialchars($formValues['title']); ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>Priority *</th>
            <td>
              <div class="control">
                <div class="select is-fullwidth">
                  <select id="task_priority" name="priority" required>
                    <option value="A"<?php echo $formValues['priority'] === 'A' ? ' selected' : ''; ?>>A - High</option>
                    <option value="B"<?php echo $formValues['priority'] === 'B' ? ' selected' : ''; ?>>B - Medium</option>
                    <option value="C"<?php echo $formValues['priority'] === 'C' ? ' selected' : ''; ?>>C - Low</option>
                  </select>
                </div>
              </div>
            </td>
          </tr>
          <tr>
            <th>Due</th>
            <td>
              <div class="control">
                <input type="date" id="task_due_date" name="due_date" class="input" value="<?php echo htmlspecialchars($formValues['due_date']); ?>">
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="field">
      <label for="task_description" class="label">Description</label>
      <div class="control">
        <textarea id="task_description" name="description" class="textarea" rows="6"><?php echo htmlspecialchars($formValues['description']); ?></textarea>
      </div>
    </div>

    <div class="buttons">
      <button type="submit" class="button is-primary"><?php echo $isEdit ? 'Update Task' : 'Add Task'; ?></button>
      <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="button">Cancel</a>
    </div>
  </form>

  <?php if ($isEdit && $editTask): ?>
    <?php
      $linkEditorSourceType = 'task';
      $linkEditorSourceId = (int) $editTask['id'];
      $linkEditorMailboxId = 0;
      $linkEditorSearchTypes = 'contact,venue,email,task';
      $linkEditorLinks = $linkEditorLinks ?? [];
      require __DIR__ . '/../../../partials/link_editor_modal.php';
    ?>
  <?php endif; ?>
</div>
