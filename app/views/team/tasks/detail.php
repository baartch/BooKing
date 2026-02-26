<?php
/**
 * Variables expected:
 * - ?array $activeTask
 * - array $baseQuery
 * - string $baseUrl
 * - int $activeTeamId
 * - array $taskLinks
 */
require_once __DIR__ . '/../../../models/core/list_helpers.php';

$detailTitle = null;
$detailSubtitle = null;
$detailActionsHtml = null;
$detailRows = [];
$detailEmptyMessage = 'Select a task to see the details.';
$taskLinks = $taskLinks ?? [];

$linkItems = [];
$linkEditorLinks = [];
$linkIcons = [
    'contact' => 'fa-user',
    'venue' => 'fa-location-dot',
    'email' => 'fa-envelope',
    'task' => 'fa-list-check'
];

if ($activeTask) {
    $detailTitle = (string) ($activeTask['title'] ?? '');
    $detailSubtitle = (string) ($activeTask['priority'] ?? '');

    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['task_id' => (int) $activeTask['id'], 'mode' => 'edit']));
    $detailActionsHtml = '<a class="button is-small" href="' . htmlspecialchars($editLink) . '">Edit</a>';

    if (!empty($taskLinks)) {
        foreach ($taskLinks as $link) {
            if ($link['type'] === 'contact') {
                $url = BASE_PATH . '/app/controllers/communication/index.php?tab=contacts&contact_id=' . (int) $link['id'];
                $linkItems[] = ['type' => 'contact', 'label' => $link['label'], 'url' => $url];
            } elseif ($link['type'] === 'venue') {
                $url = BASE_PATH . '/app/controllers/venues/index.php?q=' . urlencode($link['label']);
                $linkItems[] = ['type' => 'venue', 'label' => $link['label'], 'url' => $url];
            } elseif ($link['type'] === 'email') {
                $url = BASE_PATH . '/app/controllers/communication/index.php?tab=email&message_id=' . (int) $link['id'];
                $linkItems[] = ['type' => 'email', 'label' => $link['label'], 'url' => $url];
            }
            $linkEditorLinks[] = [
                'type' => $link['type'],
                'id' => (int) $link['id'],
                'label' => $link['label']
            ];
        }
    }

    $detailRows = [
        buildDetailRow('Priority', (string) ($activeTask['priority'] ?? '')),
        buildDetailRow('Due', (string) ($activeTask['due_date'] ?? '')),
        buildDetailRow('Description', (string) ($activeTask['description'] ?? '')),
        buildDetailRow('Created', (string) ($activeTask['created_at'] ?? '')),
        buildDetailRow('Updated', (string) ($activeTask['updated_at'] ?? ''))
    ];
}
?>

<?php if (!$activeTask): ?>
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
            <p class="is-size-7 has-text-grey">Priority <?php echo htmlspecialchars($detailSubtitle); ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($detailActionsHtml): ?>
        <div class="level-right">
          <?php echo $detailActionsHtml; ?>
        </div>
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
            <a href="#" class="detail-link-edit" data-link-editor-trigger data-link-editor-modal-id="<?php echo htmlspecialchars('link-editor-task-' . (int) $activeTask['id']); ?>" title="Edit links">
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
              <?php
                $label = (string) ($row['label'] ?? '');
                $value = (string) ($row['value'] ?? '');
                $isHtml = (bool) ($row['isHtml'] ?? false);
              ?>
              <?php if ($value !== ''): ?>
                <tr>
                  <th><?php echo htmlspecialchars($label); ?></th>
                  <td>
                    <?php if ($isHtml): ?>
                      <?php echo $value; ?>
                    <?php else: ?>
                      <?php echo nl2br(htmlspecialchars($value)); ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php
      if ($activeTask) {
          $linkEditorSourceType = 'task';
          $linkEditorSourceId = (int) $activeTask['id'];
          $linkEditorMailboxId = 0;
          $linkEditorSearchTypes = 'contact,venue,email,task';
          $linkEditorLinks = $linkEditorLinks ?? [];
          require __DIR__ . '/../../../partials/link_editor_modal.php';
      }
    ?>
  </div>
<?php endif; ?>
