<?php
/**
 * Variables expected:
 * - int $pageSize
 * - string $venuesQuery
 */
$minPageSize = 25;
$maxPageSize = 500;
$pageSize = isset($pageSize) ? (int) $pageSize : 25;
$cancelUrl = BASE_PATH . '/app/controllers/venues/index.php';
if (!empty($venuesQuery)) {
    $cancelUrl .= '?' . http_build_query(['filter' => $venuesQuery]);
}
?>
<div class="box">
  <div class="level mb-3">
    <div class="level-left">
      <h2 class="title is-5">Venues List Settings</h2>
    </div>
  </div>

  <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/venues/index.php">
    <?php renderCsrfField(); ?>
    <input type="hidden" name="action" value="update_page_size">
    <?php if (!empty($venuesQuery)): ?>
      <input type="hidden" name="filter" value="<?php echo htmlspecialchars((string) $venuesQuery); ?>">
    <?php endif; ?>

    <div class="table-container">
      <table class="table is-fullwidth">
        <tbody>
          <tr>
            <th>Venues per page (25-500)</th>
            <td>
              <div class="control">
                <input
                  type="number"
                  id="venues_page_size"
                  name="venues_page_size"
                  class="input"
                  min="<?php echo (int) $minPageSize; ?>"
                  max="<?php echo (int) $maxPageSize; ?>"
                  value="<?php echo (int) $pageSize; ?>"
                  required
                >
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="buttons">
      <button type="submit" class="button is-primary">Update Page Size</button>
      <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="button">Cancel</a>
    </div>
  </form>
</div>
