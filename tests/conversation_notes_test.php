<?php
require_once __DIR__ . '/../app/models/communication/conversation_notes.php';

$emails = [
    ['id' => 1, 'folder' => 'inbox', 'received_at' => '2026-11-01 10:00:00', 'created_at' => '2026-11-01 10:00:00'],
    ['id' => 2, 'folder' => 'sent', 'sent_at' => '2026-11-01 12:00:00', 'created_at' => '2026-11-01 12:00:00'],
];
$notes = [['id' => 3, 'occurred_at' => '2026-11-01 11:00:00', 'body' => 'Call']];
$timeline = buildConversationTimeline($emails, $notes);
$actual = array_map(static fn($item) => $item['type'] . ':' . $item['item']['id'], $timeline);
if ($actual !== ['email:2', 'note:3', 'email:1']) {
    fwrite(STDERR, 'Timeline is not sorted by event time: ' . json_encode($actual) . "\n");
    exit(1);
}
echo "✓ notes are interleaved with emails by event time\n";

foreach ([[[], 0, null], [[['id' => 5]], 0, 5], [[['id' => 5], ['id' => 9]], 0, null], [[['id' => 5], ['id' => 9]], 9, 9], [[['id' => 5]], 9, null]] as [$teams, $selected, $expected]) {
    if (resolveNoteContactTeam($teams, $selected) !== $expected) {
        fwrite(STDERR, "Incorrect contact team selection\n");
        exit(1);
    }
}
echo "✓ one team is automatic; multiple teams require a valid choice\n";
if (splitNoteParticipantName('  Jane   van Doe ') !== ['Jane', 'van Doe']) {
    fwrite(STDERR, "Participant name was not split into first and last name\n");
    exit(1);
}
echo "✓ new participant names become first and last names\n";
if (noteTextLength('José') !== 4) {
    fwrite(STDERR, "Unicode participant name length is incorrect\n");
    exit(1);
}
echo "✓ participant name length validation counts Unicode characters\n";

foreach ([
    [['team_id' => 5], ['team_id' => 5, 'user_id' => null], true],
    [['team_id' => 5], ['team_id' => 9, 'user_id' => null], false],
    [['team_id' => 5], ['team_id' => 5, 'user_id' => 1], false],
    [['user_id' => 1], ['team_id' => 9, 'user_id' => null], true],
    [['user_id' => 1], ['team_id' => null, 'user_id' => 2], false],
    [['user_id' => 1], ['team_id' => null, 'user_id' => 1], true],
] as [$conversation, $target, $expected]) {
    if (noteTargetMatchesScope($conversation, $target) !== $expected) {
        fwrite(STDERR, "Incorrect linked object scope decision\n");
        exit(1);
    }
}
echo "✓ shared notes do not expose private or other-team objects\n";

$schema = file_get_contents(__DIR__ . '/../sql/migrations/2026-11-17_conversation_notes.sql');
foreach (['conversation_notes', 'conversation_note_participants', 'ON DELETE CASCADE', 'occurred_at'] as $required) {
    if (strpos($schema, $required) === false) {
        fwrite(STDERR, "Migration missing $required\n");
        exit(1);
    }
}
echo "✓ migration stores dated notes and cascading participant relations\n";
