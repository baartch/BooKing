<?php
/**
 * Variables expected:
 * - bool $isEdit
 * - array $formValues
 * - array $userOptions
 * - array $memberSelection
 * - array $adminSelection
 * - string $cancelUrl
 * - int $selectedTeamId
 */
$isEdit = (bool) ($isEdit ?? false);
$cancelUrl = $cancelUrl ?? (BASE_PATH . '/app/controllers/admin/index.php?tab=teams');
?>
<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <h2 class="title is-5"><?php echo $isEdit ? 'Edit Team' : 'Create Team'; ?></h2>
    </div>
  </div>

  <form method="POST" action="">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $isEdit ? 'update_team' : 'create_team'; ?>">
    <input type="hidden" name="tab" value="teams">
    <?php if ($isEdit): ?>
      <input type="hidden" name="team_id" value="<?php echo (int) ($selectedTeamId ?? 0); ?>">
    <?php endif; ?>

    <div class="table-container">
      <table class="table is-fullwidth">
        <tbody>
          <tr>
            <th>Team name *</th>
            <td>
              <div class="control">
                <input type="text" id="team_name" name="team_name" class="input" required value="<?php echo htmlspecialchars((string) ($formValues['team_name'] ?? '')); ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>Description</th>
            <td>
              <div class="control">
                <input type="text" id="team_description" name="team_description" class="input" value="<?php echo htmlspecialchars((string) ($formValues['team_description'] ?? '')); ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>Members</th>
            <td>
              <div class="select is-multiple is-fullwidth">
                <select name="team_member_ids[]" multiple size="6" aria-label="Team members">
                  <?php foreach ($userOptions as $userId => $label): ?>
                    <option value="<?php echo (int) $userId; ?>" <?php echo in_array((int) $userId, $memberSelection, true) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars((string) $label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </td>
          </tr>
          <tr>
            <th>Admins</th>
            <td>
              <div class="select is-multiple is-fullwidth">
                <select name="team_admin_ids[]" multiple size="6" aria-label="Team admins">
                  <?php foreach ($userOptions as $userId => $label): ?>
                    <option value="<?php echo (int) $userId; ?>" <?php echo in_array((int) $userId, $adminSelection, true) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars((string) $label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <p class="help">Admins cannot be selected as members.</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="buttons">
      <button type="submit" class="button is-primary"><?php echo $isEdit ? 'Save' : 'Create'; ?></button>
      <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="button">Cancel</a>
    </div>
  </form>
</div>
