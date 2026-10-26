<?php
/**
 * Admin maintenance tab view.
 * Expects: $maintenanceState
 */
$errors = $errors ?? [];
$notice = $notice ?? '';
$state = $maintenanceState;

$installedVersion = (string) ($state['installed_version'] ?? '');
$installedLabel = $installedVersion !== '' ? $installedVersion : 'Unknown';
$latestVersion = (string) ($state['latest_version'] ?? '');
$lastCheck = (string) ($state['last_check_at'] ?? '');
$checkError = (string) ($state['check_error'] ?? '');
$available = (bool) ($state['available'] ?? false);
$lock = is_array($state['lock'] ?? null) ? $state['lock'] : null;
$lockRunning = (bool) ($state['running'] ?? false);
$requirements = is_array($state['requirements'] ?? null) ? $state['requirements'] : [];
$requirementsOk = $requirements !== [] && !in_array(false, array_map(static fn (array $row): bool => (bool) $row['ok'], $requirements), true);

$updatePossible = $available && !$lockRunning && $requirementsOk && $installedVersion !== '';
?>

<div class="box">
  <h2 class="title is-5">Maintenance</h2>
  <p class="mb-4">Keep the application up to date. New releases are checked once a day automatically.</p>

  <div class="columns is-multiline">
    <div class="column is-6">
      <table class="table is-fullwidth is-narrow mb-0">
        <tbody>
          <tr>
            <th>Installed version</th>
            <td><?php echo htmlspecialchars($installedLabel); ?></td>
          </tr>
          <tr>
            <th>Latest stable release</th>
            <td>
              <?php if ($latestVersion !== ''): ?>
                <?php echo htmlspecialchars($latestVersion); ?>
                <?php if ((string) ($state['notes_url'] ?? '') !== ''): ?>
                  <a href="<?php echo htmlspecialchars($state['notes_url']); ?>" target="_blank" rel="noopener noreferrer" class="ml-1">Release notes</a>
                <?php endif; ?>
              <?php else: ?>
                <span class="is-italic">No release checked yet</span>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th>Last check</th>
            <td><?php echo $lastCheck !== '' ? htmlspecialchars(date('Y-m-d H:i', strtotime($lastCheck)) . ' UTC') : '—'; ?></td>
          </tr>
          <tr>
            <th>Status</th>
            <td>
              <?php if ($checkError !== ''): ?>
                <span class="tag is-danger">Check failed</span>
              <?php elseif ($available): ?>
                <span class="tag is-warning">Update available</span>
              <?php else: ?>
                <span class="tag is-success">Up to date</span>
              <?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="column is-6">
      <?php if ($checkError !== ''): ?>
        <div class="notification is-danger is-light mb-4"><?php echo htmlspecialchars($checkError); ?></div>
      <?php endif; ?>
      <?php if ($lock !== null && ($lock['state'] ?? '') === 'failed'): ?>
        <div class="notification is-danger is-light mb-4">
          Last update failed<?php echo ($lock['step'] ?? '') !== '' ? ' at step: ' . htmlspecialchars($lock['step']) : ''; ?>.
          <?php echo htmlspecialchars((string) ($lock['error'] ?? '')); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" class="mb-2">
        <?php renderCsrfField(); ?>
        <input type="hidden" name="tab" value="maintenance">
        <input type="hidden" name="action" value="update_check_now">
        <button type="submit" class="button">Check now</button>
      </form>

      <?php if ($available): ?>
        <form method="POST" action="">
          <?php renderCsrfField(); ?>
          <input type="hidden" name="tab" value="maintenance">
          <input type="hidden" name="action" value="update_run">
          <button type="submit" class="button is-primary<?php echo $updatePossible ? '' : ' is-disabled'; ?>"<?php echo $updatePossible ? '' : ' disabled'; ?>>
            Update to <?php echo htmlspecialchars($latestVersion); ?>
          </button>
        </form>
        <?php if ($lockRunning): ?>
          <p class="help">An update is currently running.</p>
        <?php elseif (!$requirementsOk): ?>
          <p class="help is-danger">The server does not meet the update requirements.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
