<?php
// Update engine tests: lock, download, verify, replace, stale deletion.
// Catches: hash mismatch, missing manifest, failed download, concurrent
// updates, managed-path guard, preserved local files, stale file removal.

require_once __DIR__ . '/../app/models/update/zip_extract.php';
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

$base = sys_get_temp_dir() . '/booking-updater-test-' . getmypid();
shell_exec('rm -rf ' . escapeshellarg($base));

// Zip builder (same approach as zip_extract_test).
$mkzip = function (string $path, array $entries) use ($base): void {
    static $builder = null;
    if ($builder === null) {
        $builder = $base . '-tools/mkzip.py';
        @mkdir(dirname($builder), 0777, true);
        file_put_contents($builder, <<<'PY'
import sys
import zipfile

path, spec = sys.argv[1], sys.argv[2]
zf = zipfile.ZipFile(path, 'w')
for pair in spec.split('||'):
    name, content = pair.split('::', 1)
    zf.writestr(name, content, compress_type=zipfile.ZIP_DEFLATED)
zf.close()
PY
        );
    }
    $specParts = [];
    foreach ($entries as $name => $content) {
        $specParts[] = $name . '::' . $content;
    }
    shell_exec(sprintf(
        'python3 %s %s %s 2>&1',
        escapeshellarg($builder),
        escapeshellarg($path),
        escapeshellarg(implode('||', $specParts))
    ));
};

/** Build an old install fixture: version.php, manifest.json, managed files, protected local files. */
$makeOldInstall = function () use ($base): void {
    shell_exec('rm -rf ' . escapeshellarg($base));
    @mkdir($base . '/app', 0777, true);
    @mkdir($base . '/config', 0777, true);
    @mkdir($base . '/uploads', 0777, true);
    file_put_contents($base . '/app/a.php', 'old A content');
    file_put_contents($base . '/app/old.php', 'to be removed');
    file_put_contents($base . '/index.php', '<?php // old index');
    file_put_contents($base . '/app/version.php', "<?php\ndefine('APP_VERSION', 'v1.0.0');\n");
    $files = [
        'app/a.php' => hash('sha256', 'old A content'),
        'app/old.php' => hash('sha256', 'to be removed'),
        'app/version.php' => hash('sha256', "<?php\ndefine('APP_VERSION', 'v1.0.0');\n"),
        'index.php' => hash('sha256', '<?php // old index'),
    ];
    file_put_contents($base . '/manifest.json', json_encode(['version' => 'v1.0.0', 'files' => $files]));
    file_put_contents($base . '/config/config.php', 'SECRET_LOCAL_CONFIG');
    file_put_contents($base . '/config/installer.lock', '{"state":"completed"}');
    file_put_contents($base . '/uploads/data.txt', 'local upload data');
};

/** Build a release zip fixture and return its bytes. */
$makeReleaseZip = static function (callable $mutateManifest = null, array $extraEntries = [], array $skipEntries = []) use ($base, $mkzip): string {
    $entries = [
        'app/a.php' => 'new A content v1.1.0',
        'app/new.php' => 'brand new file',
        'index.php' => '<?php // new index',
        'app/version.php' => "<?php\ndefine('APP_VERSION', 'v1.1.0');\n",
        'sql/migrations/2026-11-01_example.sql' => 'ALTER TABLE example ADD note VARCHAR(255);',
    ];
    $entries = array_merge($entries, $extraEntries);
    foreach ($skipEntries as $skip) {
        unset($entries[$skip]);
    }
    $files = [];
    foreach ($entries as $name => $content) {
        $files[$name] = hash('sha256', $content);
    }
    $manifest = ['version' => 'v1.1.0', 'files' => $files];
    if ($mutateManifest !== null) {
        $manifest = $mutateManifest($manifest);
    }
    if (!in_array('manifest.json', $skipEntries, true)) {
        $entries['manifest.json'] = json_encode($manifest, JSON_PRETTY_PRINT);
    }

    $zipPath = $base . '-tools/release.zip';
    @mkdir(dirname($zipPath), 0777, true);
    $mkzip($zipPath, $entries);

    return (string) file_get_contents($zipPath);
};

