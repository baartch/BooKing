<?php
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$detailTitle = null;
$detailSubtitle = null;
$detailActionsHtml = null;
$detailRows = [];
$detailEmptyMessage = 'Select a song to see details.';

if ($activeSong) {
    $title = trim((string) ($activeSong['name'] ?? ''));
    $detailTitle = $title !== '' ? $title : 'Song #' . (int) ($activeSong['id'] ?? 0);
    $duration = formatSongDuration(isset($activeSong['duration_seconds']) ? (int) $activeSong['duration_seconds'] : null);
    $detailSubtitle = $duration !== '' ? $duration : (string) ($activeSong['composer'] ?? '');

    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['song_id' => (int) $activeSong['id'], 'mode' => 'edit']));
    $detailActionsHtml = '<a class="button is-small" href="' . htmlspecialchars($editLink) . '">Edit</a>';

    $detailRows = [
        buildDetailRow('Name', (string) ($activeSong['name'] ?? '')),
        buildDetailRow('Duration', $duration),
        buildDetailRow('Composer', (string) ($activeSong['composer'] ?? '')),
        buildDetailRow('Arranger', (string) ($activeSong['arranger'] ?? '')),
        buildDetailRow('Created', (string) ($activeSong['created_at'] ?? '')),
        buildDetailRow('Updated', (string) ($activeSong['updated_at'] ?? '')),
    ];
}
?>

<?php if (!$activeSong): ?>
  <?php require __DIR__ . '/../../../partials/tables/detail.php'; ?>
<?php else: ?>
  <div class="box">
    <div class="level">
      <div class="level-left">
        <div>
          <?php if ($detailTitle): ?>
            <h3 class="title is-5 mb-1"><?php echo htmlspecialchars($detailTitle); ?></h3>
          <?php endif; ?>
          <?php if ($detailSubtitle): ?>
            <p class="is-size-7 has-text-grey"><?php echo htmlspecialchars($detailSubtitle); ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($detailActionsHtml): ?>
        <div class="level-right"><?php echo $detailActionsHtml; ?></div>
      <?php endif; ?>
    </div>

    <div class="content">
      <div class="table-container">
        <table class="table is-fullwidth">
          <tbody>
            <?php foreach ($detailRows as $row): ?>
              <?php $label = (string) ($row['label'] ?? ''); $value = (string) ($row['value'] ?? ''); ?>
              <?php if ($value !== ''): ?>
                <tr>
                  <th><?php echo htmlspecialchars($label); ?></th>
                  <td><?php echo nl2br(htmlspecialchars($value)); ?></td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>
