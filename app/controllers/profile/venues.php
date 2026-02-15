<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$action = $_POST['action'] ?? '';
if ($action !== 'update_page_size') {
    return;
}

$requestedPageSize = (int) ($_POST['venues_page_size'] ?? $defaultPageSize);
$requestedPageSize = max($minPageSize, min($maxPageSize, $requestedPageSize));

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('UPDATE users SET venues_page_size = :venues_page_size WHERE id = :id');
    $stmt->execute([
        ':venues_page_size' => $requestedPageSize,
        ':id' => $currentUser['user_id']
    ]);
    $currentPageSize = $requestedPageSize;
    $currentUser['venues_page_size'] = $requestedPageSize;
    logAction($currentUser['user_id'] ?? null, 'profile_page_size_updated', sprintf('Page size set to %d', $requestedPageSize));
    $notice = 'Page size updated successfully.';
} catch (Throwable $error) {
    $errors[] = 'Failed to update page size.';
    logAction($currentUser['user_id'] ?? null, 'profile_page_size_error', $error->getMessage());
}
