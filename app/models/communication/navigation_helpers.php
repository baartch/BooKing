<?php

/**
 * Build the Communication controller base URL.
 */
function communicationBaseUrl(?string $basePath = null): string
{
    if ($basePath === null) {
        $basePath = defined('BASE_PATH') ? (string) BASE_PATH : '';
    }

    return rtrim($basePath, '/') . '/app/controllers/communication/index.php';
}

/**
 * Remove null/empty-string values while keeping numeric 0 and "0".
 *
 * @param array<string,mixed> $params
 * @return array<string,mixed>
 */
function communicationFilterQueryParams(array $params): array
{
    return array_filter(
        $params,
        static fn($value): bool => $value !== null && $value !== ''
    );
}

/**
 * Build communication URL query string from params.
 *
 * @param array<string,mixed> $params
 */
function buildCommunicationUrl(array $params, ?string $basePath = null): string
{
    $baseUrl = communicationBaseUrl($basePath);
    $filtered = communicationFilterQueryParams($params);

    if (!$filtered) {
        return $baseUrl;
    }

    return $baseUrl . '?' . http_build_query($filtered);
}

/**
 * Build params for email tab navigation.
 *
 * @return array<string,mixed>
 */
function buildEmailTabQuery(
    ?int $mailboxId = null,
    ?string $folder = null,
    ?string $sort = null,
    ?string $filter = null,
    ?int $page = null,
    ?int $messageId = null,
    ?int $compose = null,
    ?int $reply = null,
    ?int $forward = null,
    ?int $templateId = null,
    ?string $notice = null
): array {
    return communicationFilterQueryParams([
        'tab' => 'email',
        'mailbox_id' => $mailboxId,
        'folder' => $folder,
        'sort' => $sort,
        'filter' => $filter,
        'page' => $page,
        'message_id' => $messageId,
        'compose' => $compose,
        'reply' => $reply,
        'forward' => $forward,
        'template_id' => $templateId,
        'notice' => $notice,
    ]);
}

/**
 * Build params for contacts tab navigation.
 *
 * @return array<string,mixed>
 */
function buildContactsTabQuery(
    ?int $teamId = null,
    ?string $search = null,
    ?int $contactId = null,
    ?string $mode = null,
    ?string $notice = null
): array {
    return communicationFilterQueryParams([
        'tab' => 'contacts',
        'team_id' => $teamId,
        'q' => $search,
        'contact_id' => $contactId,
        'mode' => $mode,
        'notice' => $notice,
    ]);
}

/**
 * Build params for conversations tab navigation.
 *
 * @return array<string,mixed>
 */
function buildConversationsTabQuery(
    ?int $conversationId = null,
    ?string $notice = null
): array {
    return communicationFilterQueryParams([
        'tab' => 'conversations',
        'conversation_id' => $conversationId,
        'notice' => $notice,
    ]);
}
