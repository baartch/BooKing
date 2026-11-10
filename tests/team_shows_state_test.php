<?php
// Show state tests: migration/schema, save validation and persistence wiring,
// form selections, and colored Font Awesome state indicators in the list.

if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}

if (!function_exists('renderCsrfField')) {
    function renderCsrfField(): void
    {
        echo '<input type="hidden" name="csrf_token" value="test-token">';
    }
}

if (!function_exists('getCsrfToken')) {
    function getCsrfToken(): string
    {
        return 'test-token';
    }
}

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

$read = static fn (string $path): string => (string) file_get_contents(__DIR__ . '/../' . $path);
$schema = $read('sql/schema.sql');
$controller = $read('app/controllers/team/shows_save.php');
$model = $read('app/models/team/shows.php');
$showsView = $read('app/views/team/shows/shows.php');
$migrationPath = __DIR__ . '/../sql/migrations/2026-11-10_team_shows_state.sql';
$migration = is_file($migrationPath) ? (string) file_get_contents($migrationPath) : '';

echo "Team Shows State Test\n";
echo "=====================\n\n";

$expectedStates = "'provisional', 'definitely', 'canceled'";
$check(str_contains($schema, "state ENUM({$expectedStates}) NOT NULL DEFAULT 'provisional'"), 'fresh-install schema defines constrained state with provisional default');
$check(str_contains($migration, "ADD COLUMN state ENUM({$expectedStates}) NOT NULL DEFAULT 'provisional'"), 'migration adds constrained state with provisional default');
$check(str_contains($schema, "2026-11-10_team_shows_state.sql"), 'fresh-install schema marks the migration applied');
$check(str_contains($model, 's.state'), 'show list query retrieves state');
$check(str_contains($controller, "'state' => trim((string) (\$_POST['state'] ?? 'provisional'))"), 'save controller reads state with provisional fallback');
$check(str_contains($controller, "['provisional', 'definitely', 'canceled']"), 'save controller validates all allowed states');
$check(str_contains($controller, "if (!array_key_exists('state', \$_POST))") && str_contains($controller, "\$payload['state'] = (string) (\$existing['state']"), 'update without a state preserves the saved state');
$check(str_contains($controller, 'state = :state'), 'show update persists state');
$check(str_contains($controller, 'created_by, state') && str_contains($controller, ':created_by, :state'), 'show insert persists state');
$check((bool) preg_match("/'state'\s*=>\s*'provisional'/", $showsView), 'new show form defaults to provisional');
$check(str_contains($showsView, "\$showFlash['state']"), 'failed form submission repopulates state');

$renderForm = static function (array $formValues): string {
    $cancelUrl = '';
    $searchQuery = '';
    $showLinks = [];
    $teamSongOptions = [];
    $selectedSongIds = [];
    $activeTeamId = 1;
    $isEdit = false;
    $editShow = null;
    ob_start();
    require __DIR__ . '/../app/views/team/shows/form.php';
    return (string) ob_get_clean();
};

$formHtml = $renderForm(['state' => 'provisional']);
foreach (['provisional', 'definitely', 'canceled'] as $state) {
    $check((bool) preg_match('/<input[^>]*type="radio"[^>]*name="state"[^>]*value="' . $state . '"/', $formHtml), "form renders {$state} radio option");
}
$check((bool) preg_match('/name="state"[^>]*value="provisional"[^>]*checked/', $formHtml), 'provisional radio selected by default');
$definitelyFormHtml = $renderForm(['state' => 'definitely']);
$check((bool) preg_match('/name="state"[^>]*value="definitely"[^>]*checked/', $definitelyFormHtml), 'definitely radio remains selected when editing');

$listRows = [
    ['id' => 1, 'show_date' => '2026-11-01', 'venue_text' => 'Test Venue', 'artist_fee' => '100.00', 'state' => 'provisional'],
    ['id' => 2, 'show_date' => '2026-11-02', 'venue_text' => 'Test Venue', 'artist_fee' => '100.00', 'state' => 'definitely'],
    ['id' => 3, 'show_date' => '2026-11-03', 'venue_text' => 'Test Venue', 'artist_fee' => '100.00', 'state' => 'canceled'],
];
$shows = $listRows;
$baseUrl = '/index.php';
$baseQuery = ['tab' => 'shows'];
$activeTeamId = 1;
$searchQuery = '';
ob_start();
require __DIR__ . '/../app/views/team/shows/list.php';
$listHtml = (string) ob_get_clean();
$check((bool) preg_match('/<th>\s*State\s*<\/th>/', $listHtml), 'list includes a State column');
foreach ([
    'provisional' => ['fa-circle-question', 'has-text-warning'],
    'definitely' => ['fa-circle-check', 'has-text-success'],
    'canceled' => ['fa-circle-xmark', 'has-text-danger'],
] as $label => [$icon, $color]) {
    $check(str_contains($listHtml, $icon) && str_contains($listHtml, $color) && str_contains($listHtml, ucfirst($label)), "list renders {$label} label with colored Font Awesome icon");
}

echo "\n";
if ($failures > 0) {
    echo $failures . " test(s) FAILED\n";
    exit(1);
}
echo "All show state tests passed\n";
