<?php
/**
 * Variables expected:
 * - ?array $activeContact
 * - array $contactLinks
 * - string $baseUrl
 * - array $baseQuery
 * - int $activeTeamId
 */
?>
<?php
require_once __DIR__ . '/../../src-php/core/list_helpers.php';

$detailTitle = null;
$detailSubtitle = null;
$detailActionsHtml = null;
$detailRows = [];
$detailEmptyMessage = 'Select a contact to see the details.';
$contactLinks = $contactLinks ?? [];

$linkItems = [];
$linkEditorLinks = [];
$linkIcons = [
    'contact' => 'fa-user',
    'venue' => 'fa-location-dot',
    'email' => 'fa-envelope'
];

if ($activeContact) {
    $nameParts = [];
    if (!empty($activeContact['firstname'])) {
        $nameParts[] = $activeContact['firstname'];
    }
    if (!empty($activeContact['surname'])) {
        $nameParts[] = $activeContact['surname'];
    }
    $name = trim(implode(' ', $nameParts));
    $detailTitle = $name !== '' ? $name : '(Unnamed)';

    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['contact_id' => (int) $activeContact['id'], 'mode' => 'edit']));
    $detailActionsHtml = '<a class="button is-small" href="' . htmlspecialchars($editLink) . '">Edit</a>';

    $emailValue = (string) ($activeContact['email'] ?? '');
    $websiteValue = (string) ($activeContact['website'] ?? '');

    if (!empty($contactLinks)) {
        foreach ($contactLinks as $link) {
            if ($link['type'] === 'contact') {
                $url = BASE_PATH . '/app/controllers/communication/index.php?tab=contacts&contact_id=' . (int) $link['id'];
                $linkItems[] = ['type' => 'contact', 'label' => $link['label'], 'url' => $url];
            } elseif ($link['type'] === 'venue') {
                $url = BASE_PATH . '/app/pages/venues/index.php?q=' . urlencode($link['label']);
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
        buildDetailRow('Firstname', (string) ($activeContact['firstname'] ?? '')),
        buildDetailRow('Surname', (string) ($activeContact['surname'] ?? '')),
        buildDetailRow(
            'Email',
            $emailValue !== '' ? '<a href="mailto:' . htmlspecialchars($emailValue) . '">' . htmlspecialchars($emailValue) . '</a>' : '',
            true
        ),
        buildDetailRow('Phone', (string) ($activeContact['phone'] ?? '')),
        buildDetailRow('Address', (string) ($activeContact['address'] ?? '')),
        buildDetailRow('Postal Code', (string) ($activeContact['postal_code'] ?? '')),
        buildDetailRow('City', (string) ($activeContact['city'] ?? '')),
        buildDetailRow('Country', (string) ($activeContact['country'] ?? '')),
        buildDetailRow(
            'Website',
            $websiteValue !== ''
                ? '<a href="' . htmlspecialchars($websiteValue) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($websiteValue) . '</a>'
                : '',
            true
        ),
        buildDetailRow('Notes', (string) ($activeContact['notes'] ?? ''))
    ];
}
?>

<?php if (!$activeContact): ?>
  <?php require __DIR__ . '/../../partials/tables/detail.php'; ?>
<?php else: ?>
  <div class="box">
    <div class="level">
      <div class="level-left">
        <div>
          <?php if ($detailTitle): ?>
            <h3 class="title is-5 mb-1"><?php echo htmlspecialchars($detailTitle); ?></h3>
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
            <a href="#" class="detail-link-edit" data-link-editor-trigger data-link-editor-modal-id="<?php echo htmlspecialchars('link-editor-contact-' . (int) $activeContact['id']); ?>" title="Edit links">
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
      if ($activeContact) {
          $linkEditorSourceType = 'contact';
          $linkEditorSourceId = (int) $activeContact['id'];
          $linkEditorMailboxId = 0;
          $linkEditorSearchTypes = 'contact,venue,email';
          require __DIR__ . '/../../partials/link_editor_modal.php';
      }
    ?>
  </div>
<?php endif; ?>
