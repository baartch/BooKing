<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/communication/email_parents.php';

$query = trim((string) ($_GET['q'] ?? ''));

header('Content-Type: application/json; charset=utf-8');

if ($query === '') {
    echo json_encode(['items' => []]);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $items = searchParentCandidates($pdo, $query, 8);
    echo json_encode(['items' => $items]);
} catch (Throwable $error) {
    logAction($currentUser['user_id'] ?? null, 'email_parent_lookup_error', $error->getMessage());
    http_response_code(500);
    echo json_encode(['items' => []]);
}
