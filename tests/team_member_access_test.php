<?php

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

$read = static fn(string $path): string => (string) file_get_contents(__DIR__ . '/../' . $path);
$database = $read('app/models/core/database.php');
$auth = $read('app/models/auth/check.php');
$sidebar = $read('app/partials/sidebar.php');
$controller = $read('app/controllers/team/index.php');
$teamView = $read('app/views/team/index.php');
$templateForm = $read('app/controllers/team/template_form.php');
$templateHelpers = $read('app/models/communication/email_templates_helpers.php');
$mailboxForm = $read('app/controllers/team/mailbox_form.php');
$mailboxHelpers = $read('app/models/communication/mailbox_helpers.php');

$check((bool) preg_match('/function isTeamMember\s*\(/', $database), 'membership check helper exists');
$check(str_contains($auth, "'is_team_member'") && str_contains($auth, 'isTeamMember('), 'auth exposes team membership');
$check((bool) preg_match('/if\s*\(\s*\$isTeamAdmin\s*\|\|\s*\$isTeamMember\s*\)/', $sidebar), 'team navigation is visible to members and admins');
$check(str_contains($controller, 'if ($isTeamMember) {') && str_contains($controller, '$validTabs[]') && str_contains($controller, "'templates'"), 'member tab allowlist includes templates');
$check(str_contains($controller, "\$validTabs = ['tasks', 'shows', 'songs'];") && str_contains($controller, "\$validTabs[] = 'members';"), 'Members tab is excluded for members and allowed for admins');
$templateViewPosition = strpos($teamView, "require __DIR__ . '/templates.php';");
$mailboxAdminGatePosition = strrpos($teamView, '<?php if (!empty($isTeamAdmin)): ?>');
$check($templateViewPosition !== false && $mailboxAdminGatePosition !== false && $templateViewPosition < $mailboxAdminGatePosition, 'templates render for members while mailboxes remain admin-only');
$check((bool) preg_match('/if\s*\(!empty\(\$isTeamMember\)\).*?data-tab="templates".*?endif;/s', $teamView), 'Templates tab is visible to team members');
$check((bool) preg_match('/if\s*\(!empty\(\$isTeamAdmin\)\).*?data-tab="members".*?endif;/s', $teamView), 'Members tab and panel are restricted to team admins');
$check(str_contains($templateForm, "require_once __DIR__ . '/../../models/auth/check.php';") && str_contains($templateForm, 'fetchUserTeams('), 'template form accepts authenticated team members');
$check(!str_contains($templateForm, 'team_admin_check.php'), 'template form does not require team admin role');
$check(!str_contains($templateHelpers, 'tm.role = "admin"') && str_contains($templateHelpers, 'tm.user_id = :user_id'), 'template list and lookup are scoped to team membership');
$check(str_contains($mailboxForm, 'team_admin_check.php') && str_contains($mailboxHelpers, 'tm.role = "admin"'), 'mailbox management remains admin-only');

if ($failures > 0) {
    echo "{$failures} test(s) FAILED\n";
    exit(1);
}

echo "All team member access tests passed\n";
