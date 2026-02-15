<?php
require_once __DIR__ . '/../../../models/auth/admin_check.php';
require_once __DIR__ . '/../../../models/core/htmx_class.php';

if (!isset($teams, $users, $memberIdsByTeam, $adminIdsByTeam)) {
    return;
}

$baseUrl = BASE_PATH . '/app/controllers/admin/index.php';
$baseQuery = ['tab' => 'teams'];
$mode = (string) ($_GET['mode'] ?? '');
$selectedTeamId = (int) ($_GET['team_id'] ?? 0);

$userOptions = [];
foreach ($users as $user) {
    $userId = (int) ($user['id'] ?? 0);
    $displayName = (string) ($user['display_name'] ?? '');
    $username = (string) ($user['username'] ?? '');
    $label = $displayName !== '' ? $displayName : $username;
    if ($displayName !== '' && $username !== '') {
        $label .= ' (' . $username . ')';
    }
    $userOptions[$userId] = $label !== '' ? $label : ('User #' . $userId);
}

$activeTeam = null;
if ($selectedTeamId > 0) {
    foreach ($teams as $team) {
        if ((int) ($team['id'] ?? 0) === $selectedTeamId) {
            $activeTeam = $team;
            break;
        }
    }
}

$isEdit = $mode === 'edit' && $selectedTeamId > 0 && $activeTeam !== null;
$showForm = $mode === 'new' || $isEdit;
$editTeam = $isEdit ? $activeTeam : null;

$formValues = [
    'team_name' => '',
    'team_description' => ''
];
$memberSelection = [];
$adminSelection = [];
if ($isEdit && $editTeam) {
    $formValues = [
        'team_name' => (string) ($editTeam['name'] ?? ''),
        'team_description' => (string) ($editTeam['description'] ?? '')
    ];
    $memberSelection = array_map('intval', $memberIdsByTeam[$selectedTeamId] ?? []);
    $adminSelection = array_map('intval', $adminIdsByTeam[$selectedTeamId] ?? []);
}

$activeTeamMembers = [];
$activeTeamAdmins = [];
if ($activeTeam) {
    $teamId = (int) ($activeTeam['id'] ?? 0);
    foreach (($memberIdsByTeam[$teamId] ?? []) as $memberId) {
        if (isset($userOptions[$memberId])) {
            $activeTeamMembers[] = $userOptions[$memberId];
        }
    }
    foreach (($adminIdsByTeam[$teamId] ?? []) as $adminId) {
        if (isset($userOptions[$adminId])) {
            $activeTeamAdmins[] = $userOptions[$adminId];
        }
    }
}

$cancelQuery = array_filter([
    'tab' => 'teams',
    'team_id' => $selectedTeamId > 0 ? $selectedTeamId : null
], static fn($value) => $value !== null && $value !== '');
$cancelUrl = $baseUrl . '?' . http_build_query($cancelQuery);

$listTitle = 'Teams';
$listSummaryTags = [sprintf('%d teams', count($teams))];
$addTeamUrl = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['mode' => 'new']));
$listPrimaryActionHtml = '<a href="' . htmlspecialchars($addTeamUrl) . '" class="button is-primary">Add Team</a>';

$listContentPath = __DIR__ . '/list.php';
$detailContentPath = $showForm ? __DIR__ . '/form.php' : __DIR__ . '/detail.php';
$detailWrapperId = 'admin-teams-detail-panel';

if (HTMX::isRequest()) {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require $detailContentPath;
    return;
}
?>

<?php require __DIR__ . '/../../../partials/tables/two_column.php'; ?>
