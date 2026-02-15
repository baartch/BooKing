<?php
require_once __DIR__ . '/../../models/auth/admin_check.php';
require_once __DIR__ . '/../../src-php/core/htmx_class.php';

if (!isset($users, $teamsByUser)) {
    return;
}

$baseUrl = BASE_PATH . '/app/controllers/admin/index.php';
$baseQuery = ['tab' => 'users'];
$mode = (string) ($_GET['mode'] ?? '');
$selectedUserId = (int) ($_GET['user_id'] ?? 0);

$isEditing = $editUser !== null;
$editUserId = $isEditing ? (int) $editUser['id'] : 0;
$showForm = $isEditing || $mode === 'new';
$detailWrapperId = 'admin-user-detail-panel';

$activeUser = null;
$activeUserTeams = [];
if (!$showForm && $selectedUserId > 0) {
    foreach ($users as $user) {
        if ((int) $user['id'] === $selectedUserId) {
            $activeUser = $user;
            break;
        }
    }

    if ($activeUser) {
        $activeUserTeams = $teamsByUser[(int) $activeUser['id']] ?? [];
    }
}

$listTitle = 'Users';
$listSummaryTags = [sprintf('%d users', count($users))];
$addUserUrl = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['mode' => 'new']));
$listPrimaryActionHtml = '<a href="' . htmlspecialchars($addUserUrl) . '" class="button is-primary">Add User</a>';

$listContentPath = __DIR__ . '/users_list.php';
$detailContentPath = $showForm ? __DIR__ . '/users_form.php' : __DIR__ . '/users_detail.php';

if (HTMX::isRequest()) {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require $detailContentPath;
    return;
}
?>

<?php require __DIR__ . '/../../partials/tables/two_column.php'; ?>
