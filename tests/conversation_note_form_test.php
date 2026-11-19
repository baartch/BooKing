<?php
define('BASE_PATH', '');
function renderCsrfField(): void {}
function getCsrfToken(): string { return 'token'; }
function ensureConversationAccess(PDO $pdo, int $conversationId, int $userId): array { return ['user_id' => $userId]; }
function fetchUserTeams(PDO $pdo, int $userId): array { return []; }
function resolveLinkSourceScopeOrThrow(PDO $pdo, string $type, int $id, int $userId, array $scope): array { return ['user_id' => $userId]; }
function clearObjectLinks(PDO $pdo, string $type, int $id, ?int $teamId, ?int $userId): void { $pdo->links = []; }
function createObjectLink(PDO $pdo, string $source, int $sourceId, string $target, int $targetId, ?int $teamId, ?int $userId): void { $pdo->links[] = "$target:$targetId"; }
require_once __DIR__ . '/../app/models/communication/conversation_notes.php';

final class NoteFormTestPDO extends PDO
{
    public array $participants = [42];
    public array $links = [];
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new NoteFormTestStatement($this, $query); }
    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
}
final class NoteFormTestStatement extends PDOStatement
{
    public function __construct(private NoteFormTestPDO $pdo, private string $query) {}
    public function execute(?array $params = null): bool
    {
        if (str_starts_with($this->query, 'DELETE FROM conversation_note_participants')) {
            $this->pdo->participants = [];
        }
        if (str_starts_with($this->query, 'INSERT INTO conversation_note_participants')) {
            $this->pdo->participants[] = $params[1];
        }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed { return 1; }
}

$failures = 0;
$check = static function (bool $condition, string $label) use (&$failures): void {
    echo ($condition ? '✓ ' : '✗ ') . $label . "\n";
    $failures += $condition ? 0 : 1;
};
foreach ([0, 7] as $id) {
    $noteFormId = 'note-' . $id;
    $conversationId = 3;
    $noteEdit = ['id' => $id, 'body' => 'A note', 'occurred_at' => '2026-11-01 10:00:00', 'participants' => [['id' => 42]], 'links' => [['type' => 'contact', 'id' => 42, 'label' => 'Jane']]];
    $noteContactOptions = [['id' => 42, 'firstname' => 'Jane', 'surname' => 'Doe', 'email' => '']];
    $noteTeams = [['id' => 1, 'name' => 'Team 1'], ['id' => 2, 'name' => 'Team 2']];
    ob_start();
    require __DIR__ . '/../app/views/communication/conversation_note_form.php';
    $html = ob_get_clean();
    $check(!str_contains($html, 'name="participants[]"') && !str_contains($html, 'name="new_participants"') && !str_contains($html, 'name="contact_team_id"'), "note form $id has no participant or new-contact inputs");
    $check(str_contains($html, 'data-link-editor-trigger') && str_contains($html, 'name="link_items[]" value="contact:42"') && str_contains($html, 'data-link-editor-types="contact,venue,show,task,email"'), "note form $id retains linked object editor and existing links");
    $check((bool) preg_match('/class="detail-link-pill"[^>]*>.*?Jane/s', $html) && (bool) preg_match('/class="detail-link-edit"[^>]*data-link-editor-trigger[^>]*>.*?fa-pen/s', $html), "note form $id displays linked object pills and edit pencil");
}
$pdo = new NoteFormTestPDO();
saveConversationNote($pdo, 3, 1, 7, ['body' => 'Updated', 'occurred_at' => '2026-11-01T10:00', 'link_items' => ['contact:42']]);
$check($pdo->participants === [42], 'editing without participant inputs preserves existing participants');
$check($pdo->links === ['contact:42'], 'editing still saves related objects');
exit($failures === 0 ? 0 : 1);
