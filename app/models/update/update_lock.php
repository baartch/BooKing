<?php
/**
 * Update lock: config/update.lock.
 *
 * Only one update may run at a time (flock, non-blocking). The file also
 * carries human-readable state for the Maintenance tab: running / failed /
 * done, target version, timestamps and the failing step.
 */

function updateLockPath(?string $baseDir = null): string
{
    $base = $baseDir !== null ? rtrim($baseDir, '/\\') : rtrim(dirname(__DIR__, 3), '/');

    return $base . '/config/update.lock';
}

/**
 * Read the last update lock state. Returns null when no update ever ran.
 */
function fetchUpdateLockState(?string $baseDir = null): ?array
{
    $path = updateLockPath($baseDir);
    if (!is_file($path)) {
        return null;
    }

    $state = json_decode((string) file_get_contents($path), true);

    return is_array($state) ? $state : null;
}

/**
 * True when an update currently holds the lock (probe via flock, NOT the
 * JSON state: a crashed update leaves state 'running' behind while the
 * OS has long released the lock — state alone must never block updates).
 */
function isUpdateRunning(?string $baseDir = null): bool
{
    $path = updateLockPath($baseDir);
    if (!is_file($path)) {
        return false;
    }

    $handle = @fopen($path, 'r');
    if ($handle === false) {
        return false;
    }

    $running = !flock($handle, LOCK_EX | LOCK_NB);
    if (!$running) {
        flock($handle, LOCK_UN);
    }
    fclose($handle);

    return $running;
}

/**
 * Try to claim the update lock. On success returns the lock handle to pass
 * to writeUpdateLockState()/releaseUpdateLock().
 *
 * @return array{ok: bool, handle: resource|null, error: string}
 */
function acquireUpdateLock(string $baseDir): array
{
    $path = updateLockPath($baseDir);
    if (!is_dir(dirname($path))) {
        return ['ok' => false, 'handle' => null, 'error' => 'Config directory missing'];
    }

    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        return ['ok' => false, 'handle' => null, 'error' => 'Cannot open update lock file'];
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        return ['ok' => false, 'handle' => null, 'error' => 'An update is already in progress'];
    }

    return ['ok' => true, 'handle' => $handle, 'error' => ''];
}

/**
 * Persist the lock state (inside the lock, so no atomicity concerns).
 */
function writeUpdateLockState($handle, array $state): void
{
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, (string) json_encode($state));
    fflush($handle);
}

/**
 * Release the update lock handle.
 */
function releaseUpdateLock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}
