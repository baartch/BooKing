<?php
// Maintenance tab tests: view rendering + tab order + sidebar dot markup.
// Catches: missing state rows, missing CSRF, update button gating, tab
// placement between SMTP and Logs, dot markup on Admin icon.

if (!function_exists('renderCsrfField')) {
    function renderCsrfField(): void
    {
        echo '<input type="hidden" name="csrf_token" value="test-token">';
    }
}

$failures = 0;
$check = function (bool $condition, string $label) use (&$failures) {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

$renderMaintenance = static function (array $state): string {
    $maintenanceState = $state;
    ob_start();
    require __DIR__ . '/../app/views/admin/maintenance.php';
    return (string) ob_get_clean();
};

// --- update available ---
$html = $renderMaintenance([
    'installed_version' => 'v1.0.0',
    'latest_version' => 'v1.2.0',
    'zip_url' => 'https://example.com/BooKing-v1.2.0.zip',
    'notes_url' => 'https://github.com/baartch/BooKing/releases/tag/v1.2.0',
    'last_check_at' => '2026-10-26T08:00:00+00:00',
    'check_error' => '',
    'available' => true,
    'lock' => null,
    'requirements' => [
        ['label' => 'staging_dir_writable', 'ok' => true],
        ['label' => 'config_writable', 'ok' => true],
    ],
]);
$check(str_contains($html, 'v1.0.0'), 'installed version shown');
$check(str_contains($html, 'v1.2.0'), 'latest version shown');
$check(str_contains($html, 'Check now'), 'check button shown');
$check(str_contains($html, 'value="update_run"'), 'update action present');
$check(str_contains($html, 'Update to v1.2.0'), 'update button names target version');
$check(str_contains($html, 'name="csrf_token"'), 'CSRF token in form');
$check(str_contains($html, 'releases/tag/v1.2.0'), 'release notes link shown');
$check(str_contains($html, 'name="tab" value="maintenance"'), 'tab hidden input present');
$check(!str_contains($html, 'disabled'), 'update button enabled when idle and available');

// --- up to date ---
$html = $renderMaintenance([
    'installed_version' => 'v1.2.0',
    'latest_version' => 'v1.2.0',
    'zip_url' => '',
    'notes_url' => '',
    'last_check_at' => '2026-10-26T08:00:00+00:00',
    'check_error' => '',
    'available' => false,
    'lock' => null,
    'requirements' => [],
]);
$check(!str_contains($html, 'value="update_run"'), 'no update action when up to date');
$check(str_contains($html, 'Check now'), 'check button still shown');
$check(str_contains($html, 'v1.2.0'), 'version shown when up to date');

// --- failed check ---
$html = $renderMaintenance([
    'installed_version' => 'v1.0.0',
    'latest_version' => '',
    'zip_url' => '',
    'notes_url' => '',
    'last_check_at' => '2026-10-26T08:00:00+00:00',
    'check_error' => 'HTTP 403',
    'available' => false,
    'lock' => null,
    'requirements' => [],
]);
$check(str_contains($html, 'HTTP 403'), 'check error surfaced');
$check(!str_contains($html, 'value="update_run"'), 'no update action after failed check');

// --- update running: button disabled ---
$html = $renderMaintenance([
    'installed_version' => 'v1.0.0',
    'latest_version' => 'v1.2.0',
    'zip_url' => 'https://example.com/BooKing-v1.2.0.zip',
    'notes_url' => '',
    'last_check_at' => '2026-10-26T08:00:00+00:00',
    'check_error' => '',
    'available' => true,
    'lock' => null,
    'running' => true,
    'requirements' => [],
]);
$check(str_contains($html, 'disabled'), 'update button disabled while running');

// --- stale running state (crashed update) must not disable the button ---
$html = $renderMaintenance([
    'installed_version' => 'v1.0.0',
    'latest_version' => 'v1.2.0',
    'zip_url' => 'https://example.com/BooKing-v1.2.0.zip',
    'notes_url' => '',
    'last_check_at' => '2026-10-26T08:00:00+00:00',
    'check_error' => '',
    'available' => true,
    'lock' => ['state' => 'running', 'target_version' => 'v1.2.0', 'started_at' => '2026-10-01T00:00:00+00:00'],
    'running' => false,
    'requirements' => [['label' => 'staging_dir_writable', 'ok' => true]],
]);
$check(!str_contains($html, 'disabled'), 'button enabled when running state is stale');

// --- unknown installed version ---
$html = $renderMaintenance([
    'installed_version' => '',
    'latest_version' => 'v1.2.0',
    'zip_url' => '',
    'notes_url' => '',
    'last_check_at' => '',
    'check_error' => '',
    'available' => false,
    'lock' => null,
    'requirements' => [],
]);
$check(str_contains($html, 'Unknown'), 'unknown install version labeled');
$check(!str_contains($html, 'value="update_run"'), 'no update offered without installed version');

// --- tab placement between SMTP and Logs in admin index ---
$adminIndex = (string) file_get_contents(__DIR__ . '/../app/controllers/admin/index.php');
$smtpPos = strpos($adminIndex, 'data-tab="smtp"');
$maintenancePos = strpos($adminIndex, 'data-tab="maintenance"');
$logsPos = strpos($adminIndex, 'data-tab="logs"');
$check($smtpPos !== false && $maintenancePos !== false && $logsPos !== false, 'maintenance tab markup exists');
$check($smtpPos < $maintenancePos && $maintenancePos < $logsPos, 'maintenance tab sits between SMTP and Logs');
$check(str_contains($adminIndex, "data-tab-panel=\"maintenance\""), 'maintenance panel exists');
$check(str_contains($adminIndex, 'views/admin/maintenance.php'), 'maintenance view required');

// --- bootstrap validation ---
$bootstrap = (string) file_get_contents(__DIR__ . '/../app/controllers/admin/bootstrap.php');
$check(str_contains($bootstrap, "'maintenance'"), 'maintenance in valid tabs');
$check(str_contains($adminIndex, "require_once __DIR__ . '/maintenance.php'"), 'maintenance controller loaded');

// --- sidebar dot inside the admin anchor ---
$sidebar = (string) file_get_contents(__DIR__ . '/../app/partials/sidebar.php');
$check(str_contains($sidebar, 'app/controllers/admin/index.php'), 'admin anchor present in sidebar');
$check(str_contains($sidebar, "Admin (update available)"), 'admin anchor announces update availability');
$dotPos = strpos($sidebar, 'update-dot');
$check($dotPos !== false, 'update dot markup present in sidebar');

// --- CSS dot style ---
$css = (string) file_get_contents(__DIR__ . '/../app/public/css/global.css');
$check(str_contains($css, '.update-dot'), 'update dot style present');

if ($failures > 0) {
    echo "{$failures} test(s) FAILED\n";
    exit(1);
}
echo "All maintenance view tests passed\n";
