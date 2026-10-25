<?php
/**
 * Zip Writer Test Script
 *
 * Tests the dependency-free zip builder (no ext/zip on the server needed).
 * Run this from the command line: php tests/zip_writer_test.php
 * Exits with 0 if all tests pass, 1 if any test fails.
 */

require_once __DIR__ . '/../app/models/core/zip_writer.php';

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

function readPharEntry(string $zipPath, string $entryName): ?string
{
    $phar = new PharData($zipPath);
    $content = file_get_contents('phar://' . $zipPath . '/' . $entryName);
    unset($phar);
    return $content === false ? null : $content;
}

echo "Zip Writer Test\n";
echo "===============\n\n";

$bytes = buildZipBytes([
    'mimetype' => 'application/vnd.oasis.opendocument.text',
    'content.xml' => '<office:document-content>hello</office:document-content>',
    'META-INF/manifest.xml' => '<manifest/>',
]);

// Test 1: local file header signature
echo "Test 1: output starts with local file header signature...\n";
check(substr($bytes, 0, 4) === "PK\x03\x04", 'Starts with PK\x03\x04');

// Test 2: first entry is mimetype, stored uncompressed
echo "Test 2: mimetype is first and stored (method 0)...\n";
$nameLen = unpack('v', substr($bytes, 26, 2))['1'];
$firstName = substr($bytes, 30, $nameLen);
$firstMethod = unpack('v', substr($bytes, 8, 2))['1'];
$firstSize = unpack('V', substr($bytes, 18, 4))['1'];
$firstData = substr($bytes, 30 + $nameLen, $firstSize);
check($firstName === 'mimetype', 'First entry name is mimetype');
check($firstMethod === 0, 'mimetype is stored uncompressed');
check($firstData === 'application/vnd.oasis.opendocument.text', 'mimetype content intact');

// Test 3: mimetype gets moved to first position even when passed last
echo "Test 3: mimetype is force-sorted to the front...\n";
$bytes2 = buildZipBytes([
    'content.xml' => '<a/>',
    'mimetype' => 'x',
]);
$nameLen2 = unpack('v', substr($bytes2, 26, 2))['1'];
check(substr($bytes2, 30, $nameLen2) === 'mimetype', 'mimetype first regardless of input order');

// Test 4: non-mimetype entries are deflated
echo "Test 4: other entries use deflate (method 8)...\n";
$secondOffset = 30 + $nameLen + $firstSize;
$secondMethod = unpack('v', substr($bytes, $secondOffset + 8, 2))['1'];
check(substr($bytes, $secondOffset, 4) === "PK\x03\x04", 'Second local header found');
check($secondMethod === 8, 'content.xml is deflated');

// Test 5: archive readable by PharData with intact contents
echo "Test 5: PharData round-trip...\n";
$tmp = tempnam(sys_get_temp_dir(), 'zipw') . '.zip';
file_put_contents($tmp, $bytes);
$roundTrip = readPharEntry($tmp, 'content.xml');
$mimetypeRoundTrip = readPharEntry($tmp, 'mimetype');
$manifestRoundTrip = readPharEntry($tmp, 'META-INF/manifest.xml');
@unlink($tmp);
check($roundTrip === '<office:document-content>hello</office:document-content>', 'content.xml round-trips');
check($mimetypeRoundTrip === 'application/vnd.oasis.opendocument.text', 'mimetype round-trips');
check($manifestRoundTrip === '<manifest/>', 'META-INF/manifest.xml round-trips');

// Test 6: central directory + EOCD present (unzip compatibility)
echo "Test 6: central directory signature and EOCD present...\n";
$eocdPos = strrpos($bytes, "PK\x05\x06");
$centralPos = unpack('V', substr($bytes, $eocdPos + 16, 4))['1'];
$entryCount = unpack('v', substr($bytes, $eocdPos + 10, 2))['1'];
check($eocdPos !== false, 'EOCD found');
check(substr($bytes, $centralPos, 4) === "PK\x01\x02", 'Central directory starts at declared offset');
check($entryCount === 3, 'EOCD entry count is 3');

echo "\n";
if ($failures > 0) {
    echo $failures . " test(s) FAILED\n";
    exit(1);
}
echo "All tests passed\n";
exit(0);
