<?php
// End-to-end update simulation on a trimmed copy of the real app tree.
// Catches: real-file replacement, real stale deletion, migrations dir
// handoff, version bump — using the actual updater code path.

require_once __DIR__ . '/../app/models/update/updater.php';

$failures = 0;
$check = function (bool $condition, string $label) use (&$failures) {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

$repoRoot = realpath(__DIR__ . '/..');
$fixtureRoot = sys_get_temp_dir() . '/booking-e2e-' . getmypid();
shell_exec('rm -rf ' . escapeshellarg($fixtureRoot));
mkdir($fixtureRoot, 0777, true);

// --- old install (v0.1.0) built from the real tree ---
$oldFiles = [
    'app/controllers/tasks/taskhandler.php' => (string) file_get_contents($repoRoot . '/app/controllers/tasks/taskhandler.php'),
    'app/models/core/version.php' => (string) file_get_contents($repoRoot . '/app/models/core/version.php'),
    'sql/schema.sql' => (string) file_get_contents($repoRoot . '/sql/schema.sql'),
    'sql/migrations/2026-10-25_team_show_songs.sql' => (string) file_get_contents($repoRoot . '/sql/migrations/2026-10-25_team_show_songs.sql'),
    'index.php' => (string) file_get_contents($repoRoot . '/index.php'),
    'favicon.ico' => (string) file_get_contents($repoRoot . '/favicon.ico'),
    'app/models/update/legacy_removed.php' => '<?php // will be removed in v0.2.0',
];
$oldManifestFiles = [];
foreach ($oldFiles as $relative => $content) {
    $destination = $fixtureRoot . '/' . $relative;
    @mkdir(dirname($destination), 0777, true);
    file_put_contents($destination, $content);
    $oldManifestFiles[$relative] = hash('sha256', $content);
}
file_put_contents($fixtureRoot . '/app/version.php', "<?php\ndefine('APP_VERSION', 'v0.1.0');\n");
file_put_contents($fixtureRoot . '/manifest.json', json_encode(['version' => 'v0.1.0', 'files' => $oldManifestFiles]));
@mkdir($fixtureRoot . '/config', 0777, true);
file_put_contents($fixtureRoot . '/config/config.php', 'LOCAL_SECRET');
@mkdir($fixtureRoot . '/uploads', 0777, true);
file_put_contents($fixtureRoot . '/uploads/attachment.txt', 'kept local data');

// --- new release (v0.2.0): changed + added + removed files, migration added ---
$newFiles = $oldFiles;
$newFiles['app/controllers/tasks/taskhandler.php'] .= "\n// changed in v0.2.0\n";
$newFiles['app/models/update/new_feature.php'] = "<?php\n// added in v0.2.0\n";
$newFiles['sql/migrations/2026-11-05_e2e.sql'] = "ALTER TABLE example ADD e2e VARCHAR(10);";
unset($newFiles['app/models/update/legacy_removed.php']);
$newFiles['app/version.php'] = "<?php\ndefine('APP_VERSION', 'v0.2.0');\n";
$newManifestFiles = [];
foreach ($newFiles as $relative => $content) {
    $newManifestFiles[$relative] = hash('sha256', $content);
}
$newManifest = json_encode(['version' => 'v0.2.0', 'files' => $newManifestFiles], JSON_PRETTY_PRINT);

$mkzip = $fixtureRoot . '-mkzip.py';
file_put_contents($mkzip, <<<'PY'
import os
import sys
import zipfile

src_dir, zip_path = sys.argv[1], sys.argv[2]
zf = zipfile.ZipFile(zip_path, 'w')
for root, dirs, files in os.walk(src_dir):
    for name in sorted(files):
        full = os.path.join(root, name)
        zf.write(full, os.path.relpath(full, src_dir), compress_type=zipfile.ZIP_DEFLATED)
zf.close()
PY
);

// Stage the release tree on disk (binary-safe), then zip it.
$releaseSrc = $fixtureRoot . '-release-src';
foreach ($newFiles as $relative => $content) {
    $destination = $releaseSrc . '/' . $relative;
    @mkdir(dirname($destination), 0777, true);
    file_put_contents($destination, $content);
}
file_put_contents($releaseSrc . '/manifest.json', $newManifest);

$zipPath = $fixtureRoot . '-release.zip';
shell_exec(sprintf(
    'python3 %s %s %s 2>&1',
    escapeshellarg($mkzip),
    escapeshellarg($releaseSrc),
    escapeshellarg($zipPath)
));

$zipBytes = (string) file_get_contents($zipPath);
$check($zipBytes !== '', 'release zip fixture built');

// --- run the update with a recording migration runner ---
$migrationCalls = [];
$migrationRunner = static function (string $dir) use (&$migrationCalls): array {
    $migrationCalls[] = array_map('basename', glob($dir . '/*.sql') ?: []);
    return [];
};

$result = runApplicationUpdate(
    ['tag' => 'v0.2.0', 'zip_url' => 'https://example.com/BooKing-v0.2.0.zip'],
    static fn (): array => ['ok' => true, 'status' => 200, 'body' => $zipBytes, 'error' => ''],
    $fixtureRoot,
    $migrationRunner
);

$check($result['ok'] === true, 'e2e update succeeds: ' . ($result['error'] ?? ''));
$check(str_contains((string) file_get_contents($fixtureRoot . '/app/controllers/tasks/taskhandler.php'), 'changed in v0.2.0'), 'changed file replaced');
$check(is_file($fixtureRoot . '/app/models/update/new_feature.php'), 'added file installed');
$check(!is_file($fixtureRoot . '/app/models/update/legacy_removed.php'), 'removed file deleted');
$check(is_file($fixtureRoot . '/sql/migrations/2026-11-05_e2e.sql'), 'new migration file installed');
$check(strpos((string) file_get_contents($fixtureRoot . '/app/version.php'), 'v0.2.0') !== false, 'version stamped v0.2.0');
$check(installedAppVersion($fixtureRoot) === 'v0.2.0', 'installedAppVersion reports v0.2.0');
$check(file_get_contents($fixtureRoot . '/config/config.php') === 'LOCAL_SECRET', 'local config preserved');
$check(file_get_contents($fixtureRoot . '/uploads/attachment.txt') === 'kept local data', 'uploads preserved');
$check(count($migrationCalls) === 1, 'migration runner called once');
$check(in_array('2026-10-25_team_show_songs.sql', $migrationCalls[0] ?? [], true), 'old migration shipped to runner');
$check(in_array('2026-11-05_e2e.sql', $migrationCalls[0] ?? [], true), 'new migration shipped to runner');
$lockState = fetchUpdateLockState($fixtureRoot);
$check(($lockState['state'] ?? '') === 'done', 'final lock state done');

shell_exec('rm -rf ' . escapeshellarg($fixtureRoot) . ' ' . escapeshellarg($mkzip) . ' ' . escapeshellarg($zipPath) . ' ' . escapeshellarg($releaseSrc));

if ($failures > 0) {
    echo "{$failures} test(s) FAILED\n";
    exit(1);
}
echo "All end-to-end update tests passed\n";
