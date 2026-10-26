<?php
/**
 * Application self-updater.
 *
 * Flow (each failure stops before or at its step, no rollback):
 *   lock → requirements → download → extract → verify (hashes, version,
 *   managed paths, writability) → migrations → replace files → delete
 *   stale files → done.
 *
 * Local-only files (config/config.php, installer lock, uploads/) are never
 * touched: only paths inside the release manifest are written, and stale
 * deletion is restricted to the previous manifest minus the new one.
 */

require_once __DIR__ . '/../core/version.php';
require_once __DIR__ . '/version_compare.php';
require_once __DIR__ . '/update_lock.php';
require_once __DIR__ . '/zip_extract.php';

/**
 * Paths a release may manage. Everything else is untouchable.
 */
function isManagedReleasePath(string $relative): bool
{
    $managedRoots = ['app/', 'assets/', 'sql/'];
    $managedFiles = [
        'index.php',
        'favicon.ico',
        '.htaccess',
        'manifest.json',
        'config/.htaccess',
        'config/config.example.php',
    ];

    foreach ($managedRoots as $root) {
        if (str_starts_with($relative, $root)) {
            return true;
        }
    }

    return in_array($relative, $managedFiles, true);
}

/**
 * Stale files: listed in the old manifest, managed, absent from the new one.
 *
 * @param string[] $oldFiles
 * @param string[] $newFiles
 * @return string[]
 */
function computeStaleFiles(array $oldFiles, array $newFiles): array
{
    $stale = [];
    foreach ($oldFiles as $file) {
        if (in_array($file, $newFiles, true)) {
            continue;
        }
        if (isManagedReleasePath($file)) {
            $stale[] = $file;
        }
    }

    return $stale;
}

/**
 * Environment requirements for self-updating.
 *
 * @return array<int, array{label: string, ok: bool}>
 */
function updateRequirements(?string $baseDir = null): array
{
    $base = $baseDir !== null ? rtrim($baseDir, '/\\') : rtrim(dirname(__DIR__, 3), '/');

    return [
        ['label' => 'staging_dir_writable', 'ok' => is_writable($base . '/uploads') || is_writable($base) || is_dir($base . '/uploads')],
        ['label' => 'config_writable', 'ok' => is_writable($base . '/config')],
        ['label' => 'zip_reader', 'ok' => zipArchiveAvailable() || class_exists('PharData')],
        [
            'label' => 'http_transport',
            'ok' => function_exists('curl_init') || (bool) ini_get('allow_url_fopen'),
        ],
    ];
}

/**
 * Recursive directory delete (no shell_exec — commonly disabled in
 * shared-hosting disable_functions).
 */
function removeDirectoryRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}

/**
 * Run the application update from a GitHub release ZIP.
 *
 * @param array{tag: string, zip_url: string} $target
 * @param callable(string): array{ok: bool, status: int, body: string, error: string} $httpFetch
 * @param callable(string): array|null $migrationRunner receives the extracted sql/migrations dir
 * @return array{ok: bool, error: string|null, target_version: string, installed_version: string}
 */
