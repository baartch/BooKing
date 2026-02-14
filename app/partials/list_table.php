<?php
/**
 * Variables expected:
 * - array $listRows
 * - array $listColumns (each: label, key?, render?, isHtml?)
 * - string $listEmptyMessage
 * - callable|null $listRowClass
 * - callable|null $listRowActions (returns HTML)
 * - string $listActionsLabel
 */
$listRows = $listRows ?? [];
$listColumns = $listColumns ?? [];
$listEmptyMessage = $listEmptyMessage ?? 'No entries found.';
$listRowClass = $listRowClass ?? null;
$listRowActions = $listRowActions ?? null;
$listActionsLabel = $listActionsLabel ?? 'Actions';
?>
<?php if (!$listRows): ?>
  <p><?php echo htmlspecialchars($listEmptyMessage); ?></p>
<?php else: ?>
  <div class="table-container">
    <table class="table is-fullwidth is-hoverable">
      <thead>
        <tr>
          <?php foreach ($listColumns as $column): ?>
            <th><?php echo htmlspecialchars((string) ($column['label'] ?? '')); ?></th>
          <?php endforeach; ?>
          <?php if ($listRowActions): ?>
            <th class="has-text-right"><?php echo htmlspecialchars($listActionsLabel); ?></th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($listRows as $row): ?>
          <?php $rowClass = $listRowClass ? (string) $listRowClass($row) : ''; ?>
          <tr class="<?php echo htmlspecialchars($rowClass); ?>">
            <?php foreach ($listColumns as $column): ?>
              <?php
                $value = '';
                if (isset($column['render']) && is_callable($column['render'])) {
                    $value = (string) $column['render']($row);
                } elseif (isset($column['key'])) {
                    $value = (string) ($row[$column['key']] ?? '');
                }
                $isHtml = (bool) ($column['isHtml'] ?? false);
              ?>
              <td>
                <?php if ($isHtml): ?>
                  <?php echo $value; ?>
                <?php else: ?>
                  <?php echo htmlspecialchars($value); ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <?php if ($listRowActions): ?>
              <td class="has-text-right">
                <?php echo $listRowActions($row); ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
