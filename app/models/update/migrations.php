<?php
/**
 * Ordered database migrations.
 *
 * Migrations are plain SQL files in sql/migrations/, applied in name order.
 * Applied migrations are recorded in schema_migrations.
 *
 * - Fresh installs: sql/schema.sql already contains every shipped migration's
 *   effect plus the schema_migrations rows, so nothing is pending.
 * - Existing installs (pre-updater): the updater creates schema_migrations
 *   on first run and baselines it — every shipped migration is recorded as
 *   applied without running, because the schema already reflects them.
 */

require_once __DIR__ . '/../install/install_helpers.php';

const SCHEMA_MIGRATIONS_TABLE_SQL = 'CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

/**
 * Migration names shipped in a release, in application order.
 *
 * @return string[]
 */
function shippedMigrationNames(string $migrationsDir): array
{
    if (!is_dir($migrationsDir)) {
        return [];
    }

    $names = [];
    foreach (scandir($migrationsDir) ?: [] as $name) {
        if (str_ends_with($name, '.sql')) {
            $names[] = $name;
        }
    }
    sort($names);

    return $names;
}

/**
 * Migration names not yet applied, in application order.
 *
 * @param string[] $shippedNames
 * @param string[] $appliedNames
 * @return string[]
 */
function computePendingMigrations(array $shippedNames, array $appliedNames): array
{
    $pending = [];
    foreach ($shippedNames as $name) {
        if (!in_array($name, $appliedNames, true)) {
            $pending[] = $name;
        }
    }

    return $pending;
}

/**
 * Apply migrations in order. $exec runs one SQL statement and may throw;
 * $record persists a migration name as applied. A failure stops the run
 * with the failing migration's name; earlier migrations stay applied.
 *
 * @param array<string, string[]> $todo migration name => statements
 * @return string[] applied migration names
 */
function applyMigrationsToPdo(array $todo, callable $exec, callable $record): array
{
    $applied = [];
    foreach ($todo as $name => $statements) {
        try {
            foreach ($statements as $statement) {
                $exec($statement);
            }
        } catch (Throwable $error) {
            throw new RuntimeException(
                sprintf('Migration %s failed: %s', $name, $error->getMessage()),
                0
            );
        }
        $record($name);
        $applied[] = $name;
    }

    return $applied;
}

/**
 * Ensure the schema_migrations table exists and apply pending migrations
 * from $migrationsDir. Existing installs are baselined: when the table is
 * created here, the schema already contains every shipped migration's
 * effect, so they are recorded as applied without running.
 *
 * @return string[] applied migration names
 */
function runPendingMigrations(PDO $pdo, string $migrationsDir): array
{
    $tableExisted = (bool) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'"
    )->fetchColumn();

    $pdo->exec(SCHEMA_MIGRATIONS_TABLE_SQL);

    $shipped = shippedMigrationNames($migrationsDir);

    if (!$tableExisted) {
        // Baseline an existing installation: everything shipped is present.
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO schema_migrations (name) VALUES (:name)'
        );
        foreach ($shipped as $name) {
            $insert->execute([':name' => $name]);
        }

        return [];
    }

    $appliedNames = [];
    foreach ($pdo->query('SELECT name FROM schema_migrations')->fetchAll() as $row) {
        $appliedNames[] = (string) $row['name'];
    }

    $pending = computePendingMigrations($shipped, $appliedNames);
    $todo = [];
    foreach ($pending as $name) {
        $sql = (string) file_get_contents($migrationsDir . '/' . $name);
        $todo[$name] = splitSqlStatements($sql);
    }

    $record = static function (string $name) use ($pdo): void {
        $insert = $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (:name)');
        $insert->execute([':name' => $name]);
    };

    return applyMigrationsToPdo($todo, static function (string $statement) use ($pdo): void {
        $pdo->exec($statement);
    }, $record);
}
