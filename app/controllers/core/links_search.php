<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/link_search.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim((string) ($_GET['q'] ?? ''));
$types = array_filter(explode(',', (string) ($_GET['types'] ?? 'contact,venue,conversation')));
$scopeMailboxId = (int) ($_GET['mailbox_id'] ?? 0);
$userId = (int) ($currentUser['user_id'] ?? 0);

if ($query === '' || mb_strlen($query) < 2) {
    echo json_encode(['items' => []]);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $items = searchLinkTargets($pdo, $userId, $query, $types, $scopeMailboxId);
    echo json_encode(['items' => $items]);
} catch (Throwable $error) {
    logAction($userId, 'link_search_error', $error->getMessage());
    http_response_code(500);
    echo json_encode(['items' => []]);
}
