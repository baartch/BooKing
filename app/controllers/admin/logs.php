<?php
/**
 * Admin logs tab controller.
 * Expects: $currentUser, &$errors, &$notice
 * Provides: $logsRows, $logsPagination
 */

$logsPage = isset($_GET['logs_page']) ? max(1, (int) $_GET['logs_page']) : 1;
$logsPerPage = isset($_GET['logs_per_page']) ? max(10, min(500, (int) $_GET['logs_per_page'])) : 100;
$logsQuery = isset($_GET['logs_q']) ? trim((string) $_GET['logs_q']) : '';

try {
    $pdo = getDatabaseConnection();

    $logsOffset = ($logsPage - 1) * $logsPerPage;
    $logsParams = [];
    $logsWhere = '';
    if ($logsQuery !== '') {
        $logsWhere = 'WHERE (u.username LIKE :q OR l.action LIKE :q OR l.details LIKE :q)';
        $logsParams[':q'] = '%' . $logsQuery . '%';
    }

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM logs l
         LEFT JOIN users u ON u.id = l.user_id
         ' . $logsWhere
    );
    $countStmt->execute($logsParams);
    $logsTotal = (int) $countStmt->fetchColumn();

    $listStmt = $pdo->prepare(
        'SELECT l.id, l.created_at AS timestamp, u.username, l.action, l.details
         FROM logs l
         LEFT JOIN users u ON u.id = l.user_id
         ' . $logsWhere . '
         ORDER BY l.created_at DESC, l.id DESC
         LIMIT :limit OFFSET :offset'
    );
    foreach ($logsParams as $key => $value) {
        $listStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $listStmt->bindValue(':limit', $logsPerPage, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $logsOffset, PDO::PARAM_INT);
    $listStmt->execute();

    $logsRows = $listStmt->fetchAll();
    $logsTotalPages = max(1, (int) ceil($logsTotal / $logsPerPage));

    $logsPagination = [
        'page' => $logsPage,
        'perPage' => $logsPerPage,
        'total' => $logsTotal,
        'totalPages' => $logsTotalPages,
        'query' => $logsQuery
    ];
} catch (Throwable $error) {
    $logsRows = [];
    $logsPagination = [
        'page' => $logsPage,
        'perPage' => $logsPerPage,
        'total' => 0,
        'totalPages' => 1,
        'query' => $logsQuery
    ];
    $errors[] = 'Failed to load logs.';
    logAction($currentUser['user_id'] ?? null, 'admin_logs_load_error', $error->getMessage());
}
