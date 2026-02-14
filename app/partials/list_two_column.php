<?php
/**
 * Variables expected:
 * - string $listTitle
 * - array|null $listSearch (action, inputName, inputValue, placeholder, inputId, hiddenFields)
 * - array $listSummaryTags
 * - string|null $listPrimaryActionHtml
 * - string $listContentPath
 * - string $detailContentPath
 */
$listSummaryTags = $listSummaryTags ?? [];
$listPrimaryActionHtml = $listPrimaryActionHtml ?? null;
$listSearch = $listSearch ?? null;
?>
<div class="box">
  <div class="columns is-variable is-4 list-layout">
    <section class="column is-8">
      <div class="level mb-3">
        <div class="level-left">
          <h2 class="title is-5"><?php echo htmlspecialchars($listTitle); ?></h2>
        </div>
        <div class="level-right">
          <?php if ($listSearch): ?>
            <form method="GET" action="<?php echo htmlspecialchars($listSearch['action']); ?>" data-filter-form>
              <?php foreach (($listSearch['hiddenFields'] ?? []) as $name => $value): ?>
                <?php if ($value !== null && $value !== ''): ?>
                  <input type="hidden" name="<?php echo htmlspecialchars((string) $name); ?>" value="<?php echo htmlspecialchars((string) $value); ?>">
                <?php endif; ?>
              <?php endforeach; ?>
              <div class="field has-addons">
                <div class="control has-icons-left is-expanded">
                  <div class="dropdown is-fullwidth map-search-dropdown">
                    <div class="dropdown-trigger">
                      <input
                        class="input"
                        type="text"
                        id="<?php echo htmlspecialchars($listSearch['inputId']); ?>"
                        name="<?php echo htmlspecialchars($listSearch['inputName']); ?>"
                        value="<?php echo htmlspecialchars($listSearch['inputValue']); ?>"
                        placeholder="<?php echo htmlspecialchars($listSearch['placeholder']); ?>"
                        autocomplete="off"
                      >
                      <span class="icon is-left"><i class="fa-solid fa-magnifying-glass"></i></span>
                    </div>
                  </div>
                </div>
                <p class="control">
                  <span class="button is-static">Ctrl+K</span>
                </p>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($listSummaryTags || $listPrimaryActionHtml): ?>
        <div class="level mb-3">
          <div class="level-left">
            <?php foreach ($listSummaryTags as $tag): ?>
              <span class="tag"><?php echo htmlspecialchars((string) $tag); ?></span>
            <?php endforeach; ?>
          </div>
          <div class="level-right">
            <?php echo $listPrimaryActionHtml ?? ''; ?>
          </div>
        </div>
      <?php endif; ?>
      <?php require $listContentPath; ?>
    </section>
    <section class="column is-4">
      <?php require $detailContentPath; ?>
    </section>
  </div>
</div>
