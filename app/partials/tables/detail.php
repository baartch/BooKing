<?php
/**
 * Variables expected:
 * - string|null $detailTitle
 * - string|null $detailSubtitle
 * - string|null $detailActionsHtml
 * - array $detailRows (each: label, value, isHtml?)
 * - string $detailEmptyMessage
 */
$detailTitle = $detailTitle ?? null;
$detailSubtitle = $detailSubtitle ?? null;
$detailActionsHtml = $detailActionsHtml ?? null;
$detailRows = $detailRows ?? [];
$detailEmptyMessage = $detailEmptyMessage ?? 'Select an entry to see the details.';
?>
<?php if (!$detailTitle && !$detailRows): ?>
  <div class="notification">
    <p><?php echo htmlspecialchars($detailEmptyMessage); ?></p>
  </div>
<?php else: ?>
  <div class="box">
    <div class="level">
      <div class="level-left">
        <div>
          <?php if ($detailTitle): ?>
            <h3 class="title is-5 mb-1"><?php echo htmlspecialchars($detailTitle); ?></h3>
          <?php endif; ?>
          <?php if ($detailSubtitle): ?>
            <p class="subtitle is-7"><?php echo htmlspecialchars($detailSubtitle); ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($detailActionsHtml): ?>
        <div class="level-right">
          <?php echo $detailActionsHtml; ?>
        </div>
      <?php endif; ?>
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
  </div>
<?php endif; ?>
