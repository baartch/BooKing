<?php

// Render the actual view with its database-dependent helpers replaced by fixtures.
function fetchConversationsForUser(int $userId): array { return $GLOBALS['testConversations']; }
function fetchMailboxIdentityListForUser(int $userId): array { return []; }
function getEmailFolderOptions(): array { return []; }
function formatConversationSubject(?string $subject): string { return $subject ?: '(No subject)'; }
function renderCsrfField(): void {}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value): int { return strlen($value); }
    function mb_strtolower(string $value): string { return strtolower($value); }
}
defined('BASE_PATH') || define('BASE_PATH', '');

function renderConversationForTest(string $folder, bool $closed): string
{
    $GLOBALS['testConversations'] = [[
        'id' => 42,
        'subject' => 'Hello',
        'participant_key' => 'someone@example.com',
        'last_message_folder' => $folder,
        'last_activity_at' => date('Y-m-d H:i:s'),
        'message_count' => 1,
        'is_closed' => $closed,
    ]];
    $currentUser = ['user_id' => 1];
    $_GET = [];
    $view = file_get_contents(__DIR__ . '/../app/views/communication/conversations.php');
    $view = preg_replace('/^require_once .*;\R/m', '', $view);
    ob_start();
    eval('?>' . $view);
    return ob_get_clean();
}

$failures = 0;
foreach ([false => 'open', true => 'closed'] as $closed => $state) {
    foreach (['inbox' => ['fa-arrow-right', 'has-text-success'], 'sent' => ['fa-arrow-left', 'conversation-arrow-outgoing']] as $folder => [$arrow, $color]) {
        $html = renderConversationForTest($folder, (bool) $closed);
        $ok = (bool) preg_match('/<i\b[^>]*class="[^"]*\b' . $arrow . '\b[^"]*\b' . $color . '\b[^"]*"/', $html);
        echo ($ok ? '✓ ' : '✗ ') . "$state $folder arrow has its direction and color\n";
        $failures += $ok ? 0 : 1;
    }
}
exit($failures === 0 ? 0 : 1);
