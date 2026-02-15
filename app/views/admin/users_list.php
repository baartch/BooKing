<?php
/**
 * Variables expected:
 * - array $users
 * - array $teamsByUser
 * - string $baseUrl
 * - array $baseQuery
 */
require_once __DIR__ . '/../../src-php/core/list_helpers.php';

$listColumns = [
    buildListColumn('Email', 'username'),
    buildListColumn('Displayname', 'display_name'),
    buildListColumn('Role', null, static function (array $user): string {
        return ucfirst((string) ($user['role'] ?? ''));
    }, false),
    buildListColumn('Teams', null, static function (array $user) use ($teamsByUser): string {
        $userId = (int) ($user['id'] ?? 0);
        $teams = $teamsByUser[$userId] ?? [];
        return $teams ? implode(', ', $teams) : 'No teams';
    }, false),
    buildListColumn('Created', 'created_at')
];

$listRows = $users;
$listEmptyMessage = 'No users found.';

$listRowLink = static function (array $user) use ($baseUrl, $baseQuery): array {
    $userId = (int) ($user['id'] ?? 0);
    $detailLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['user_id' => $userId]));

    return [
        'href' => $detailLink,
        'hx-get' => $detailLink,
        'hx-target' => '#admin-user-detail-panel',
        'hx-swap' => 'innerHTML',
        'hx-push-url' => $detailLink
    ];
};

$listRowActions = static function (array $user) use ($baseUrl, $baseQuery): string {
    $userId = (int) ($user['id'] ?? 0);
    $editLink = $baseUrl . '?' . http_build_query(array_merge($baseQuery, ['edit_user_id' => $userId]));
    ob_start();
    ?>
      <div class="buttons are-small is-justify-content-flex-end">
        <a class="button" href="<?php echo htmlspecialchars($editLink); ?>" aria-label="Edit user" title="Edit user">
          <span class="icon"><i class="fa-solid fa-pen"></i></span>
        </a>
        <form method="POST" action="" onsubmit="return confirm('Delete this user?');">
          <?php renderCsrfField(); ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="tab" value="users">
          <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
          <button type="submit" class="button" aria-label="Delete user" title="Delete user">
            <span class="icon"><i class="fa-solid fa-trash"></i></span>
          </button>
        </form>
      </div>
    <?php
    return (string) ob_get_clean();
};

$listActionsLabel = 'Actions';

require __DIR__ . '/../../partials/tables/list.php';
