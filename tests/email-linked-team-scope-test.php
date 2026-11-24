<?php

require_once __DIR__ . '/../app/models/core/link_helpers.php';

class EmailLinkScopeFixtureStatement extends PDOStatement
{
    private string $query;
    private array $rows;

    public function __construct(string $query)
    {
        $this->query = $query;
        $this->rows = [];
    }

    public function execute(?array $params = null): bool
    {
        if (str_contains($this->query, 'FROM object_links')) {
            if (str_contains($this->query, 'team_id IN') && in_array(7, $params ?? [], true)) {
                $this->rows = [[
                    'left_type' => 'email',
                    'left_id' => 42,
                    'right_type' => 'task',
                    'right_id' => 8,
                ]];
            }
            return true;
        }

        if (str_contains($this->query, 'FROM team_tasks')) {
            $this->rows = [['id' => 8, 'title' => 'Follow up', 'priority' => 'A']];
        }

        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }
}

class EmailLinkScopeFixturePdo extends PDO
{
    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new EmailLinkScopeFixtureStatement($query);
    }
}

$pdo = new EmailLinkScopeFixturePdo();
$links = fetchLinkedObjects($pdo, 'email', 42, null, 19, [7]);
$expected = [['type' => 'task', 'id' => 8, 'label' => 'Follow up']];

if ($links !== $expected) {
    fwrite(STDERR, 'Expected email link lookup to include an accessible team-scoped task link.' . PHP_EOL);
    exit(1);
}

echo "✓ email link lookup includes accessible team-scoped task links\n";
