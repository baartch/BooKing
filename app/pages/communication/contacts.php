<?php
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/htmx_class.php';
require_once __DIR__ . '/../../src-php/communication/contacts_helpers.php';
require_once __DIR__ . '/../../src-php/communication/email_helpers.php';

$errors = [];
$notice = '';
$pdo = null;
$countryOptions = ['DE', 'CH', 'AT', 'IT', 'FR'];

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/pages/communication/index.php';
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$requestedTeamId = (int) ($_GET['team_id'] ?? 0);
$contactId = (int) ($_GET['contact_id'] ?? 0);
$mode = (string) ($_GET['mode'] ?? '');

$baseQuery = ['tab' => 'contacts'];
if ($searchQuery !== '') {
    $baseQuery['q'] = $searchQuery;
}
if ($requestedTeamId > 0) {
    $baseQuery['team_id'] = $requestedTeamId;
}

$noticeKey = (string) ($_GET['notice'] ?? '');
if ($noticeKey === 'contact_created') {
    $notice = 'Contact created successfully.';
} elseif ($noticeKey === 'contact_updated') {
    $notice = 'Contact updated successfully.';
} elseif ($noticeKey === 'contact_error') {
    $notice = 'Failed to save contact.';
} elseif ($noticeKey === 'contact_deleted') {
    $notice = 'Contact deleted successfully.';
}

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $error) {
    $errors[] = 'Database connection unavailable.';
    logAction($userId, 'contacts_db_error', $error->getMessage());
    $pdo = null;
}

// Saving contacts is handled via POST route (avoid header() after HTML output in index.php)

$activeTeamId = 0;
$userTeamIds = [];
if ($pdo) {
    try {
        $activeTeamId = resolveActiveTeamId($pdo, $userId, $requestedTeamId);
        $userTeams = fetchUserTeams($pdo, $userId);
        $userTeamIds = array_map('intval', array_column($userTeams, 'id'));
        if ($activeTeamId <= 0) {
            $errors[] = 'No team access available.';
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to resolve team scope.';
        logAction($userId, 'contacts_team_scope_error', $error->getMessage());
    }
}

if ($activeTeamId > 0) {
    $baseQuery['team_id'] = $activeTeamId;
}

$contacts = [];
if ($pdo && $activeTeamId > 0) {
    try {
        $contacts = fetchContacts($pdo, $activeTeamId, $searchQuery);
    } catch (Throwable $error) {
        $errors[] = 'Failed to load contacts.';
        logAction($userId, 'contacts_list_error', $error->getMessage());
    }
}

$activeContactId = $contactId;
$formValues = [
    'firstname' => '',
    'surname' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'postal_code' => '',
    'city' => '',
    'country' => '',
    'website' => '',
    'notes' => ''
];

$showForm = in_array($mode, ['edit', 'new'], true);
$isEdit = $mode === 'edit' && $contactId > 0;

$contactRecord = null;
if ($pdo && $contactId > 0 && $activeTeamId > 0) {
    try {
        $contactRecord = fetchContact($pdo, $activeTeamId, $contactId);
        if (!$contactRecord) {
            $errors[] = 'Contact not found.';
            $contactRecord = null;
            $isEdit = false;
            $showForm = $mode === 'new';
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load contact.';
        logAction($userId, 'contacts_load_error', $error->getMessage());
        $contactRecord = null;
        $isEdit = false;
        $showForm = $mode === 'new';
    }
}

$editContact = null;
if ($showForm && $isEdit && $contactRecord) {
    $editContact = $contactRecord;
    $formValues = [
        'firstname' => (string) ($contactRecord['firstname'] ?? ''),
        'surname' => (string) ($contactRecord['surname'] ?? ''),
        'email' => (string) ($contactRecord['email'] ?? ''),
        'phone' => (string) ($contactRecord['phone'] ?? ''),
        'address' => (string) ($contactRecord['address'] ?? ''),
        'postal_code' => (string) ($contactRecord['postal_code'] ?? ''),
        'city' => (string) ($contactRecord['city'] ?? ''),
        'country' => (string) ($contactRecord['country'] ?? ''),
        'website' => (string) ($contactRecord['website'] ?? ''),
        'notes' => (string) ($contactRecord['notes'] ?? '')
    ];
} elseif ($showForm && $isEdit && !$contactRecord) {
    $showForm = false;
}

$activeContact = null;
$contactLinks = [];
if ($contactRecord && $pdo && $activeTeamId > 0) {
    try {
        $contactLinks = fetchLinkedObjects($pdo, 'contact', (int) $contactRecord['id'], $activeTeamId, null);
    } catch (Throwable $error) {
        logAction($userId, 'contacts_links_load_error', $error->getMessage());
    }
}

if (!$showForm && $contactRecord) {
    $activeContact = $contactRecord;
}

$cancelQuery = array_filter([
    'tab' => 'contacts',
    'q' => $searchQuery !== '' ? $searchQuery : null,
    'team_id' => $activeTeamId > 0 ? $activeTeamId : null,
    'contact_id' => $isEdit ? $contactId : null
], static fn($value) => $value !== null && $value !== '');
$cancelUrl = $baseUrl . '?' . http_build_query($cancelQuery);

$listTitle = 'Contacts';
$listSummaryTags = [sprintf('%d contacts', count($contacts))];
$addContactUrl = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['mode' => 'new']));
$listPrimaryActionHtml = '<a href="' . htmlspecialchars($addContactUrl) . '" class="button is-primary">Add Contact</a>';

$listSearch = [
    'action' => $baseUrl,
    'inputName' => 'q',
    'inputValue' => $searchQuery,
    'placeholder' => 'Search for contacts...',
    'inputId' => 'contact-filter',
    'hiddenFields' => [
        'tab' => 'contacts',
        'team_id' => $activeTeamId
    ]
];

$listContentPath = __DIR__ . '/contacts_list.php';
$detailContentPath = $showForm ? __DIR__ . '/contacts_form.php' : __DIR__ . '/contacts_detail.php';
$detailWrapperId = 'contact-detail-panel';

if (HTMX::isRequest()) {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require $detailContentPath;
    return;
}
?>

<?php if ($notice): ?>
  <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
  <div class="notification"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<?php if ($activeTeamId > 0 && isset($userTeams) && count($userTeams) > 1): ?>
  <div class="box">
    <div class="field">
      <label class="label">Team</label>
      <div class="control">
        <div class="select">
          <select onchange="window.location.href=this.value;">
            <?php foreach ($userTeams as $team): ?>
              <?php
                $teamId = (int) ($team['id'] ?? 0);
                $teamUrl = $baseUrl . '?' . http_build_query(array_filter([
                  'tab' => 'contacts',
                  'team_id' => $teamId,
                  'q' => $searchQuery !== '' ? $searchQuery : null
                ], static fn($value) => $value !== null && $value !== ''));
              ?>
              <option value="<?php echo htmlspecialchars($teamUrl); ?>" <?php echo $teamId === (int) $activeTeamId ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars((string) ($team['name'] ?? ('Team #' . $teamId))); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <p class="help">Contacts are scoped to the selected team.</p>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../../partials/table_two_column.php'; ?>
