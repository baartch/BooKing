<?php
require_once __DIR__ . '/database.php';

class LinkScopeException extends RuntimeException
{
    /** @var string */
    private $errorCode;
    /** @var int */
    private $httpStatus;
    /** @var array<string,mixed> */
    private $context;

    /**
     * @param array<string,mixed> $context
     */
    public function __construct(string $errorCode, string $message, int $httpStatus = 422, array $context = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

/**
 * @return array<int,string>
 */
function getAllowedLinkTypes(): array
{
    return ['email', 'contact', 'task', 'venue'];
}

function normalizeLinkType(string $type): string
{
    return strtolower(trim($type));
}

function assertAllowedLinkType(string $type, string $fieldName = 'type'): string
{
    $normalized = normalizeLinkType($type);
    if ($normalized === '' || !in_array($normalized, getAllowedLinkTypes(), true)) {
        throwLinkScopeException(
            null,
            'invalid_link_type',
            sprintf('Invalid %s "%s".', $fieldName, $type),
            [
                'action' => 'validate_link_type',
                'field' => $fieldName,
                'provided_type' => $type,
                'normalized_type' => $normalized,
            ],
            422
        );
    }

    return $normalized;
}

/**
 * @param array<int,mixed> $links
 * @return array<int,array{type:string,id:int}>
 */
function normalizeLinkItems(array $links): array
{
    $normalized = [];
    $seen = [];

    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $type = normalizeLinkType((string) ($link['type'] ?? ''));
        $id = (int) ($link['id'] ?? 0);

        if ($type === '' || $id <= 0) {
            continue;
        }

        if (!in_array($type, getAllowedLinkTypes(), true)) {
            continue;
        }

        $key = $type . ':' . $id;
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $normalized[] = [
            'type' => $type,
            'id' => $id,
        ];
    }

    usort($normalized, static function (array $left, array $right): int {
        $leftType = (string) ($left['type'] ?? '');
        $rightType = (string) ($right['type'] ?? '');
        $typeComparison = strcmp($leftType, $rightType);
        if ($typeComparison !== 0) {
            return $typeComparison;
        }

        $leftId = (int) ($left['id'] ?? 0);
        $rightId = (int) ($right['id'] ?? 0);
        return $leftId <=> $rightId;
    });

    return $normalized;
}

/**
 * Resolve scope for link operations from source object.
 *
 * @param array<string,mixed> $context
 * @return array{source_type:string,source_id:int,team_id:int|null,user_id:int|null,mailbox_id:int|null}
 */
function resolveLinkSourceScopeOrThrow(PDO $pdo, string $sourceType, int $sourceId, int $requestUserId, array $context = []): array
{
    $normalizedSourceType = assertAllowedLinkType($sourceType, 'source_type');
    if ($sourceId <= 0) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'invalid_source_id',
            'Source id must be > 0.',
            [
                'action' => 'resolve_link_scope',
                'source_type' => $normalizedSourceType,
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
            ],
            422
        );
    }

    switch ($normalizedSourceType) {
        case 'email':
            return resolveEmailLinkScopeOrThrow($pdo, $sourceId, $requestUserId, $context);
        case 'contact':
            return resolveContactLinkScopeOrThrow($pdo, $sourceId, $requestUserId);
        case 'task':
            return resolveTaskLinkScopeOrThrow($pdo, $sourceId, $requestUserId);
        case 'venue':
            return resolveVenueLinkScopeOrThrow($pdo, $sourceId, $requestUserId, $context);
    }

    throwLinkScopeException(
        $requestUserId > 0 ? $requestUserId : null,
        'unsupported_source_type',
        sprintf('Unsupported source type "%s".', $normalizedSourceType),
        [
            'action' => 'resolve_link_scope',
            'source_type' => $normalizedSourceType,
            'source_id' => $sourceId,
            'request_user_id' => $requestUserId,
        ],
        422
    );
}

/**
 * @param array<string,mixed> $context
 * @return array{source_type:string,source_id:int,team_id:int|null,user_id:int|null,mailbox_id:int|null}
 */
