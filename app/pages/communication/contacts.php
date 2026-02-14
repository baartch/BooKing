<?php
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/communication/contacts_helpers.php';

$errors = [];
$notice = '';
$pdo = null;
$countryOptions = ['DE', 'CH', 'AT', 'IT', 'FR'];

$userId = (int) ($currentUser['user_id'] ?? 0);
$baseUrl = BASE_PATH . '/app/pages/communication/index.php';
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$requestedTeamId = (int) ($_GET['team_id'] ?? 0);

$baseQuery = ['tab' => 'contacts'];
if ($searchQuery !== '') {
    $baseQuery['q'] = $searchQuery;
}
if ($requestedTeamId > 0) {
    $baseQuery['team_id'] = $requestedTeamId;
}

$hasContactSelection = array_key_exists('contact_id', $_GET);
$contactId = $hasContactSelection ? (int) ($_GET['contact_id'] ?? 0) : 0;
if ($contactId > 0) {
    $baseQuery['contact_id'] = $contactId;
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

$editContact = null;
$isEdit = $contactId > 0;
$showForm = $hasContactSelection;

if ($pdo && $isEdit && $activeTeamId > 0) {
    try {
        $editContact = fetchContact($pdo, $activeTeamId, $contactId);
        if (!$editContact) {
            $errors[] = 'Contact not found.';
            $isEdit = false;
        } else {
            $formValues = [
                'firstname' => (string) ($editContact['firstname'] ?? ''),
                'surname' => (string) ($editContact['surname'] ?? ''),
                'email' => (string) ($editContact['email'] ?? ''),
                'phone' => (string) ($editContact['phone'] ?? ''),
                'address' => (string) ($editContact['address'] ?? ''),
                'postal_code' => (string) ($editContact['postal_code'] ?? ''),
                'city' => (string) ($editContact['city'] ?? ''),
                'country' => (string) ($editContact['country'] ?? ''),
                'website' => (string) ($editContact['website'] ?? ''),
                'notes' => (string) ($editContact['notes'] ?? '')
            ];
        }
    } catch (Throwable $error) {
        $errors[] = 'Failed to load contact.';
        logAction($userId, 'contacts_load_error', $error->getMessage());
        $isEdit = false;
    }
}

$cancelQuery = array_filter([
    'tab' => 'contacts',
    'q' => $searchQuery !== '' ? $searchQuery : null,
    'team_id' => $activeTeamId > 0 ? $activeTeamId : null
], static fn($value) => $value !== null && $value !== '');
$cancelUrl = $baseUrl . '?' . http_build_query($cancelQuery);
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

<?php if ($showForm): ?>
  <?php require __DIR__ . '/contacts_form.php'; ?>
<?php else: ?>
  <?php require __DIR__ . '/contacts_list.php'; ?>
<?php endif; ?>
