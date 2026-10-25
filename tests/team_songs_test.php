<?php
/**
 * Team Songs Model Test Script
 *
 * Tests the pure helpers of the team songs model (validation and duration
 * formatting). The database-backed fetch functions are not covered here
 * (no local DB driver); they mirror the team shows model.
 * Run this from the command line: php tests/team_songs_test.php
 */

require_once __DIR__ . '/../app/models/team/songs.php';
require_once __DIR__ . '/../app/models/core/form_helpers.php';
require_once __DIR__ . '/../app/models/communication/team_helpers.php';

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

echo "Team Songs Model Test\n";
echo "=====================\n\n";

// buildSongFormInput: valid full input
$result = buildSongFormInput([
    'name' => 'Blue Skies',
    'duration' => '3:45',
    'composer' => 'I. Berlin',
    'arranger' => 'A. Composer',
]);
check(empty($result['errors']), 'valid input has no errors');
check(($result['values']['name'] ?? null) === 'Blue Skies', 'valid input keeps name');
check(($result['values']['duration_seconds'] ?? null) === 225, '3:45 parses to 225 seconds');
check(($result['values']['composer'] ?? null) === 'I. Berlin', 'valid input keeps composer');
check(($result['values']['arranger'] ?? null) === 'A. Composer', 'valid input keeps arranger');

// buildSongFormInput: empty duration is optional
$result = buildSongFormInput([
    'name' => 'Song',
    'duration' => '',
    'composer' => 'C',
    'arranger' => 'A',
]);
check(empty($result['errors']), 'empty duration has no errors');
check(
    array_key_exists('duration_seconds', $result['values']) && $result['values']['duration_seconds'] === null,
    'empty duration parses to null'
);

// buildSongFormInput: trimming
$result = buildSongFormInput([
    'name' => '  Song  ',
    'duration' => ' 3:45 ',
    'composer' => ' C ',
    'arranger' => ' A ',
]);
check(empty($result['errors']) && $result['values']['name'] === 'Song', 'name is trimmed');
check(($result['values']['duration_seconds'] ?? null) === 225, 'duration input is trimmed before parsing');

// buildSongFormInput: required fields
$result = buildSongFormInput(['name' => '', 'duration' => '', 'composer' => 'C', 'arranger' => 'A']);
check(!empty($result['errors']), 'missing name is an error');
check((bool) preg_match('/name/i', implode(' ', $result['errors'])), 'missing name error mentions name');

$result = buildSongFormInput(['name' => 'S', 'duration' => '', 'composer' => '', 'arranger' => 'A']);
check(!empty($result['errors']), 'missing composer is an error');
check((bool) preg_match('/composer/i', implode(' ', $result['errors'])), 'missing composer error mentions composer');

$result = buildSongFormInput(['name' => 'S', 'duration' => '', 'composer' => 'C', 'arranger' => '']);
check(!empty($result['errors']), 'missing arranger is an error');
check((bool) preg_match('/arranger/i', implode(' ', $result['errors'])), 'missing arranger error mentions arranger');

// buildSongFormInput: invalid durations
$result = buildSongFormInput(['name' => 'S', 'duration' => '3:75', 'composer' => 'C', 'arranger' => 'A']);
check(!empty($result['errors']), '3:75 is rejected');

$result = buildSongFormInput(['name' => 'S', 'duration' => '345', 'composer' => 'C', 'arranger' => 'A']);
check(!empty($result['errors']), 'plain seconds "345" are rejected');

$result = buildSongFormInput(['name' => 'S', 'duration' => '3:45:12', 'composer' => 'C', 'arranger' => 'A']);
check(!empty($result['errors']), '3:45:12 is rejected');

$result = buildSongFormInput(['name' => 'S', 'duration' => '3:5', 'composer' => 'C', 'arranger' => 'A']);
check(!empty($result['errors']), '3:5 is rejected (seconds need two digits)');

// buildSongFormInput: length limit
$result = buildSongFormInput([
    'name' => str_repeat('x', 256),
    'duration' => '',
    'composer' => 'C',
    'arranger' => 'A',
]);
check(!empty($result['errors']), '256-character name is rejected');

// formatSongDuration
check(formatSongDuration(225) === '3:45', '225s formats to 3:45');
check(formatSongDuration(600) === '10:00', '600s formats to 10:00');
check(formatSongDuration(59) === '0:59', '59s formats to 0:59');
check(formatSongDuration(625) === '10:25', '625s formats to 10:25');
check(formatSongDuration(null) === '', 'null formats to empty string');

// buildSongFormInput: duration bound (max 1000 minutes)
$result = buildSongFormInput(['name' => 'S', 'duration' => '1000:00', 'composer' => 'C', 'arranger' => 'A']);
check(empty($result['errors']), '1000:00 is accepted');
check(($result['values']['duration_seconds'] ?? null) === 60000, '1000:00 parses to 60000 seconds');

$result = buildSongFormInput(['name' => 'S', 'duration' => '1001:00', 'composer' => 'C', 'arranger' => 'A']);
check(!empty($result['errors']), '1001:00 is rejected');

$result = buildSongFormInput(['name' => 'S', 'duration' => '99999:00', 'composer' => 'C', 'arranger' => 'A']);
check(!empty($result['errors']), 'five-digit minutes are rejected');

// setFormFlash / takeFormFlash
$_SESSION = [];
setFormFlash('test_key', ['name' => 'Flashed']);
check(takeFormFlash('test_key') === ['name' => 'Flashed'], 'takeFormFlash returns stored values');
check(takeFormFlash('test_key') === null, 'takeFormFlash clears after read');
check(takeFormFlash('missing_key') === null, 'takeFormFlash returns null when nothing stored');
setFormFlash('test_key', ['name' => 'A']);
setFormFlash('test_key', ['name' => 'B']);
check(takeFormFlash('test_key') === ['name' => 'B'], 'setFormFlash overwrites previous value');

// loadTeamTabList
$testErrors = [];
$loaded = loadTeamTabList(static function (): array {
    return ['a', 'b'];
}, 'songs', $testErrors, null);
check($loaded === ['a', 'b'], 'loadTeamTabList returns fetched rows on success');
check(empty($testErrors['songs']), 'loadTeamTabList records no error on success');

$loaded = loadTeamTabList(static function (): array {
    throw new RuntimeException('boom');
}, 'songs', $testErrors, null);
check($loaded === [], 'loadTeamTabList returns empty array on failure');
check(!empty($testErrors['songs']) && in_array('Failed to load songs.', $testErrors['songs'], true), 'loadTeamTabList records tab error on failure');

echo "\n";
if ($failures > 0) {
    echo $failures . " test(s) FAILED\n";
    exit(1);
}
echo "All tests passed\n";