function resolveEmailLinkScopeOrThrow(PDO $pdo, int $sourceId, int $requestUserId, array $context = []): array
{
    $messageStmt = $pdo->prepare(
        'SELECT id, mailbox_id, team_id, user_id
         FROM email_messages
         WHERE id = :id
         LIMIT 1'
    );
    $messageStmt->execute([':id' => $sourceId]);
    $message = $messageStmt->fetch();

    if (!$message) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'source_not_found',
            sprintf('Email source %d not found.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'email',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
            ],
            404
        );
    }

    $mailboxId = (int) ($message['mailbox_id'] ?? 0);
    $requestedMailboxId = isset($context['mailbox_id']) ? (int) $context['mailbox_id'] : 0;

    if ($requestedMailboxId > 0 && $requestedMailboxId !== $mailboxId) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'mailbox_mismatch',
            sprintf('Email %d does not belong to mailbox %d.', $sourceId, $requestedMailboxId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'email',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
                'requested_mailbox_id' => $requestedMailboxId,
                'resolved_mailbox_id' => $mailboxId,
            ],
            422
        );
    }

    $mailbox = fetchAccessibleMailboxScope($pdo, $mailboxId, $requestUserId);
    if (!$mailbox) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'mailbox_access_denied',
            sprintf('Mailbox access denied for email source %d.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'email',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
                'mailbox_id' => $mailboxId,
            ],
            403
        );
    }

    $resolvedUserId = !empty($message['user_id'])
        ? (int) $message['user_id']
        : (!empty($mailbox['user_id']) ? (int) $mailbox['user_id'] : null);
    $resolvedTeamId = !empty($message['team_id'])
        ? (int) $message['team_id']
        : (!empty($mailbox['team_id']) ? (int) $mailbox['team_id'] : null);

    if ($resolvedTeamId === null && $resolvedUserId === null) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'scope_unresolved',
            sprintf('Unable to resolve scope for email source %d.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'email',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
                'mailbox_id' => $mailboxId,
            ],
            422
        );
    }

    return [
        'source_type' => 'email',
        'source_id' => $sourceId,
        'team_id' => $resolvedTeamId,
        'user_id' => $resolvedUserId,
        'mailbox_id' => $mailboxId,
    ];
}

/**
 * @return array{source_type:string,source_id:int,team_id:int|null,user_id:int|null,mailbox_id:int|null}
 */
function resolveContactLinkScopeOrThrow(PDO $pdo, int $sourceId, int $requestUserId): array
{
    $contactStmt = $pdo->prepare(
        'SELECT id, team_id
         FROM contacts
         WHERE id = :id
         LIMIT 1'
    );
    $contactStmt->execute([':id' => $sourceId]);
    $contact = $contactStmt->fetch();

    if (!$contact) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'source_not_found',
            sprintf('Contact source %d not found.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'contact',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
            ],
            404
        );
    }

    $teamId = !empty($contact['team_id']) ? (int) $contact['team_id'] : null;
    if ($teamId === null) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'scope_unresolved',
            sprintf('Unable to resolve scope for contact source %d.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'contact',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
            ],
            422
        );
    }

    if (!hasTeamAccess($pdo, $requestUserId, $teamId)) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'source_access_denied',
            sprintf('Contact access denied for source %d.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'contact',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
                'resolved_team_id' => $teamId,
            ],
            403
        );
    }

    return [
        'source_type' => 'contact',
        'source_id' => $sourceId,
        'team_id' => $teamId,
        'user_id' => null,
        'mailbox_id' => null,
    ];
}

/**
 * @return array{source_type:string,source_id:int,team_id:int|null,user_id:int|null,mailbox_id:int|null}
 */
function resolveTaskLinkScopeOrThrow(PDO $pdo, int $sourceId, int $requestUserId): array
{
    $taskStmt = $pdo->prepare(
        'SELECT id, team_id
         FROM team_tasks
         WHERE id = :id
         LIMIT 1'
    );
    $taskStmt->execute([':id' => $sourceId]);
    $task = $taskStmt->fetch();

    if (!$task) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'source_not_found',
            sprintf('Task source %d not found.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'task',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
            ],
            404
        );
    }

    $teamId = !empty($task['team_id']) ? (int) $task['team_id'] : null;
    if ($teamId === null) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'scope_unresolved',
            sprintf('Unable to resolve scope for task source %d.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'task',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
            ],
            422
        );
    }

    if (!hasTeamAccess($pdo, $requestUserId, $teamId)) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'source_access_denied',
            sprintf('Task access denied for source %d.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'task',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
                'resolved_team_id' => $teamId,
            ],
            403
        );
    }

    return [
        'source_type' => 'task',
        'source_id' => $sourceId,
        'team_id' => $teamId,
        'user_id' => null,
        'mailbox_id' => null,
    ];
}

