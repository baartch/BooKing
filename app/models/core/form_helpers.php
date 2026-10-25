<?php
/**
 * Stores form values in the session so they survive a redirect
 * (e.g. after a failed validation on a POST-redirect-GET form flow).
 */
function setFormFlash(string $key, array $values): void
{
    $_SESSION['form_flash'][$key] = $values;
}

/**
 * Returns and clears the flashed form values for the given key.
 */
function takeFormFlash(string $key): ?array
{
    if (!isset($_SESSION['form_flash'][$key]) || !is_array($_SESSION['form_flash'][$key])) {
        return null;
    }

    $values = $_SESSION['form_flash'][$key];
    unset($_SESSION['form_flash'][$key]);

    return $values;
}

function normalizeOptionalString(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : $value;
}

function normalizeOptionalNumber(string $value, string $fieldName, array &$errors, bool $integer = false): ?float
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        $errors[] = sprintf('%s must be a number.', $fieldName);
        return null;
    }

    return $integer ? (float) (int) $value : (float) $value;
}
