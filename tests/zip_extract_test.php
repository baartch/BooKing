<?php
// Zip extraction tests: path traversal rejection, safe extraction, corruption.
// Catches: '../' escapes, absolute paths, backslash tricks, partial extraction
// on bad archives, corrupt archive handling.

require_once __DIR__ . '/../app/models/update/zip_extract.php';

$failures = 0;
$check = function (bool $condition, string $label) use (&$failures) {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

$fixtureDir = sys_get_temp_dir() . '/booking-zip-test-' . getmypid();
if (is_dir($fixtureDir)) {
    shell_exec('rm -rf ' . escapeshellarg($fixtureDir));
}
mkdir($fixtureDir, 0777, true);

$buildZip = function (string $name, array $entries, bool $stored = false) use ($fixtureDir): string {
    static $builder = null;
    if ($builder === null) {
        $builder = $fixtureDir . '/mkzip.py';
        file_put_contents($builder, <<<'PY'
import sys
import zipfile

path, spec = sys.argv[1], sys.argv[2]
stored = len(sys.argv) > 3 and sys.argv[3] == 'stored'
compression = zipfile.ZIP_STORED if stored else zipfile.ZIP_DEFLATED
zf = zipfile.ZipFile(path, 'w')
for pair in spec.split('||'):
    name, content = pair.split('::', 1)
    zf.writestr(name, content, compress_type=compression)
zf.close()
PY
        );
    }

    $path = $fixtureDir . '/' . $name;
    $specParts = [];
    foreach ($entries as $entryName => $content) {
        $specParts[] = $entryName . '::' . $content;
    }
    shell_exec(sprintf(
        'python3 %s %s %s%s 2>&1',
        escapeshellarg($builder),
        escapeshellarg($path),
        escapeshellarg(implode('||', $specParts)),
        $stored ? ' stored' : ''
    ));

    return $path;
};

// --- isSafeArchiveEntry ---
$check(isSafeArchiveEntry('app/models/a.php') === true, 'normal nested path accepted');
$check(isSafeArchiveEntry('index.php') === true, 'root file accepted');
$check(isSafeArchiveEntry('sql/migrations/2026-10-25_team_show_songs.sql') === true, 'deep path accepted');
$check(isSafeArchiveEntry('../evil.php') === false, 'parent traversal rejected');
$check(isSafeArchiveEntry('/abs.php') === false, 'absolute path rejected');
$check(isSafeArchiveEntry('..\\\\win.php') === false, 'backslash traversal rejected');
$check(isSafeArchiveEntry('app/../../x.php') === false, 'nested parent traversal rejected');
$check(isSafeArchiveEntry('') === false, 'empty entry rejected');
$check(isSafeArchiveEntry('C:/abs.php') === false, 'drive letter rejected');
$check(isSafeArchiveEntry('app//a.php') === false, 'empty path segment rejected');

// --- safe extraction ---
$goodZip = $buildZip('good.zip', [
    'app/a.php' => '<?php // good A',
    'sql/x.sql' => 'SELECT 1;',
    'sql/migrations/2026-10-25_team_show_songs.sql' => '-- migration',
    'index.php' => '<?php // index',
], 1);
$goodDest = $fixtureDir . '/out-good';
$extracted = extractReleaseArchive($goodZip, $goodDest);
$check(count($extracted) === 4, 'all four entries extracted');
$check(in_array('sql/x.sql', $extracted, true), 'extracted list returned');
$check(file_get_contents($goodDest . '/app/a.php') === '<?php // good A', 'deflated file extracted with content');
$check(file_get_contents($goodDest . '/sql/migrations/2026-10-25_team_show_songs.sql') === '-- migration', 'nested file extracted');
$check(is_file($goodDest . '/index.php'), 'root file extracted');

// --- stored (uncompressed) entries ---
$storedZip = $buildZip('stored.zip', ['stored.txt' => 'plain stored content'], true);
$storedDest = $fixtureDir . '/out-stored';
$extracted = extractReleaseArchive($storedZip, $storedDest);
$check(file_get_contents($storedDest . '/stored.txt') === 'plain stored content', 'stored entry extracted');

// --- malicious archive aborts and extracts nothing ---
$badZip = $buildZip('bad.zip', [
    'app/ok.php' => 'fine',
    '../evil.php' => 'evil',
], 1);
$badDest = $fixtureDir . '/out-bad';
$threw = false;
try {
    extractReleaseArchive($badZip, $badDest);
} catch (RuntimeException $error) {
    $threw = $error->getMessage() !== '';
}
$check($threw, 'unsafe archive throws');
$check(!is_dir($badDest) || (glob($badDest . '/*') === [] && glob($badDest . '/app/*') === []), 'unsafe archive extracts nothing');

// --- corrupt archive ---
$corruptPath = $fixtureDir . '/corrupt.zip';
file_put_contents($corruptPath, 'this is not a zip file at all');
$threw = false;
try {
    extractReleaseArchive($corruptPath, $fixtureDir . '/out-corrupt');
} catch (RuntimeException $error) {
    $threw = true;
}
$check($threw, 'corrupt archive throws');

shell_exec('rm -rf ' . escapeshellarg($fixtureDir));

if ($failures > 0) {
    echo "{$failures} test(s) FAILED\n";
    exit(1);
}
echo "All zip extraction tests passed\n";
