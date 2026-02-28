<?php

/**
 * Add a user-facing error message to a list.
 *
 * @param array<int,string> $errors
 */
function addErrorMessage(array &$errors, string $message): void
{
    $errors[] = $message;
}

/**
 * Add a user-facing error message and write a log entry.
 *
 * @param array<int,string> $errors
 */
function addErrorAndLog(
    array &$errors,
    string $userMessage,
    ?int $userId,
    string $logActionKey,
    Throwable $error
): void {
    $errors[] = $userMessage;
    logAction($userId, $logActionKey, $error->getMessage());
}

/**
 * Write a standardized exception log entry.
 */
function logThrowable(?int $userId, string $logActionKey, Throwable $error): void
{
    logAction($userId, $logActionKey, $error->getMessage());
}
