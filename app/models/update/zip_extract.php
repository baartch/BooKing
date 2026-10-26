<?php
/**
 * Release archive extraction.
 *
 * Uses PharData (works with just zlib — no ext/zip needed) after validating
 * every entry name. All names are checked before a single file is written,
 * so a malicious archive never leaves partial output behind.
 */

/**
 * True when the archive entry name is a safe relative POSIX-style path.
 */
function isSafeArchiveEntry(string $entry): bool
{
    $entry = str_replace('\\', '/', $entry);

    if ($entry === '' || str_starts_with($entry, '/')) {
        return false;
    }

    if (str_contains($entry, ':')) {
        // Windows drive letters and NTFS alternate data streams.
        return false;
    }

    foreach (explode('/', $entry) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }

    return true;
}

/**
 * List the raw entry names recorded in the zip central directory.
 * PharData silently skips unsafe names, so validation must read the
 * actual directory, not Phar's filtered view.
 *
 * @return string[] entry names in central-directory order
 */
function listZipEntryNames(string $zipPath): array
{
    $size = filesize($zipPath);
    if ($size === false || $size < 22) {
        throw new RuntimeException('Release archive is not a zip file');
    }

    $handle = fopen($zipPath, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Cannot read release archive');
    }

    try {
        // Locate the end-of-central-directory record (last 22 bytes + comment).
        $scanStart = max(0, $size - 22 - 65535);
        $scanLength = $size - $scanStart;
        fseek($handle, $scanStart);
        $tail = fread($handle, $scanLength);
        if ($tail === false) {
            throw new RuntimeException('Cannot read release archive');
        }

        $eocd = strrpos($tail, "PK\x05\x06");
        if ($eocd === false) {
            throw new RuntimeException('Release archive is not a zip file');
        }
        $eocd += $scanStart;
        fseek($handle, $eocd + 10);
        $countBytes = fread($handle, 2);
        $offsetBytes = fread($handle, 2);
        fseek($handle, $eocd + 16);
        $cdOffsetBytes = fread($handle, 4);
        if ($countBytes === false || $offsetBytes === false || $cdOffsetBytes === false) {
            throw new RuntimeException('Truncated zip directory');
        }

        $entryCount = unpack('v', $countBytes)[1];
        $centralOffset = unpack('V', $cdOffsetBytes)[1];

        $names = [];
        fseek($handle, $centralOffset);
        for ($i = 0; $i < $entryCount; $i++) {
            $fixed = fread($handle, 46);
            if ($fixed === false || strlen($fixed) < 46) {
                throw new RuntimeException('Truncated zip directory');
            }
            $signature = unpack('V', substr($fixed, 0, 4))[1];
            if ($signature !== 0x02014b50) {
                throw new RuntimeException('Corrupt zip directory entry');
            }
            $flags = unpack('v', substr($fixed, 8, 2))[1];
            if (($flags & 0x0001) !== 0) {
                throw new RuntimeException('Encrypted zip entries are not supported');
            }
            $nameLength = unpack('v', substr($fixed, 28, 2))[1];
            $extraLength = unpack('v', substr($fixed, 30, 2))[1];
            $commentLength = unpack('v', substr($fixed, 32, 2))[1];

            $name = fread($handle, $nameLength);
            if ($name === false || strlen($name) < $nameLength) {
                throw new RuntimeException('Truncated zip entry name');
            }
            $names[] = $name;

            fseek($handle, $extraLength + $commentLength, SEEK_CUR);
        }

        return $names;
    } finally {
        fclose($handle);
    }
}

/**
 * Extract a release ZIP into $destDir. Returns the sorted list of extracted
 * relative file paths. Throws RuntimeException on unsafe or unreadable
 * archives (nothing is written in that case).
 *
 * @return string[]
 */
function extractReleaseArchive(string $zipPath, string $destDir): array
{
    if (!is_file($zipPath)) {
        throw new RuntimeException('Downloaded release archive not found');
    }

    // Validation pass: every raw central-directory name must be safe.
    $entryNames = listZipEntryNames($zipPath);
    $fileNames = array_values(array_filter($entryNames, static fn (string $name): bool => !str_ends_with($name, '/')));
    if ($fileNames === []) {
        throw new RuntimeException('Release archive is empty');
    }
    foreach ($fileNames as $entryName) {
        if (!isSafeArchiveEntry($entryName)) {
            throw new RuntimeException('Unsafe archive entry: ' . $entryName);
        }
    }

    try {
        $archive = new PharData($zipPath);
    } catch (Throwable $error) {
        throw new RuntimeException('Release archive is unreadable: ' . $error->getMessage());
    }

    $entries = [];
    $iterator = new RecursiveIteratorIterator(
        $archive,
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo) {
            continue;
        }
        $entry = $fileInfo->getPathname();
        $prefix = 'phar://' . $zipPath . '/';
        if (!str_starts_with($entry, $prefix)) {
            throw new RuntimeException('Unexpected archive entry');
        }
        $relative = substr($entry, strlen($prefix));
        $entries[] = [$relative, $entry];
    }

    if (count($entries) !== count($fileNames)) {
        throw new RuntimeException('Archive entries changed during extraction');
    }

    if (!is_dir($destDir) && !mkdir($destDir, 0775, true)) {
        throw new RuntimeException('Cannot create extraction directory');
    }

    $extracted = [];
    foreach ($entries as [$relative, $pharPath]) {
        $destination = $destDir . '/' . $relative;
        $parent = dirname($destination);
        if (!is_dir($parent) && !mkdir($parent, 0775, true)) {
            throw new RuntimeException('Cannot create directory: ' . $relative);
        }
        if (!copy($pharPath, $destination)) {
            throw new RuntimeException('Cannot extract: ' . $relative);
        }
        $extracted[] = $relative;
    }

    sort($extracted);

    return $extracted;
}
