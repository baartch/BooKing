<?php
/**
 * Show Songs (Setlist) Model Test Script
 *
 * Tests the pure helpers of the show songs model (input normalization).
 * The database-backed fetch/replace functions are not covered here
 * (no local DB driver); they mirror the team shows model.
 * Run this from the command line: php tests/show_songs_test.php
 */

require_once __DIR__ . '/../app/models/team/show_songs.php';

$failures = 0;

function check(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        echo "✓ " . $label . "\n";
    } else {
        $failures++;
        echo "✗ " . $label . " (FAILED)\n";
    }
}

echo "Show Songs Model Test\n";
echo("=====================\n\n");

// normalizeShowSongInput: ordered IDs, empty entry filtered
$result = normalizeShowSongInput(['show_songs' => ['3', '7', '']]);
check(($result['song_ids'] ?? null) === [3, 7], 'empty string is filtered, order preserved');
check(empty($result['errors']), 'empty string filter produces no errors');

// non-numeric junk dropped
$result = normalizeShowSongInput(['show_songs' => ['3', 'abc', '7']]);
check(($result['song_ids'] ?? null) === [3, 7], 'non-numeric entry is dropped');
check(empty($result['errors']), 'non-numeric entry produces no error');

// duplicates rejected
$result = normalizeShowSongInput(['show_songs' => ['3', '3']]);
check(($result['song_ids'] ?? null) === [], 'duplicate IDs yield empty song_ids');
check(!empty($result['errors']), 'duplicate IDs produce an error');
check((bool) preg_match('/once/i', implode(' ', $result['errors'])), 'duplicate error mentions "once"');

// empty array is a valid empty setlist
$result = normalizeShowSongInput(['show_songs' => []]);
check(($result['song_ids'] ?? null) === [], 'empty array is an empty setlist');
check(empty($result['errors']), 'empty array produces no errors');

// missing key entirely
$result = normalizeShowSongInput([]);
check(($result['song_ids'] ?? null) === [], 'missing key is an empty setlist');
check(empty($result['errors']), 'missing key produces no errors');

// '0' is not a valid song id
$result = normalizeShowSongInput(['show_songs' => ['0']]);
check(($result['song_ids'] ?? null) === [], 'zero is filtered out');
check(empty($result['errors']), 'zero produces no errors');

// float-like junk dropped
$result = normalizeShowSongInput(['show_songs' => ['3.5']]);
check(($result['song_ids'] ?? null) === [], 'float-like junk is dropped');
check(empty($result['errors']), 'float-like junk produces no errors');

// numeric string with whitespace is still numeric
$result = normalizeShowSongInput(['show_songs' => [' 12 ']]);
check(($result['song_ids'] ?? null) === [12], 'whitespace-padded numeric string parses');
check(empty($result['errors']), 'whitespace-padded numeric string produces no errors');

// three entries, unique, order preserved end to end
$result = normalizeShowSongInput(['show_songs' => ['9', '2', '5']]);
check(($result['song_ids'] ?? null) === [9, 2, 5], 'unique IDs keep posted order');
check(empty($result['errors']), 'unique IDs produce no errors');

echo "\n";
if ($failures > 0) {
    echo $failures . " test(s) FAILED\n";
    exit(1);
}
echo "All tests passed\n";
