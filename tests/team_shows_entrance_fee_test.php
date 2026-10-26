<?php
// Team Shows "Entrance fee" field tests: schema + migration + controller
// wiring + view rendering (form + detail) + list exclusion.
// Catches: missing column, unapplied migration, unvalidated input, missing
// form/detail markup, flash repopulation gaps, fee leaking into list view.
// Run from the command line: php tests/team_shows_entrance_fee_test.php

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
$check = function (bool $condition, string $label) use (&$failures): void {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

$schema = (string) file_get_contents(__DIR__ . '/../sql/schema.sql');
$controller = (string) file_get_contents(__DIR__ . '/../app/controllers/team/shows_save.php');
$showsView = (string) file_get_contents(__DIR__ . '/../app/views/team/shows/shows.php');
$listView = (string) file_get_contents(__DIR__ . '/../app/views/team/shows/list.php');

echo "Team Shows Entrance Fee Test\n";
echo "=============================\n\n";

// --- schema ---
$check((bool) preg_match('/entrance_fee\s+DECIMAL\(10,2\)\s+DEFAULT NULL/', $schema), 'schema.sql has optional entrance_fee DECIMAL(10,2) column');
$check((bool) preg_match('/artist_fee DECIMAL\(10,2\) DEFAULT NULL,\s*\n\s*entrance_fee DECIMAL\(10,2\) DEFAULT NULL,/m', $schema), 'entrance_fee sits after artist_fee in team_shows');

// --- migration ---
$migrationPath = __DIR__ . '/../sql/migrations/2026-10-26_team_shows_entrance_fee.sql';
$check(is_file($migrationPath), 'standalone migration file exists');
$migration = is_file($migrationPath) ? (string) file_get_contents($migrationPath) : '';
$check((bool) preg_match('/ALTER TABLE\s+team_shows.*ADD COLUMN\s+entrance_fee\s+DECIMAL\(10,2\)\s+DEFAULT NULL\s+AFTER artist_fee/s', $migration), 'migration adds entrance_fee after artist_fee');
$check((bool) preg_match('/INSERT INTO schema_migrations.*2026-10-26_team_shows_entrance_fee\.sql/s', $schema), 'schema.sql marks entrance_fee migration applied for fresh installs');

// --- controller wiring ---
$check(str_contains($controller, '\'entrance_fee\' => trim((string) ($_POST[\'entrance_fee\'] ?? \'\'))'), 'payload reads entrance_fee from POST');
$check((bool) preg_match('/Entrance fee must be a non-negative amount\./', $controller), 'entrance fee validation error exists');
$check((bool) preg_match('/str_replace\(\',\',\s*\'.\',\s*\$payload\[\'entrance_fee\'\]\)/', $controller), 'entrance fee normalizes comma to dot');
$check(substr_count($controller, "\$_POST['entrance_fee']") >= 1, 'POST input read');
$check((bool) preg_match('/\(:team_id,:name,:show_date,:show_time,:venue_text,:artist_fee,:entrance_fee,:notes,:created_by\)|:team_id, :name, :show_date, :show_time, :venue_text, :artist_fee, :entrance_fee, :notes, :created_by/', $controller), 'INSERT binds :entrance_fee');
$check((bool) preg_match('/entrance_fee\s*=\s*:entrance_fee/', $controller), 'UPDATE sets entrance_fee = :entrance_fee');
$check(substr_count($controller, 'entrance_fee') >= 8, 'controller wires entrance_fee through payload, validation, flash, insert, update');

// --- shows.php view wiring (defaults, edit populate, flash repopulate) ---
$check((bool) preg_match("/'entrance_fee'\s*=>\s*''/", $showsView), 'form defaults include empty entrance_fee');
$check(str_contains($showsView, '$editShow[\'entrance_fee\']'), 'edit populates entrance_fee from show');
$check(str_contains($showsView, '$showFlash[\'entrance_fee\']'), 'flash repopulates entrance_fee');

// --- list stays compact (no entrance fee column) ---
$check(!str_contains($listView, 'entrance_fee'), 'list view does not show entrance fee');

// --- form renders the field ---
$renderForm = static function (array $formValues, bool $isEdit, ?array $editShow): string {
    $cancelUrl = '';
    $searchQuery = '';
    $showLinks = [];
    $teamSongOptions = [];
    $selectedSongIds = [];
    $activeTeamId = 1;
    ob_start();
    require __DIR__ . '/../app/views/team/shows/form.php';
    return (string) ob_get_clean();
};

$html = $renderForm([
    'name' => 'Test Show',
    'show_date' => '2026-11-01',
    'show_time' => '20:00',
    'venue_text' => 'Test Venue',
    'artist_fee' => '100.00',
    'entrance_fee' => '12.50',
    'notes' => '',
], false, null);
$check(str_contains($html, '<th>Entrance Fee</th>'), 'form shows Entrance Fee label');
preg_match('/<input[^>]*name="entrance_fee"[^>]*>/', $html, $entranceInputMatches);
$entranceInputTag = (string) ($entranceInputMatches[0] ?? '');
$check($entranceInputTag !== '' && str_contains($entranceInputTag, 'type="number"'), 'form input is type number named entrance_fee');
$check(str_contains($entranceInputTag, 'min="0"') && str_contains($entranceInputTag, 'step="0.01"'), 'form input allows non-negative amounts with cents');
$check(str_contains($html, 'value="12.50"'), 'form renders fixture entrance fee value');

// --- detail renders the row ---
$baseUrl = '/index.php';
$baseQuery = ['tab' => 'shows'];
$activeTeamId = 1;
$searchQuery = '';
$showLinks = [];
$activeShowSongs = [];
$activeShow = [
    'id' => 1,
    'name' => 'Test Show',
    'show_date' => '2026-11-01',
    'show_time' => '20:00',
    'venue_text' => 'Test Venue',
    'artist_fee' => '100.00',
    'entrance_fee' => '12.50',
    'notes' => '',
    'created_at' => '2026-10-26 08:00:00',
    'updated_at' => '2026-10-26 08:00:00',
];
ob_start();
require __DIR__ . '/../app/views/team/shows/detail.php';
$html = (string) ob_get_clean();
$check(str_contains($html, 'Entrance Fee'), 'detail shows Entrance Fee row');
$check(str_contains($html, '12.50'), 'detail shows fixture entrance fee value');
$check((bool) preg_match('/Entrance Fee.*12\.50/s', $html), 'detail pairs entrance fee label with value');
$check((bool) preg_match('/Artist Fee.*Entrance Fee/s', $html), 'detail lists entrance fee after artist fee');

echo "\n";
if ($failures > 0) {
    echo $failures . " test(s) FAILED\n";
    exit(1);
}
echo "All entrance fee tests passed\n";