$target = ['tag' => 'v1.1.0', 'zip_url' => 'https://example.com/BooKing-v1.1.0.zip'];
$okFetch = static fn (): array => ['ok' => true, 'status' => 200, 'body' => $makeReleaseZip(), 'error' => ''];

// --- happy path ---
$makeOldInstall();
$migrationDirs = [];
$migrationRunner = static function (string $dir) use (&$migrationDirs): array {
    $migrationDirs[] = $dir;
    return [];
};
$result = runApplicationUpdate($target, $okFetch, $base, $migrationRunner);
$check($result['ok'] === true, 'update succeeds: ' . ($result['error'] ?? ''));
$check(file_get_contents($base . '/app/a.php') === 'new A content v1.1.0', 'managed file replaced');
$check(file_get_contents($base . '/app/new.php') === 'brand new file', 'new file created');
$check(!is_file($base . '/app/old.php'), 'stale file removed');
$check(file_get_contents($base . '/index.php') === '<?php // new index', 'root file replaced');
$check(strpos((string) file_get_contents($base . '/app/version.php'), "v1.1.0") !== false, 'version bumped');
$check(strpos((string) file_get_contents($base . '/manifest.json'), 'v1.1.0') !== false, 'manifest installed');
$check(file_get_contents($base . '/config/config.php') === 'SECRET_LOCAL_CONFIG', 'local config preserved');
$check(file_get_contents($base . '/config/installer.lock') === '{"state":"completed"}', 'installer lock preserved');
$check(file_get_contents($base . '/uploads/data.txt') === 'local upload data', 'uploads preserved');
$check(count($migrationDirs) === 1, 'migration runner called once');
$lockState = fetchUpdateLockState($base);
$check(($lockState['state'] ?? '') === 'done', 'lock state done');

// --- failure: hash mismatch, nothing changes ---
$makeOldInstall();
$liedManifest = static function (array $manifest): array {
    $manifest['files']['index.php'] = str_repeat('0', 64);
    return $manifest;
};
$failedResult = runApplicationUpdate($target, static fn (): array => ['ok' => true, 'status' => 200, 'body' => $makeReleaseZip($liedManifest), 'error' => ''], $base, $migrationRunner);
$check($failedResult['ok'] === false, 'hash mismatch fails');
$check(strpos((string) $failedResult['error'], 'index.php') !== false, 'hash error names the file');
$check(file_get_contents($base . '/app/a.php') === 'old A content', 'failed update changes nothing (a.php)');
$check(is_file($base . '/app/old.php'), 'failed update removes nothing');
$lockState = fetchUpdateLockState($base);
$check(($lockState['state'] ?? '') === 'failed', 'lock state failed after hash mismatch');

// --- failure: zip without manifest ---
$makeOldInstall();
$noManifestResult = runApplicationUpdate($target, static fn (): array => ['ok' => true, 'status' => 200, 'body' => $makeReleaseZip(null, [], ['manifest.json']), 'error' => ''], $base, $migrationRunner);
$check($noManifestResult['ok'] === false, 'missing manifest fails');
$check(file_get_contents($base . '/app/a.php') === 'old A content', 'no-manifest update changes nothing');

// --- failure: download error ---
$makeOldInstall();
$downloadResult = runApplicationUpdate($target, static fn (): array => ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'boom'], $base, $migrationRunner);
$check($downloadResult['ok'] === false, 'download failure fails');
$check(strpos((string) $downloadResult['error'], 'boom') !== false, 'download error surfaced');

// --- failure: concurrent update ---
$makeOldInstall();
$lockPath = $base . '/config/update.lock';
$lockHandle = fopen($lockPath, 'c+');
flock($lockHandle, LOCK_EX | LOCK_NB);
$concurrentResult = runApplicationUpdate($target, $okFetch, $base, $migrationRunner);
flock($lockHandle, LOCK_UN);
fclose($lockHandle);
$check($concurrentResult['ok'] === false, 'concurrent update refused');
$check(strpos((string) $concurrentResult['error'], 'already') !== false, 'concurrent error mentions already running');
$check(file_get_contents($base . '/app/a.php') === 'old A content', 'concurrent attempt changes nothing');

