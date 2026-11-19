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
require_once __DIR__ . '/../../../models/core/link_helpers.php';
require_once __DIR__ . '/../../../partials/linked_objects.php';

$detailTitle = null;
$detailSubtitle = null;
$detailActionsHtml = null;
$detailRows = [];
$detailEmptyMessage = 'Select a task to see the details.';
$taskLinks = $taskLinks ?? [];

$linkItems = [];
$linkEditorLinks = [];

if ($activeTask) {
    $detailTitle = (string) ($activeTask['title'] ?? '');
    $detailSubtitle = (string) ($activeTask['priority'] ?? '');

    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['task_id' => (int) $activeTask['id'], 'mode' => 'edit']));
    $detailActionsHtml = '<a class="button is-small" href="' . htmlspecialchars($editLink) . '">Edit</a>';

    if (!empty($taskLinks)) {
        foreach ($taskLinks as $link) {
            $linkItems[] = [
                'type' => $link['type'],
                'label' => $link['label'],
                'url' => buildLinkUrl($link)
            ];
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
            <?php echo renderLinkedObjects([
                'items' => $linkItems,
                'modalId' => 'link-editor-task-' . (int) $activeTask['id'],
            ]); ?>
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
