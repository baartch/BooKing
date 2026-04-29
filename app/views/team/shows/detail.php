<?php
require_once __DIR__ . '/../../../models/core/list_helpers.php';
require_once __DIR__ . '/../../../models/core/link_helpers.php';

$detailTitle = null;
$detailSubtitle = null;
$detailActionsHtml = null;
$detailRows = [];
$detailEmptyMessage = 'Select a show to see details.';
$showLinks = $showLinks ?? [];

$linkItems = [];
$linkEditorLinks = [];
$linkIcons = getLinkIcons();

if ($activeShow) {
    $title = trim((string) ($activeShow['name'] ?? ''));
    $detailTitle = $title !== '' ? $title : 'Show #' . (int) ($activeShow['id'] ?? 0);
    $detailSubtitle = (string) ($activeShow['show_date'] ?? '');

    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['show_id' => (int) $activeShow['id'], 'mode' => 'edit']));
    $detailActionsHtml = '<a class="button is-small" href="' . htmlspecialchars($editLink) . '">Edit</a>';

    if (!empty($showLinks)) {
        foreach ($showLinks as $link) {
            $linkItems[] = [
                'type' => $link['type'],
                'label' => $link['label'],
                'url' => buildLinkUrl($link),
            ];
            $linkEditorLinks[] = [
                'type' => $link['type'],
                'id' => (int) $link['id'],
                'label' => $link['label'],
            ];
        }
    }

    $detailRows = [
        buildDetailRow('Date', (string) ($activeShow['show_date'] ?? '')),
        buildDetailRow('Time', (string) ($activeShow['show_time'] ?? '')),
        buildDetailRow('Venue', (string) ($activeShow['venue_text'] ?? '')),
        buildDetailRow('Artist Fee', (string) ($activeShow['artist_fee'] ?? '')),
        buildDetailRow('Notes', (string) ($activeShow['notes'] ?? '')),
        buildDetailRow('Created', (string) ($activeShow['created_at'] ?? '')),
        buildDetailRow('Updated', (string) ($activeShow['updated_at'] ?? '')),
    ];
}
?>

<?php if (!$activeShow): ?>
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

    <div class="detail-meta">
      <div class="detail-meta-info">
        <div class="detail-meta-row">
          <span class="detail-meta-label">Links:</span>
          <span class="detail-meta-value detail-link-list">
            <?php if (!$linkItems): ?>
              <span class="has-text-grey is-size-7">No links yet</span>
            <?php else: ?>
              <?php foreach ($linkItems as $link): ?>
                <a href="<?php echo htmlspecialchars($link['url']); ?>" class="detail-link-pill">
                  <span class="icon is-small"><i class="fa-solid <?php echo htmlspecialchars($linkIcons[$link['type']] ?? 'fa-link'); ?>"></i></span>
                  <span><?php echo htmlspecialchars($link['label']); ?></span>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
            <a href="#" class="detail-link-edit" data-link-editor-trigger data-link-editor-modal-id="<?php echo htmlspecialchars('link-editor-show-' . (int) $activeShow['id']); ?>" title="Edit links">
              <span class="icon is-small"><i class="fa-solid fa-pen fa-2xs"></i></span>
            </a>
          </span>
        </div>
      </div>
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

    <?php
      $linkEditorSourceType = 'show';
      $linkEditorSourceId = (int) $activeShow['id'];
      $linkEditorMailboxId = 0;
      $linkEditorSearchTypes = 'contact,venue,email,task,show';
      $linkEditorLinks = $linkEditorLinks ?? [];
      require __DIR__ . '/../../../partials/link_editor_modal.php';
    ?>
  </div>
<?php endif; ?>