/**
 * @param array<string,mixed> $context
 * @return array{source_type:string,source_id:int,team_id:int|null,user_id:int|null,mailbox_id:int|null}
 */
function resolveVenueLinkScopeOrThrow(PDO $pdo, int $sourceId, int $requestUserId, array $context = []): array
{
    $venueStmt = $pdo->prepare(
        'SELECT id
         FROM venues
         WHERE id = :id
         LIMIT 1'
    );
    $venueStmt->execute([':id' => $sourceId]);
    $venue = $venueStmt->fetch();

    if (!$venue) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'source_not_found',
            sprintf('Venue source %d not found.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'venue',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
            ],
            404
        );
    }

    $teamId = isset($context['team_id']) ? (int) $context['team_id'] : null;
    if ($teamId !== null && $teamId <= 0) {
        $teamId = null;
    }

    $userId = isset($context['user_id']) ? (int) $context['user_id'] : null;
    if ($userId !== null && $userId <= 0) {
        $userId = null;
    }

    if ($userId !== null && $userId !== $requestUserId) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'source_access_denied',
            sprintf('Venue scope user mismatch for source %d.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'venue',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
                'resolved_user_id' => $userId,
            ],
            403
        );
    }

    if ($teamId !== null && !hasTeamAccess($pdo, $requestUserId, $teamId)) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'source_access_denied',
            sprintf('Venue team access denied for source %d.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'venue',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
                'resolved_team_id' => $teamId,
            ],
            403
        );
    }

    if ($teamId === null && $userId === null) {
        throwLinkScopeException(
            $requestUserId > 0 ? $requestUserId : null,
            'scope_unresolved',
            sprintf('Unable to resolve scope for venue source %d.', $sourceId),
            [
                'action' => 'resolve_link_scope',
                'source_type' => 'venue',
                'source_id' => $sourceId,
                'request_user_id' => $requestUserId,
            ],
            422
        );
    }

    return [
        'source_type' => 'venue',
        'source_id' => $sourceId,
        'team_id' => $teamId,
        'user_id' => $userId,
        'mailbox_id' => null,
    ];
}

/**
 * @param array<string,mixed> $context
 */
function throwLinkScopeException(?int $userId, string $errorCode, string $message, array $context = [], int $httpStatus = 422): void
{
    $payload = array_merge([
        'error_code' => $errorCode,
        'message' => $message,
    ], $context);

    $jsonPayload = json_encode($payload);
    if ($jsonPayload === false) {
        $jsonPayload = $message;
    }

    logAction($userId, 'link_scope_error', $jsonPayload);
    throw new LinkScopeException($errorCode, $message, $httpStatus, $payload);
}

/**
 * @return array<string,mixed>|null
 */
function fetchAccessibleMailboxScope(PDO $pdo, int $mailboxId, int $userId): ?array
{
    if ($mailboxId <= 0 || $userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT m.id, m.team_id, m.user_id
         FROM mailboxes m
         LEFT JOIN team_members tm ON tm.team_id = m.team_id AND tm.user_id = :user_id
         WHERE m.id = :mailbox_id
           AND (m.user_id = :user_id_direct OR tm.user_id = :user_id_team)
         LIMIT 1'
    );
    $stmt->execute([
        ':mailbox_id' => $mailboxId,
        ':user_id' => $userId,
        ':user_id_direct' => $userId,
        ':user_id_team' => $userId,
    ]);

    $mailbox = $stmt->fetch();
    return $mailbox ?: null;
}

function hasTeamAccess(PDO $pdo, int $userId, int $teamId): bool
{
    if ($userId <= 0 || $teamId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM team_members
         WHERE user_id = :user_id AND team_id = :team_id
         LIMIT 1'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':team_id' => $teamId,
    ]);

    return (bool) $stmt->fetchColumn();
}
