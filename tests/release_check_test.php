<?php
// Release check tests: GitHub response parsing + 24h check gating.
// Catches: wrong asset selection, prerelease/draft leakage, broken JSON,
// check throttling both ways.

require_once __DIR__ . '/../app/models/update/github_client.php';
require_once __DIR__ . '/../app/models/update/release_check.php';

$failures = 0;
$check = function (bool $condition, string $label) use (&$failures) {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

$validBody = json_encode([
    'tag_name' => 'v0.2.0',
    'html_url' => 'https://github.com/baartch/BooKing/releases/v0.2.0',
    'draft' => false,
    'prerelease' => false,
    'assets' => [
        ['name' => 'BooKing-v0.2.0.zip', 'browser_download_url' => 'https://example.com/BooKing-v0.2.0.zip'],
        ['name' => 'checksums.txt', 'browser_download_url' => 'https://example.com/checksums.txt'],
    ],
]);

$parsed = parseLatestReleaseResponse($validBody);
$check(is_array($parsed), 'valid release response parsed');
$check($parsed['tag'] === 'v0.2.0', 'tag extracted');
$check($parsed['zip_url'] === 'https://example.com/BooKing-v0.2.0.zip', 'matching zip asset selected');
$check($parsed['notes_url'] === 'https://github.com/baartch/BooKing/releases/v0.2.0', 'notes url extracted');

$wrongAssetBody = json_encode([
    'tag_name' => 'v0.2.0',
    'html_url' => 'https://github.com/baartch/BooKing/releases/v0.2.0',
    'draft' => false,
    'prerelease' => false,
    'assets' => [['name' => 'source.zip', 'browser_download_url' => 'https://example.com/source.zip']],
]);
$check(parseLatestReleaseResponse($wrongAssetBody) === null, 'release without matching asset rejected');

$prereleaseBody = json_encode([
    'tag_name' => 'v0.3.0-beta',
    'html_url' => 'https://github.com/baartch/BooKing/releases/v0.3.0-beta',
    'draft' => false,
    'prerelease' => true,
    'assets' => [['name' => 'BooKing-v0.3.0-beta.zip', 'browser_download_url' => 'https://example.com/b.zip']],
]);
$check(parseLatestReleaseResponse($prereleaseBody) === null, 'prerelease rejected');

$draftBody = json_encode([
    'tag_name' => 'v0.3.0',
    'html_url' => 'https://github.com/baartch/BooKing/releases/v0.3.0',
    'draft' => true,
    'prerelease' => false,
    'assets' => [['name' => 'BooKing-v0.3.0.zip', 'browser_download_url' => 'https://example.com/b.zip']],
]);
$check(parseLatestReleaseResponse($draftBody) === null, 'draft rejected');

$check(parseLatestReleaseResponse('not json at all') === null, 'broken JSON rejected');
$check(parseLatestReleaseResponse(json_encode(['message' => 'rate limited'])) === null, 'error body rejected');

$now = '2026-10-26T12:00:00+00:00';
$check(releaseCheckDue(['last_check_at' => '2026-10-26T11:00:00+00:00'], $now) === false, 'check 1h ago skipped');
$check(releaseCheckDue(['last_check_at' => '2026-10-25T11:00:00+00:00'], $now) === true, 'check 25h ago due');
$check(releaseCheckDue(['last_check_at' => ''], $now) === true, 'never checked before is due');
$check(releaseCheckDue([], $now) === true, 'empty state due');
$check(releaseCheckDue(['last_check_at' => 'garbage'], $now) === true, 'unparseable last check is due');

if ($failures > 0) {
    echo "{$failures} test(s) FAILED\n";
    exit(1);
}
echo "All release check tests passed\n";
