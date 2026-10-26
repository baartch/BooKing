<?php
/**
 * Release version comparison helpers.
 *
 * Versions are stable release tags like "v1.2.3" (an optional "v" prefix,
 * dotted numeric segments). Anything else — prerelease suffixes, "latest",
 * empty strings — is not comparable and is rejected.
 */

/**
 * Parse a release tag into numeric segments. Returns null for non-stable tags.
 *
 * @return int[]|null
 */
function parseVersionTag(string $tag): ?array
{
    $normalized = $tag !== '' && $tag[0] === 'v' ? substr($tag, 1) : $tag;
    if ($normalized === '' || !preg_match('/^\d+(\.\d+)*$/', $normalized)) {
        return null;
    }

    return array_map('intval', explode('.', $normalized));
}

/**
 * True when $candidate is a stable version strictly newer than $installed.
 * An unknown installed version ('') accepts any stable candidate.
 */
function isNewerReleaseVersion(string $candidate, string $installed): bool
{
    $candidateSegments = parseVersionTag($candidate);
    if ($candidateSegments === null) {
        return false;
    }

    if ($installed === '') {
        return true;
    }

    $installedSegments = parseVersionTag($installed);
    if ($installedSegments === null) {
        return false;
    }

    $length = max(count($candidateSegments), count($installedSegments));
    for ($i = 0; $i < $length; $i++) {
        $candidatePart = $candidateSegments[$i] ?? 0;
        $installedPart = $installedSegments[$i] ?? 0;
        if ($candidatePart !== $installedPart) {
            return $candidatePart > $installedPart;
        }
    }

    return false;
}
