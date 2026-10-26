<?php
/**
 * Installed app version.
 *
 * The release pipeline stamps each package with a generated version.php at
 * the application root. Installs that predate the updater have no such
 * file and report an unknown version ('').
 *
 * The file is parsed, never included: an include would define APP_VERSION
 * once per PHP process and go stale after an in-place update.
 */

/**
 * The installed app version, or '' when unknown (pre-updater install).
 */
function installedAppVersion(?string $baseDir = null): string
{
    $base = $baseDir !== null ? rtrim($baseDir, '/\\') : rtrim(dirname(__DIR__, 2), '/');
    $versionFile = $base . '/version.php';

    if (!is_file($versionFile)) {
        return '';
    }

    $source = (string) file_get_contents($versionFile);
    if (!preg_match("/define\('APP_VERSION', '([^']+)'\)/", $source, $match)) {
        return '';
    }

    return $match[1];
}
