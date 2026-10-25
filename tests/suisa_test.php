<?php
/**
 * SUISA Export Model Test Script
 *
 * Tests the ODT generation for the SUISA live programme form:
 * pure formatting helpers + full document generation against the
 * tokenized template in assets/suisa_template/.
 * Run this from the command line: php tests/suisa_test.php
 * Exits with 0 if all tests pass, 1 if any test fails.
 */

require_once __DIR__ . '/../app/models/team/suisa.php';

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

function extractContentXml(string $odtBytes): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'suisa') . '.zip';
    file_put_contents($tmp, $odtBytes);
    $phar = new PharData($tmp);
    $content = file_get_contents('phar://' . $tmp . '/content.xml');
    unset($phar);
    @unlink($tmp);
    return (string) $content;
}

echo "SUISA Export Model Test\n";
echo "=======================\n\n";

// --- Formatting helpers -------------------------------------------------

echo "Test 1: duration formatting...\n";
check(formatSuisaDuration(null) === '', 'null duration is empty');
check(formatSuisaDuration(0) === '0:00', '0 seconds is 0:00');
check(formatSuisaDuration(215) === '3:35', '215 seconds is 3:35');
check(formatSuisaDuration(-10) === '', 'negative duration is empty');

echo "Test 2: date formatting...\n";
check(formatSuisaDate('2026-10-21') === '21.10.2026', 'ISO date becomes dd.mm.yyyy');
check(formatSuisaDate('not-a-date') === '', 'invalid date is empty');

echo "Test 3: city line formatting...\n";
check(buildSuisaCityLine(['country' => 'DE', 'postal_code' => '79104', 'city' => 'Freiburg']) === 'DE-79104 Freiburg', 'country prefix + postal + city');
check(buildSuisaCityLine(['postal_code' => '79104', 'city' => 'Freiburg']) === '79104 Freiburg', 'without country');
check(buildSuisaCityLine(['country' => 'CH', 'city' => 'Zürich']) === 'CH Zürich', 'country + city without postal code');
check(buildSuisaCityLine([]) === '', 'empty venue parts are empty');

echo "Test 4: download filename...\n";
check(suisaDownloadFilename(['name' => 'Hafenkonzert & Sause', 'show_date' => '2026-10-21']) === '2026.10.21_SUISA_Hafenkonzert_Sause.odt', 'date-first filename, unsafe characters replaced');
check(suisaDownloadFilename(['name' => 'Hafenkonzert', 'show_date' => 'not-a-date']) === 'SUISA_Hafenkonzert.odt', 'no date prefix on invalid date');
check(suisaDownloadFilename(['name' => '', 'show_date' => '2026-10-21']) === '2026.10.21_SUISA_Show.odt', 'fallback name when show name is empty');

// --- Document generation ------------------------------------------------

$venue = [
    'name' => 'Kulturhaus Beispiel GmbH',
    'address' => 'Fallenbrunnen 17',
    'postal_code' => '79104',
    'city' => 'Freiburg',
    'country' => 'DE',
    'capacity' => 120,
    'type' => 'Kulturlokal',
];
$show = ['name' => 'Hafenkonzert', 'show_date' => '2026-10-21', 'team_name' => 'In Volo'];
$songs = [
    ['name' => 'Trotzdem arrogant', 'composer' => 'Max Muster', 'arranger' => 'Lisa Bearb', 'duration_seconds' => 215],
    ['name' => 'Black & <Wild>', 'composer' => 'A & B', 'arranger' => '', 'duration_seconds' => null],
];

echo "Test 5: full document generation...\n";
$odt = buildSuisaOdt($show, $venue, $songs);
check(strlen($odt) > 10000, 'ODT has content');
check(substr($odt, 0, 2) === 'PK', 'ODT is a zip');

$content = extractContentXml($odt);
check(strpos($content, '{{') === false, 'no tokens remain');
check(strpos($content, 'Kulturhaus Beispiel GmbH') !== false, 'venue name filled');
check(strpos($content, 'Fallenbrunnen 17') !== false, 'street filled');
check(strpos($content, 'DE-79104 Freiburg') !== false, 'city line filled');
check(strpos($content, '>120<') !== false, 'capacity filled');
check(strpos($content, '21.10.2026') !== false, 'show date filled');
check(strpos($content, 'Kulturlokal') !== false, 'venue type (Ort) filled');
check(strpos($content, 'In Volo') !== false, 'team name filled (Interpret)');
check(strpos($content, 'Liedermacher') !== false, 'static genre filled');

echo "Test 6: song rows...\n";
check(strpos($content, '1.<text:tab/></text:span>Trotzdem arrogant') !== false, 'song 1 title row');
check(strpos($content, '2.<text:tab/></text:span>Black &amp; &lt;Wild&gt;') !== false, 'song 2 title row, XML-escaped');
check(strpos($content, 'Max Muster') !== false, 'composer filled');
check(strpos($content, 'A &amp; B') !== false, 'composer XML-escaped');
check(strpos($content, 'Lisa Bearb') !== false, 'arranger filled');
check(substr_count($content, '<table:table-row table:style-name="Table3.1"') === 4, '2 header + 2 song rows');
check(preg_match('/>3:35</', $content) === 1, 'duration formatted');
check(preg_match_all('/\d+\.<text:tab\/>/', $content) === 2, 'exactly two numbered song rows');

echo "Test 7: missing venue generates empty form fields...\n";
$contentNoVenue = extractContentXml(buildSuisaOdt($show, null, $songs));
check(strpos($contentNoVenue, '{{') === false, 'no tokens remain without venue');
check(strpos($contentNoVenue, '21.10.2026') !== false, 'date still filled');

echo "Test 8: empty setlist generates no song rows...\n";
$contentNoSongs = extractContentXml(buildSuisaOdt($show, $venue, []));
check(strpos($contentNoSongs, '{{') === false, 'no tokens remain without songs');
check(preg_match_all('/\d+\.<text:tab\/>/', $contentNoSongs) === 0, 'no numbered song rows');

echo "Test 9: missing team name leaves Interpret empty...\n";
$contentNoTeam = extractContentXml(buildSuisaOdt(['show_date' => '2026-10-21'], $venue, []));
check(strpos($contentNoTeam, '{{') === false, 'no tokens remain without team name');
check(strpos($contentNoTeam, 'In Volo') === false, 'no team name leaked');

echo "\n";
if ($failures > 0) {
    echo $failures . " test(s) FAILED\n";
    exit(1);
}
echo "All tests passed\n";
exit(0);
