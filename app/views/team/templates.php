<?php
/** @var string $activeTab */
?>
<div class="tab-panel <?php echo $activeTab === 'templates' ? '' : 'is-hidden'; ?>" data-tab-panel="templates" role="tabpanel">
  <?php if (!empty($notice['templates'])): ?>
    <div class="notification"><?php echo htmlspecialchars($notice['templates']); ?></div>
  <?php endif; ?>

  <?php foreach ($errors['templates'] as $error): ?>
    <div class="notification"><?php echo htmlspecialchars($error); ?></div>
  <?php endforeach; ?>

  <div class="level mb-4">
    <div class="level-left">
      <h2 class="title is-4">Templates</h2>
    </div>
    <div class="level-right">
      <a href="<?php echo BASE_PATH; ?>/app/controllers/team/template_form.php" class="button is-primary">Add Template</a>
    </div>
  </div>

  <div class="box">
    <h3 class="title is-5">Email Templates</h3>
    <?php if (!$templates): ?>
      <p>No templates configured yet.</p>
    <?php else: ?>
      <div class="table-container">
        <table class="table is-fullwidth is-striped is-hoverable" data-templates-table>
          <thead>
            <tr>
              <th>Team</th>
              <th>Name</th>
              <th>Subject</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($templates as $template): ?>
              <tr>
                <td><?php echo htmlspecialchars($template['team_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($template['name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($template['subject'] ?? ''); ?></td>
                <td>
                  <div class="buttons are-small">
                    <a href="<?php echo BASE_PATH; ?>/app/controllers/team/template_form.php?edit_template_id=<?php echo (int) $template['id']; ?>" class="button" aria-label="Edit template" title="Edit template">
                      <span class="icon"><i class="fa-solid fa-pen"></i></span>
                    </a>
                    <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/team/index.php?tab=templates" onsubmit="return confirm('Delete this template?');">
                      <?php renderCsrfField(); ?>
                      <input type="hidden" name="action" value="delete_template">
                      <input type="hidden" name="template_id" value="<?php echo (int) $template['id']; ?>">
                      <button type="submit" class="button" aria-label="Delete template" title="Delete template">
                        <span class="icon"><i class="fa-solid fa-trash"></i></span>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
