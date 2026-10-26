<?php
// Migration tests: pending computation, ordered application, failure
// isolation, baseline handling, schema.sql coverage.
// Catches: skipped versions never skip migrations, failing migration stops
// the run without applying later ones, fresh installs mark shipped
// migrations applied.

require_once __DIR__ . '/../app/models/install/install_helpers.php';
require_once __DIR__ . '/../app/models/update/migrations.php';

$failures = 0;
$check = function (bool $condition, string $label) use (&$failures) {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

// --- pending computation (skipped versions still migrate) ---
$check(computePendingMigrations(['2026-10-25_a.sql', '2026-11-01_b.sql', '2026-11-02_c.sql'], ['2026-10-25_a.sql']) === ['2026-11-01_b.sql', '2026-11-02_c.sql'], 'pending computed around applied');
$check(computePendingMigrations(['a.sql', 'b.sql'], []) === ['a.sql', 'b.sql'], 'everything pending on baseline-less state');
$check(computePendingMigrations([], ['a.sql']) === [], 'no shipped migrations means nothing to do');
$check(computePendingMigrations(['a.sql'], ['a.sql', 'unknown.sql']) === [], 'unknown applied names ignored');

// --- statement splitting via the shared installer splitter ---
$fixtureSql = "-- comment line\nCREATE TABLE a (id INT);\n\nCREATE TABLE b (id INT);\n-- another comment\nINSERT INTO a VALUES (1);\n";
$statements = splitSqlStatements($fixtureSql);
$check(count($statements) === 3, 'three statements split');
$check($statements[0] === 'CREATE TABLE a (id INT)', 'first statement intact');

// --- ordered application with failure isolation ---
$todo = [
    '2026-10-25_a.sql' => ['CREATE TABLE a (id INT)'],
    '2026-11-01_b.sql' => ['CREATE TABLE b (id INT)'],
    '2026-11-02_c.sql' => ['CREATE TABLE c (id INT)'],
];
$executed = [];
$recorded = [];
$exec = static function (string $statement) use (&$executed): void {
    $executed[] = $statement;
    if (str_contains($statement, 'TABLE b')) {
        throw new RuntimeException('syntax error near b');
    }
};
$record = static function (string $name) use (&$recorded): void {
    $recorded[] = $name;
};
$threw = false;
try {
    applyMigrationsToPdo($todo, $exec, $record);
} catch (RuntimeException $error) {
    $threw = str_contains($error->getMessage(), '2026-11-01_b.sql');
}
$check($threw, 'failing migration throws with its filename');
$check($recorded === ['2026-10-25_a.sql'], 'earlier migration recorded, later ones not applied');
$check(count($executed) === 2, 'execution stopped at the failure');

$okRecorded = [];
$okApplied = applyMigrationsToPdo($todo, static function (): void {
}, static function (string $name) use (&$okRecorded): void {
    $okRecorded[] = $name;
});
$check($okApplied === ['2026-10-25_a.sql', '2026-11-01_b.sql', '2026-11-02_c.sql'], 'all applied in order on success');
$check($okRecorded === $okApplied, 'each applied migration recorded');

// --- shipped migrations on disk ---
$shipped = shippedMigrationNames(__DIR__ . '/../sql/migrations');
$check(in_array('2026-10-25_team_show_songs.sql', $shipped, true), 'shipped migration listed');
$check($shipped === array_values($shipped), 'shipped names sorted');

// --- schema.sql covers fresh installs ---
$schema = (string) file_get_contents(__DIR__ . '/../sql/schema.sql');
$check(str_contains($schema, 'CREATE TABLE schema_migrations'), 'schema.sql creates schema_migrations');
$check((bool) preg_match("/INSERT INTO schema_migrations.*2026-10-25_team_show_songs\.sql/s", $schema), 'schema.sql marks shipped migration applied');

if ($failures > 0) {
    echo "{$failures} test(s) FAILED\n";
    exit(1);
}
echo "All migration tests passed\n";
