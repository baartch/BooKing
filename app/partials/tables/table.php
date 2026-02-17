<?php
/**
 * Variables expected:
 * - array $listRows
 * - array $listColumns (each: label, key?, render?, isHtml?)
 * - string $listEmptyMessage
 * - callable|null $listRowClass
 * - callable|null $listRowActions (returns HTML)
 * - string $listActionsLabel
 * - callable|null $listRowLink (returns array or string)
 */
$listRows = $listRows ?? [];
$listColumns = $listColumns ?? [];
$listEmptyMessage = $listEmptyMessage ?? 'No entries found.';
$listRowClass = $listRowClass ?? null;
$listRowActions = $listRowActions ?? null;
$listActionsLabel = $listActionsLabel ?? 'Actions';
$listRowLink = $listRowLink ?? null;
?>
<?php if (!$listRows): ?>
  <p><?php echo htmlspecialchars($listEmptyMessage); ?></p>
<?php else: ?>
  <div class="table-container table-container--dropdowns">
    <table class="table is-fullwidth is-hoverable" data-list-selectable data-list-active-class="is-selected">
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
          <?php
            $rowClass = $listRowClass ? (string) $listRowClass($row) : '';
            $linkAttributes = '';
            if ($listRowLink && is_callable($listRowLink)) {
                $linkResult = $listRowLink($row);
                $linkData = is_array($linkResult) ? $linkResult : ['href' => (string) $linkResult];

                if (!empty($linkData['href'])) {
                    $linkAttributes .= ' data-row-link="' . htmlspecialchars((string) $linkData['href']) . '"';
                    $linkAttributes .= ' tabindex="0"';
                    $linkAttributes .= ' style="cursor: pointer;"';
                }

                $rowTarget = $linkData['hx-target'] ?? null;
                $rowSwap = $linkData['hx-swap'] ?? null;

                if ($rowTarget) {
                    $linkAttributes .= ' data-row-target="' . htmlspecialchars((string) $rowTarget) . '"';
                }

                if ($rowSwap) {
                    $linkAttributes .= ' data-row-swap="' . htmlspecialchars((string) $rowSwap) . '"';
                }

                foreach ($linkData as $attrName => $attrValue) {
                    if (in_array($attrName, ['href'], true)) {
                        continue;
                    }
                    if ($attrValue === null || $attrValue === '') {
                        continue;
                    }
                    if ($attrName === 'hx-push-url') {
                        $linkAttributes .= ' data-row-push-url="' . htmlspecialchars((string) $attrValue) . '"';
                        continue;
                    }
                    if (in_array($attrName, ['hx-target', 'hx-swap', 'hx-get'], true)) {
                        continue;
                    }
                    $linkAttributes .= ' ' . htmlspecialchars((string) $attrName) . '="' . htmlspecialchars((string) $attrValue) . '"';
                }
            }
          ?>
          <?php if ($rowClass !== ''): ?>
            <tr data-list-item class="<?php echo htmlspecialchars($rowClass); ?>"<?php echo $linkAttributes; ?>>
          <?php else: ?>
            <tr data-list-item<?php echo $linkAttributes; ?>>
          <?php endif; ?>
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
