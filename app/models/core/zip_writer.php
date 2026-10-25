<?php
/**
 * Dependency-free zip archive builder.
 *
 * Writes a spec-compliant zip without ext/zip: the ODF mimetype entry is
 * always stored first and uncompressed, everything else is deflated via zlib.
 *
 * @param array<string, string> $entries map of archive path => file contents
 * @return string raw zip bytes
 */

function buildZipBytes(array $entries): string
{
    if (array_key_exists('mimetype', $entries)) {
        $mimetype = $entries['mimetype'];
        unset($entries['mimetype']);
        $entries = ['mimetype' => $mimetype] + $entries;
    }

    $local = '';
    $central = '';
    $offset = 0;
    $count = 0;

    foreach ($entries as $name => $data) {
        $isMimetype = $name === 'mimetype';
        $method = $isMimetype ? 0 : 8;
        $payload = $data;

        if (!$isMimetype) {
            $deflated = gzdeflate($data, 6);
            // Keep the stored variant when deflate fails or does not shrink.
            if ($deflated !== false && strlen($deflated) < strlen($data)) {
                $payload = $deflated;
            } else {
                $method = 0;
            }
        }

        $nameBytes = strlen($name);
        $crc = crc32($data);
        $compressedSize = strlen($payload);
        $uncompressedSize = strlen($data);

        // DOS date/time fixed at 1980-01-01 00:00.
        $local .= "PK\x03\x04"
            . pack('v', 20)      // version needed
            . pack('v', 0)       // flags
            . pack('v', $method)
            . pack('v', 0)       // time
            . pack('v', 0x21)    // date
            . pack('V', $crc)
            . pack('V', $compressedSize)
            . pack('V', $uncompressedSize)
            . pack('v', $nameBytes)
            . pack('v', 0)       // extra length
            . $name
            . $payload;

        $central .= "PK\x01\x02"
            . pack('v', 20)      // version made by
            . pack('v', 20)      // version needed
            . pack('v', 0)       // flags
            . pack('v', $method)
            . pack('v', 0)       // time
            . pack('v', 0x21)    // date
            . pack('V', $crc)
            . pack('V', $compressedSize)
            . pack('V', $uncompressedSize)
            . pack('v', $nameBytes)
            . pack('v', 0)       // extra length
            . pack('v', 0)       // comment length
            . pack('v', 0)       // disk number start
            . pack('v', 0)       // internal attributes
            . pack('V', 0)       // external attributes
            . pack('V', $offset)
            . $name;

        $offset += 30 + $nameBytes + $compressedSize;
        $count++;
    }

    return $local . $central . "PK\x05\x06"
        . pack('v', 0)            // disk number
        . pack('v', 0)            // disk with central dir
        . pack('v', $count)
        . pack('v', $count)
        . pack('V', strlen($central))
        . pack('V', strlen($local))
        . pack('v', 0);           // comment length
}