// --- failure: manifest lists an unmanaged path ---
$makeOldInstall();
$evilManifest = static function (array $manifest): array {
    $manifest['files']['config/config.php'] = hash('sha256', 'evil overwrite');
    return $manifest;
};
$guardResult = runApplicationUpdate($target, static fn (): array => ['ok' => true, 'status' => 200, 'body' => $makeReleaseZip($evilManifest, ['config/config.php' => 'evil overwrite']), 'error' => ''], $base, $migrationRunner);
$check($guardResult['ok'] === false, 'unmanaged manifest path rejected');
$check(file_get_contents($base . '/config/config.php') === 'SECRET_LOCAL_CONFIG', 'local config untouched by guard');

// --- failure: downgrade attempt ---
$makeOldInstall();
$downgradeResult = runApplicationUpdate(['tag' => 'v0.9.0', 'zip_url' => 'https://example.com/BooKing-v0.9.0.zip'], $okFetch, $base, $migrationRunner);
$check($downgradeResult['ok'] === false, 'downgrade refused');

// --- stale 'running' lock state must not block future updates ---
$makeOldInstall();
$lockPath = $base . '/config/update.lock';
@mkdir(dirname($lockPath), 0777, true);
file_put_contents($lockPath, json_encode(['state' => 'running', 'target_version' => 'v1.1.0']));
$check(isUpdateRunning($base) === false, 'running state without a held lock is stale');
$staleResult = runApplicationUpdate($target, $okFetch, $base, $migrationRunner);
$check($staleResult['ok'] === true, 'stale running state does not block update: ' . ($staleResult['error'] ?? ''));
$heldHandle = fopen($lockPath, 'c+');
flock($heldHandle, LOCK_EX | LOCK_NB);
$check(isUpdateRunning($base) === true, 'held lock reports running');
flock($heldHandle, LOCK_UN);
fclose($heldHandle);

// --- staging cleanup without shell_exec + deny file re-verified each run ---
$makeOldInstall();
$removeDir = $base . '/remove-me/nested/deep';
mkdir($removeDir, 0777, true);
file_put_contents($removeDir . '/file.txt', 'gone');
removeDirectoryRecursive($base . '/remove-me');
$check(!is_dir($base . '/remove-me'), 'recursive directory delete works');

$stagingRoot = $base . '/uploads/update_staging';
mkdir($stagingRoot, 0777, true);
if (is_file($stagingRoot . '/.htaccess')) {
    unlink($stagingRoot . '/.htaccess');
}
$recreatedResult = runApplicationUpdate($target, $okFetch, $base, $migrationRunner);
$check($recreatedResult['ok'] === true, 'update succeeds without deny file');
$check((string) file_get_contents($stagingRoot . '/.htaccess') === "Require all denied\n", 'staging deny file re-created every run');
$leftovers = array_values(array_diff(scandir($stagingRoot) ?: [], ['.', '..', '.htaccess']));
$check($leftovers === [], 'staging run directory cleaned after success');

// --- failed replace must not bump version.php (stay retryable) ---
$makeOldInstall();
$asDirectory = $base . '/sql/migrations/2026-11-01_example.sql';
@mkdir(dirname($asDirectory), 0777, true);
mkdir($asDirectory, 0777, true);
$replaceFailResult = runApplicationUpdate($target, $okFetch, $base, $migrationRunner);
$check($replaceFailResult['ok'] === false, 'replace failure fails');
$check(strpos((string) $replaceFailResult['error'], '2026-11-01_example.sql') !== false, 'replace error names the file');
$check(installedAppVersion($base) === 'v1.0.0', 'version.php not installed when a later copy fails');
removeDirectoryRecursive($asDirectory);
$retryResult = runApplicationUpdate($target, $okFetch, $base, $migrationRunner);
$check($retryResult['ok'] === true, 'failed replace stays retryable: ' . ($retryResult['error'] ?? ''));

// --- requirements reported ---
$requirements = updateRequirements($base);
$check(is_array($requirements) && count($requirements) >= 3, 'requirements listed');
$wellFormed = true;
foreach ($requirements as $row) {
    if (!isset($row['label'], $row['ok'])) {
        $wellFormed = false;
    }
}
$check($wellFormed, 'requirement rows well-formed');

shell_exec('rm -rf ' . escapeshellarg($base) . ' ' . escapeshellarg($base . '-tools'));

if ($failures > 0) {
    echo "{$failures} test(s) FAILED\n";
    exit(1);
}
echo "All updater tests passed\n";
