<?php
/**
 * Variables expected:
 * - bool $isEditing
 * - array $editUser
 */
$editUser = $editUser ?? null;
$isEditing = (bool) $isEditing;
$editUserId = $isEditing && $editUser ? (int) $editUser['id'] : 0;
$cancelUrl = BASE_PATH . '/app/controllers/admin/index.php?tab=users';
?>
<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <h2 class="title is-5"><?php echo $isEditing ? 'Edit User' : 'Create User'; ?></h2>
    </div>
  </div>

  <form method="POST" action="">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $isEditing ? 'update_user' : 'create'; ?>">
    <input type="hidden" name="tab" value="users">
    <?php if ($isEditing): ?>
      <input type="hidden" name="user_id" value="<?php echo $editUserId; ?>">
    <?php endif; ?>

    <div class="table-container">
      <table class="table is-fullwidth">
        <tbody>
          <tr>
            <th>Email</th>
            <td>
              <div class="control">
                <input type="email" id="admin_username" name="username" class="input" required value="<?php echo $isEditing && $editUser ? htmlspecialchars($editUser['username']) : ''; ?>">
              </div>
            </td>
          </tr>
          <tr>
            <th>Displayname</th>
            <td>
              <div class="control">
                <input type="text" id="admin_display_name" name="display_name" class="input" maxlength="120" value="<?php echo $isEditing && $editUser ? htmlspecialchars((string) ($editUser['display_name'] ?? '')) : ''; ?>">
              </div>
              <p class="help">Shown in the UI. Leave empty to fall back to email.</p>
            </td>
          </tr>
          <tr>
            <th>Role</th>
            <td>
              <div class="control">
                <div class="select is-fullwidth">
                  <select id="admin_role" name="role" <?php echo $isEditing && ($currentUser['user_id'] ?? 0) === $editUserId ? 'disabled' : ''; ?>>
                    <option value="agent" <?php echo $isEditing && $editUser && $editUser['role'] === 'agent' ? 'selected' : ''; ?>>Agent</option>
                    <option value="admin" <?php echo $isEditing && $editUser && $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                  </select>
                </div>
                <?php if ($isEditing && ($currentUser['user_id'] ?? 0) === $editUserId): ?>
                  <input type="hidden" name="role" value="<?php echo $isEditing && $editUser ? htmlspecialchars($editUser['role']) : 'agent'; ?>">
                <?php endif; ?>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="buttons">
      <button type="submit" class="button is-primary"><?php echo $isEditing ? 'Save' : 'Create'; ?></button>
      <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="button">Cancel</a>
    </div>
  </form>
</div>
