<?php
$cancelUrl = $cancelUrl ?? (BASE_PATH . '/app/controllers/team/index.php?tab=songs');
$searchQuery = $searchQuery ?? '';
?>
<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <h2 class="title is-5"><?php echo $isEdit ? 'Update Song' : 'Add Song'; ?></h2>
    </div>
  </div>

  <form method="POST" action="<?php echo htmlspecialchars(BASE_PATH . '/app/controllers/team/songs_save.php'); ?>">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $isEdit ? 'update_song' : 'create_song'; ?>">
    <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">
    <input type="hidden" name="team_id" value="<?php echo isset($activeTeamId) ? (int) $activeTeamId : 0; ?>">
    <?php if ($isEdit && $editSong): ?>
      <input type="hidden" name="song_id" value="<?php echo (int) $editSong['id']; ?>">
    <?php endif; ?>

    <div class="table-container">
      <table class="table is-fullwidth">
        <tbody>
          <tr>
            <th>Name *</th>
            <td><input type="text" id="song_name" name="name" class="input" required maxlength="255" value="<?php echo htmlspecialchars((string) ($formValues['name'] ?? '')); ?>"></td>
          </tr>
          <tr>
            <th>Duration</th>
            <td>
              <input type="text" id="song_duration" name="duration" class="input" maxlength="7" pattern="[0-9]+:[0-5][0-9]" placeholder="e.g. 3:45" value="<?php echo htmlspecialchars((string) ($formValues['duration'] ?? '')); ?>">
              <p class="help">Minutes:seconds (e.g. 3:45). Optional.</p>
            </td>
          </tr>
          <tr>
            <th>Composer *</th>
            <td><input type="text" id="song_composer" name="composer" class="input" required maxlength="255" value="<?php echo htmlspecialchars((string) ($formValues['composer'] ?? '')); ?>"></td>
          </tr>
          <tr>
            <th>Arranger *</th>
            <td><input type="text" id="song_arranger" name="arranger" class="input" required maxlength="255" value="<?php echo htmlspecialchars((string) ($formValues['arranger'] ?? '')); ?>"></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="buttons">
      <button type="submit" class="button is-primary"><?php echo $isEdit ? 'Update Song' : 'Add Song'; ?></button>
      <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="button">Cancel</a>
    </div>
  </form>
</div>
