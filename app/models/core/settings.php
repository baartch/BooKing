<?php
function loadSettingValues(array $keys): array
{
    $values = array_fill_keys($keys, '');
    if ($keys === []) {
        return $values;
    }

    try {
        $pdo = getDatabaseConnection();
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare(
            sprintf('SELECT setting_key, setting_value FROM settings WHERE setting_key IN (%s)', $placeholders)
        );
        $stmt->execute(array_values($keys));
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $key = (string) $row['setting_key'];
            if (array_key_exists($key, $values)) {
                $values[$key] = decryptSettingValue($row['setting_value'] ?? '');
            }
        }
    } catch (Throwable $error) {
        return $values;
    }

    return $values;
}

/**
 * Persist one setting value (encrypted upsert). Empty values clear the key.
 */
function saveSettingValue(string $key, string $value): bool
{
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value)
             VALUES (:setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => encryptSettingValue($value)
        ]);

        return true;
    } catch (Throwable $error) {
        error_log('Saving setting failed: ' . $error->getMessage());
        return false;
    }
}
