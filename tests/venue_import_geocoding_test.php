<?php
// Exercise the real importer and Mapbox request with isolated database/network boundaries.
final class ImportTestPDO extends PDO
{
    public array $rows = [];
    public string $apiKey = 'test-key';
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new ImportTestStatement($this, $query);
    }
}

final class ImportTestStatement extends PDOStatement
{
    private array $bindings = [];
    public function __construct(private ImportTestPDO $pdo, private string $query) {}
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bindings[$param] = $value;
        return true;
    }
    public function execute(?array $params = null): bool
    {
        if (str_contains($this->query, 'INSERT INTO venues')) {
            $this->pdo->rows[] = $params;
        }
        return true;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return [['setting_key' => 'mapbox_api_key_server', 'setting_value' => $this->pdo->apiKey]];
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return ($this->bindings[':lat1'] ?? null) === 50.0 ? ['name' => 'Existing'] : false;
    }
}

final class ImportTestHttps
{
    public $context;
    public static array $requests = [];
    private string $body = '';
    private int $position = 0;
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
        self::$requests[] = $query;
        if (str_contains($query['q'], 'Network failure')) {
            return false;
        }
        $latitude = str_contains($query['q'], 'Duplicate') ? 50.0 : 47.3769;
        $features = str_contains($query['q'], 'Unknown') ? [] : [[
            'properties' => ['context' => [], 'coordinates' => ['latitude' => $latitude, 'longitude' => 8.5417]]
        ]];
        $this->body = json_encode(['features' => $features]);
        return true;
    }
    public function stream_read(int $count): string
    {
        $part = substr($this->body, $this->position, $count);
        $this->position += strlen($part);
        return $part;
    }
    public function stream_eof(): bool { return $this->position >= strlen($this->body); }
    public function stream_stat(): array { return []; }
}

$fixture = sys_get_temp_dir() . '/venue-import-' . bin2hex(random_bytes(8));
mkdir($fixture . '/core', 0700, true);
mkdir($fixture . '/venues', 0700, true);
foreach (['form_helpers.php', 'object_links.php', 'search_helpers.php', 'settings.php'] as $file) {
    copy(__DIR__ . '/../app/models/core/' . $file, $fixture . '/core/' . $file);
}
foreach (['venues_actions.php', 'venues_repository.php'] as $file) {
    copy(__DIR__ . '/../app/models/venues/' . $file, $fixture . '/venues/' . $file);
}
file_put_contents($fixture . '/core/database.php', '<?php');
register_shutdown_function(static function () use ($fixture): void {
    foreach (glob($fixture . '/core/*') as $file) { unlink($file); }
    foreach (glob($fixture . '/venues/*') as $file) { unlink($file); }
    rmdir($fixture . '/core');
    rmdir($fixture . '/venues');
    rmdir($fixture);
});
$pdo = new ImportTestPDO();
function getDatabaseConnection(): PDO { return $GLOBALS['pdo']; }
function decryptSettingValue(?string $value): string { return $value ?? ''; }
function logAction(?int $userId, string $action, string $details = ''): void {}
require $fixture . '/venues/venues_actions.php';
stream_wrapper_unregister('https');
stream_wrapper_register('https', ImportTestHttps::class);

$entries = [
    ['name' => 'Found', 'street' => 'Street 1', 'city' => 'Zurich', 'state' => 'ZH', 'country' => 'CH'],
    ['name' => 'No result', 'street' => 'Unknown 1', 'city' => 'Zurich', 'state' => 'ZH', 'country' => 'CH'],
    ['name' => 'Supplied', 'street' => 'Street 2', 'city' => 'Zurich', 'state' => 'ZH', 'country' => 'CH', 'latitude' => 1, 'longitude' => 2],
    ['name' => 'No address', 'state' => 'ZH'],
    ['name' => 'Partial', 'street' => 'Street 3', 'city' => 'Zurich', 'state' => 'ZH', 'country' => 'CH', 'latitude' => 3],
    ['name' => 'Duplicate', 'street' => 'Duplicate 1', 'city' => 'Zurich', 'state' => 'ZH', 'country' => 'CH'],
    ['name' => 'Network error', 'street' => 'Network failure', 'city' => 'Zurich', 'state' => 'ZH', 'country' => 'CH'],
    ['name' => 'Street only', 'street' => 'Street 4', 'state' => 'ZH'],
    ['name' => 'Zero', 'street' => 'Street 5', 'state' => 'ZH', 'latitude' => 0, 'longitude' => 0],
];
$result = handleVenueImport(['role' => 'admin', 'user_id' => 1], ['CH'], json_encode($entries));
$rows = array_column($pdo->rows, null, ':name');
$checks = [
    'lookup coordinates are saved' => ($rows['Found'][':latitude'] ?? null) === 47.3769 && ($rows['Found'][':longitude'] ?? null) === 8.5417,
    'Mapbox uses street, city, country and configured key' => (ImportTestHttps::$requests[0]['q'] ?? null) === 'Street 1, Zurich' && (ImportTestHttps::$requests[0]['country'] ?? null) === 'CH' && (ImportTestHttps::$requests[0]['access_token'] ?? null) === 'test-key',
    'failed lookup leaves coordinates empty' => isset($rows['No result']) && $rows['No result'][':latitude'] === null && $rows['No result'][':longitude'] === null,
    'supplied coordinates are preserved' => ($rows['Supplied'][':latitude'] ?? null) === 1.0 && ($rows['Supplied'][':longitude'] ?? null) === 2.0,
    'addressless row is imported without coordinates' => isset($rows['No address']) && $rows['No address'][':latitude'] === null && $rows['No address'][':longitude'] === null,
    'partial coordinates are preserved' => ($rows['Partial'][':latitude'] ?? null) === 3.0 && $rows['Partial'][':longitude'] === null,
    'only rows missing both coordinates are looked up' => count(ImportTestHttps::$requests) === 5,
    'geocoded duplicates are skipped' => !isset($rows['Duplicate']) && count($result['errors']) === 1,
    'network failure does not prevent import' => isset($rows['Network error']) && $rows['Network error'][':latitude'] === null && $rows['Network error'][':longitude'] === null,
    'street-only lookup uses default country' => (ImportTestHttps::$requests[4]['q'] ?? null) === 'Street 4' && (ImportTestHttps::$requests[4]['country'] ?? null) === 'DE' && ($rows['Street only'][':latitude'] ?? null) === 47.3769,
    'zero coordinates remain unchanged' => ($rows['Zero'][':latitude'] ?? null) === 0.0 && ($rows['Zero'][':longitude'] ?? null) === 0.0,
];
$pdo->apiKey = '';
$pdo->rows = [];
ImportTestHttps::$requests = [];
$resultWithoutKey = handleVenueImport(['role' => 'admin'], ['CH'], json_encode([$entries[0]]));
$checks['missing key leaves coordinates empty and imports'] = count($pdo->rows) === 1 && $pdo->rows[0][':latitude'] === null && $pdo->rows[0][':longitude'] === null && !$resultWithoutKey['errors'] && !ImportTestHttps::$requests;
stream_wrapper_restore('https');
$failures = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? '✓ ' : '✗ ') . $label . "\n";
    $failures += $passed ? 0 : 1;
}
exit($failures ? 1 : 0);