function runApplicationUpdate(array $target, callable $httpFetch, ?string $baseDir = null, ?callable $migrationRunner = null): array
{
    $base = $baseDir !== null ? rtrim($baseDir, '/\\') : rtrim(dirname(__DIR__, 3), '/');
    $tag = (string) ($target['tag'] ?? '');
    $zipUrl = (string) ($target['zip_url'] ?? '');

    $fail = static function (string $error) use ($tag): array {
        return ['ok' => false, 'error' => $error, 'target_version' => $tag, 'installed_version' => ''];
    };

    if ($tag === '' || $zipUrl === '') {
        return $fail('No target release available.');
    }
    if (!isNewerReleaseVersion($tag, installedAppVersion($base))) {
        return $fail('Target release is not newer than the installed version.');
    }

    $lock = acquireUpdateLock($base);
    if (!$lock['ok']) {
        return $fail($lock['error']);
    }
    $handle = $lock['handle'];

    $state = [
        'state' => 'running',
        'target_version' => $tag,
        'started_at' => gmdate('c'),
        'finished_at' => null,
        'step' => 'start',
        'error' => null,
    ];
    writeUpdateLockState($handle, $state);

    $stagingRoot = $base . '/uploads/update_staging';
    $stagingDir = null;
    $migrationsDir = null;

    try {
        // Staging area (web-accessible uploads/ → deny direct access,
        // re-verified on every run so a lost .htaccess never exposes it).
        if (!is_dir($stagingRoot) && !mkdir($stagingRoot, 0775, true)) {
            throw new RuntimeException('Cannot create staging directory.');
        }
        $htaccess = $stagingRoot . '/.htaccess';
        if (!is_file($htaccess) || (string) file_get_contents($htaccess) !== "Require all denied\n") {
            if (file_put_contents($htaccess, "Require all denied\n") === false) {
                throw new RuntimeException('Cannot protect the staging directory.');
            }
        }

        $stagingDir = $stagingRoot . '/' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
        if (!mkdir($stagingDir, 0775, true)) {
            throw new RuntimeException('Cannot create staging run directory.');
        }

        // Download.
        $state['step'] = 'download';
        writeUpdateLockState($handle, $state);
        $response = $httpFetch($zipUrl);
        if (!$response['ok'] || $response['body'] === '') {
            throw new RuntimeException('Download failed: ' . ($response['error'] !== '' ? $response['error'] : ('HTTP ' . $response['status'])));
        }
        $zipPath = $stagingDir . '/release.zip';
        if (file_put_contents($zipPath, $response['body']) === false) {
            throw new RuntimeException('Cannot store the downloaded archive.');
        }

        // Extract (validates every raw archive name first).
        $state['step'] = 'extract';
        writeUpdateLockState($handle, $state);
        $extractDir = $stagingDir . '/extracted';
        $extracted = extractReleaseArchive($zipPath, $extractDir);

        // Verify manifest + version.
        $state['step'] = 'verify';
        writeUpdateLockState($handle, $state);
        $manifestPath = $extractDir . '/manifest.json';
        if (!is_file($manifestPath)) {
            throw new RuntimeException('Release archive has no manifest.json.');
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest) || !is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('Release manifest is unreadable.');
        }
        if ((string) ($manifest['version'] ?? '') !== $tag) {
            throw new RuntimeException('Manifest version does not match the release tag.');
        }

        $versionSource = (string) file_get_contents($extractDir . '/app/version.php');
        if (!preg_match("/define\('APP_VERSION', '([^']+)'\)/", $versionSource, $match) || $match[1] !== $tag) {
            throw new RuntimeException('Release version stamp does not match the release tag.');
        }

        $manifestFiles = [];
        foreach ($manifest['files'] as $manifestPath => $expectedHash) {
            $manifestPath = (string) $manifestPath;
            $expectedHash = (string) $expectedHash;
            if (!isManagedReleasePath($manifestPath)) {
                throw new RuntimeException('Manifest manages an unmanaged path: ' . $manifestPath);
            }
            $extractedFile = $extractDir . '/' . $manifestPath;
            if (!is_file($extractedFile)) {
                throw new RuntimeException('Manifest file missing from archive: ' . $manifestPath);
            }
            if (hash('sha256', (string) file_get_contents($extractedFile)) !== $expectedHash) {
                throw new RuntimeException('Hash mismatch for ' . $manifestPath);
            }
            $manifestFiles[] = $manifestPath;
        }
        if ($manifestFiles === []) {
            throw new RuntimeException('Release manifest is empty.');
        }
        $expectedExtracted = array_merge($manifestFiles, ['manifest.json']);
        sort($expectedExtracted);
        $extractedSorted = $extracted;
        sort($extractedSorted);
        if ($extractedSorted !== $expectedExtracted) {
            throw new RuntimeException('Archive contains files outside the manifest.');
        }

        // Migrations run before file replacement: additive schema changes
        // are safe for old code, new code would not be safe with old schema.
        $state['step'] = 'migrate';
        writeUpdateLockState($handle, $state);
        $migrationsDir = $extractDir . '/sql/migrations';
        if ($migrationRunner === null) {
            require_once __DIR__ . '/migrations.php';
            $migrationRunner = static function (string $dir): array {
                return runPendingMigrations(getDatabaseConnection(), $dir);
            };
        }
        if (is_dir($migrationsDir)) {
            $migrationRunner($migrationsDir);
        }

        // Snapshot the old manifest BEFORE overwriting manifest.json.
        $oldManifestPath = $base . '/manifest.json';
        if (is_file($oldManifestPath)) {
            $oldManifest = json_decode((string) file_get_contents($oldManifestPath), true);
            $oldFiles = is_array($oldManifest) && is_array($oldManifest['files'] ?? null)
                ? array_map('strval', array_keys($oldManifest['files']))
                : [];
        } else {
            $oldFiles = [];
        }

        // Replace managed files. version.php and manifest.json go LAST:
        // a mid-copy failure must leave the old version in place so the
        // same release can be retried.
        $state['step'] = 'replace';
        writeUpdateLockState($handle, $state);
        $copyPlan = array_merge($manifestFiles, ['manifest.json']);
        $installLast = ['app/version.php', 'manifest.json'];
        $orderedPlan = array_values(array_filter(
            $copyPlan,
            static fn (string $relative): bool => !in_array($relative, $installLast, true)
        ));
        foreach ($installLast as $last) {
            if (in_array($last, $copyPlan, true)) {
                $orderedPlan[] = $last;
            }
        }
        $copyPlan = $orderedPlan;
        foreach ($copyPlan as $relative) {
            $destination = $base . '/' . $relative;
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0775, true)) {
                throw new RuntimeException('Cannot create directory for ' . $relative);
            }
            if (is_file($destination) && !is_writable($destination)) {
                throw new RuntimeException('Not writable: ' . $relative);
            }
            if (!is_writable($parent)) {
                throw new RuntimeException('Directory not writable: ' . dirname($relative));
            }
        }
        foreach ($copyPlan as $relative) {
            if (!copy($extractDir . '/' . $relative, $base . '/' . $relative)) {
                throw new RuntimeException('Cannot install ' . $relative);
            }
        }

        // Delete stale files recorded in the pre-update manifest.
        $state['step'] = 'cleanup';
        writeUpdateLockState($handle, $state);
        foreach (computeStaleFiles($oldFiles, $copyPlan) as $stale) {
            $stalePath = $base . '/' . $stale;
            if (is_file($stalePath) && !@unlink($stalePath)) {
                throw new RuntimeException('Cannot remove stale file: ' . $stale);
            }
        }

        $state['state'] = 'done';
        $state['step'] = null;
        $state['finished_at'] = gmdate('c');
        writeUpdateLockState($handle, $state);

        return [
            'ok' => true,
            'error' => null,
            'target_version' => $tag,
            'installed_version' => installedAppVersion($base),
        ];
    } catch (Throwable $error) {
        $state['state'] = 'failed';
        $state['error'] = $error->getMessage();
        $state['finished_at'] = gmdate('c');
        writeUpdateLockState($handle, $state);

        return [
            'ok' => false,
            'error' => $error->getMessage(),
            'target_version' => $tag,
            'installed_version' => installedAppVersion($base),
        ];
    } finally {
        if ($stagingDir !== null && is_dir($stagingDir)) {
            removeDirectoryRecursive($stagingDir);
        }
        releaseUpdateLock($handle);
    }
}
