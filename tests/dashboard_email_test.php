<?php

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    if ($condition) {
        echo "✓ {$label}\n";
    } else {
        echo "✗ {$label}\n";
        $failures++;
    }
};

$helperPath = __DIR__ . '/../app/models/dashboard/email_helpers.php';
if (is_file($helperPath)) {
    require_once $helperPath;
}
require_once __DIR__ . '/../app/models/communication/mail_fetch_helpers.php';

$check(function_exists('fetchDashboardUnreadEmails'), 'dashboard unread email helper is available');

if (function_exists('fetchDashboardUnreadEmails')) {
    final class DashboardUnreadTestPDO extends PDO
    {
        public array $queries = [];
        public array $rows = [];

        public function __construct() {}

        public function prepare(string $query, array $options = []): PDOStatement|false
        {
            return new DashboardUnreadTestStatement($this, $query);
        }
    }

    final class DashboardUnreadTestStatement extends PDOStatement
    {
        public function __construct(private DashboardUnreadTestPDO $pdo, private string $query) {}

        public function execute(?array $params = null): bool
        {
            $this->pdo->queries[] = ['sql' => $this->query, 'params' => $params ?? []];
            return true;
        }

        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
        {
            return $this->pdo->rows;
        }
    }

    $emptyPdo = new DashboardUnreadTestPDO();
    $check(fetchDashboardUnreadEmails($emptyPdo, []) === [], 'returns no messages without querying when there are no accessible mailboxes');
    $check($emptyPdo->queries === [], 'does not prepare a query for an empty mailbox list');

    $pdo = new DashboardUnreadTestPDO();
    $pdo->rows = [
        ['id' => 42, 'mailbox_id' => 3, 'from_email' => 'info@example.com', 'subject' => 'Hello', 'received_at' => '2026-12-12 15:43:00', 'received_at_epoch' => 1797118980, 'created_at' => '2026-12-12 15:43:00', 'created_at_epoch' => 1797118980],
        ['id' => 43, 'mailbox_id' => 7, 'from_email' => 'team@example.com', 'subject' => 'Meeting', 'received_at' => null, 'received_at_epoch' => null, 'created_at' => '2026-12-11 09:00:00', 'created_at_epoch' => 1797008400],
        ['id' => 44, 'mailbox_id' => 9, 'from_email' => 'unicode@example.com', 'subject' => null, 'received_at' => null, 'received_at_epoch' => null, 'created_at' => '2026-12-10 09:00:00', 'created_at_epoch' => 1796922000],
    ];
    $mailboxes = [
        ['id' => 3, 'name' => 'private', 'display_name' => 'Private box', 'user_id' => 5, 'team_name' => null],
        ['id' => 7, 'name' => 'shared', 'display_name' => '', 'user_id' => null, 'team_name' => 'Brass'],
        ['id' => 9, 'name' => '', 'display_name' => '', 'user_id' => null, 'team_name' => ''],
    ];

    $messages = fetchDashboardUnreadEmails($pdo, $mailboxes);
    $query = $pdo->queries[0] ?? ['sql' => '', 'params' => []];
    $check(array_values($query['params']) === [3, 7, 9], 'binds only IDs from the accessible mailbox list');
    $check(str_contains($query['sql'], 'folder') && str_contains($query['sql'], "'inbox'") && str_contains($query['sql'], 'is_read = 0'), 'query includes only unread Inbox messages');
    $check(str_contains($query['sql'], 'COALESCE(received_at_epoch, UNIX_TIMESTAMP(received_at), UNIX_TIMESTAMP(created_at)) DESC, id DESC') && !preg_match('/\bLIMIT\b/i', $query['sql']), 'orders by preserved message instants newest first without limiting results');
    $check(str_contains($query['sql'], 'received_at_epoch') && str_contains($query['sql'], 'UNIX_TIMESTAMP(created_at) AS created_at_epoch'), 'selects the original receive instant and database-session-safe creation instant');
    $check(!preg_match('/\b(body|body_html|imap_password|smtp_password)\b/i', $query['sql']), 'selects summaries without message bodies or mailbox credentials');
    $check(($messages[0]['mailbox_label'] ?? '') === 'Personal · Private box' && ($messages[0]['mailbox_initial'] ?? '') === 'P', 'uses personal mailbox display name and initial');
    $check(($messages[1]['mailbox_label'] ?? '') === 'Brass · shared' && ($messages[1]['mailbox_initial'] ?? '') === 'S', 'uses team context and stored mailbox name when display name is empty');
    $check(($messages[2]['mailbox_label'] ?? '') === 'Team · Mailbox' && ($messages[2]['mailbox_initial'] ?? '') === 'M', 'uses safe fallback label and initial when mailbox names are empty');
    $check(($messages[2]['received_at'] ?? null) === null && ($messages[2]['created_at'] ?? '') === '2026-12-10 09:00:00', 'preserves received and created timestamps for browser formatting fallback');

    $unicodePdo = new DashboardUnreadTestPDO();
    $unicodePdo->rows = [
        ['id' => 45, 'mailbox_id' => 13, 'from_email' => 'unicode@example.com', 'subject' => 'Hello', 'received_at' => null, 'created_at' => '2026-12-10 09:00:00'],
    ];
    $unicodeMessages = fetchDashboardUnreadEmails($unicodePdo, [
        ['id' => 13, 'name' => 'Å-Team', 'display_name' => '', 'user_id' => null, 'team_name' => 'Music'],
    ]);
    $check(($unicodeMessages[0]['mailbox_initial'] ?? '') === 'Å', 'keeps a non-ASCII mailbox initial as a complete UTF-8 character');

    $check(function_exists('dashboardEmailTimestampToIso8601'), 'dashboard timestamp normalizer is available');
    if (function_exists('dashboardEmailTimestampToIso8601')) {
        $defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('America/Los_Angeles');
        $check(dashboardEmailTimestampToIso8601('2026-12-12 15:43:00') === '2026-12-12T15:43:00-08:00', 'adds the PHP source timezone offset when normalizing a stored naive timestamp');
        $check(dashboardEmailTimestampToIso8601('2026-11-01 01:30:00', 1793521800) === '2026-11-01T08:30:00+00:00', 'uses the source epoch for the first repeated DST-fold time');
        $check(dashboardEmailTimestampToIso8601('2026-11-01 01:30:00', 1793525400) === '2026-11-01T09:30:00+00:00', 'distinguishes the second repeated DST-fold time by its source epoch');
        $check(dashboardEmailTimestampToIso8601('2026-12-11 09:00:00', 1797008400) === '2026-12-11T17:00:00+00:00', 'uses the database timestamp epoch instead of the PHP timezone assumption');
        $check(dashboardEmailTimestampToIso8601('not-a-date') === null, 'rejects invalid stored timestamps');
        date_default_timezone_set($defaultTimezone);
    }

    $check(function_exists('normalizeFetchedEmailTimestamp'), 'mail ingestion timestamp normalizer is available');
    if (function_exists('normalizeFetchedEmailTimestamp')) {
        $defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('America/Los_Angeles');
        $firstFold = normalizeFetchedEmailTimestamp('Sun, 01 Nov 2026 01:30:00 -0700');
        $secondFold = normalizeFetchedEmailTimestamp('Sun, 01 Nov 2026 01:30:00 -0800');
        $check($firstFold === ['received_at' => '2026-11-01 01:30:00', 'received_at_epoch' => 1793521800], 'preserves the first original IMAP offset through a repeated local time');
        $check($secondFold === ['received_at' => '2026-11-01 01:30:00', 'received_at_epoch' => 1793525400], 'preserves the second original IMAP offset through a repeated local time');
        $check(normalizeFetchedEmailTimestamp('invalid') === ['received_at' => null, 'received_at_epoch' => null], 'does not create a bogus timestamp for an invalid mail date');
        date_default_timezone_set($defaultTimezone);
    }
}

if ($failures > 0) {
    echo "{$failures} dashboard email test(s) failed\n";
    exit(1);
}

echo "All dashboard email tests passed\n";
