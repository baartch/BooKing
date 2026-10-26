<?php
// Version helper tests: installedAppVersion + isNewerReleaseVersion.
// Catches: bad tag comparison, unknown-install handling, fixture loading.

require_once __DIR__ . '/../app/models/update/version_compare.php';
require_once __DIR__ . '/../app/models/core/version.php';

$failures = 0;
$check = function (bool $condition, string $label) use (&$failures) {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

$check(isNewerReleaseVersion('v1.1.0', 'v1.0.9') === true, 'newer patch is newer');
$check(isNewerReleaseVersion('v1.0.0', 'v1.0.0') === false, 'same version is not newer');
$check(isNewerReleaseVersion('v2.0', 'v1.9.9') === true, 'shorter tag with bigger major is newer');
$check(isNewerReleaseVersion('v1.0.0', '') === true, 'unknown installed version accepts any stable release');
$check(isNewerReleaseVersion('latest', 'v1.0.0') === false, 'non-numeric candidate rejected');
$check(isNewerReleaseVersion('v1.10.0', 'v1.9.0') === true, 'numeric not lexicographic comparison');
$check(isNewerReleaseVersion('v1.0.0', 'v1.0.1') === false, 'older candidate rejected');
$check(parseVersionTag('v1.2.3') === [1, 2, 3], 'tag parsed to segments');
$check(parseVersionTag('v1.2.3-beta') === null, 'non-stable suffix rejected');
$check(parseVersionTag('1.2') === [1, 2], 'v prefix optional');

$fixture = sys_get_temp_dir() . '/booking-version-test-' . getmypid();
if (!is_dir($fixture . '/app')) {
    mkdir($fixture . '/app', 0777, true);
}
file_put_contents($fixture . '/app/version.php', "<?php\ndefine('APP_VERSION', 'v9.9.9');\n");
$check(installedAppVersion($fixture) === 'v9.9.9', 'installed version read from fixture root (app/version.php)');

// Regression: pipeline writes app/version.php in the package root; the
// reader must resolve <installation root>/app/version.php, not a file
// next to index.php.
file_put_contents($fixture . '/version.php', "<?php\ndefine('APP_VERSION', 'v0.0.0-WRONG-PLACE');\n");
$check(installedAppVersion($fixture) === 'v9.9.9', 'root-level version.php is ignored, app/version.php wins');

$emptyFixture = $fixture . '/empty';
if (!is_dir($emptyFixture . '/app')) {
    mkdir($emptyFixture . '/app', 0777, true);
}
$check(installedAppVersion($emptyFixture) === '', 'missing app/version.php yields unknown');

if (installedAppVersion() !== '') {
    // Sanity: repo must not accidentally ship app/version.php pre-release.
    $check(false, 'repo has no APP_VERSION (expected pre-release)');
} else {
    $check(true, 'repo has no APP_VERSION (expected pre-release)');
}

if ($failures > 0) {
    echo "{$failures} test(s) FAILED\n";
    exit(1);
}
echo "All version tests passed\n";
