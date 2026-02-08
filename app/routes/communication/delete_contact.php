<?php
require_once __DIR__ . '/../auth/check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/communication/contacts_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$contactId = (int) ($_POST['contact_id'] ?? 0);
$searchQuery = trim((string) ($_POST['q'] ?? ''));

$redirectParams = ['tab' => 'contacts'];
if ($searchQuery !== '') {
    $redirectParams['q'] = $searchQuery;
}

try {
    $pdo = getDatabaseConnection();
    $existing = fetchContact($pdo, $contactId);
    if ($existing) {
        $stmt = $pdo->prepare('UPDATE email_messages SET parent_type = NULL, parent_id = NULL WHERE parent_type = "contact" AND parent_id = :id');
        $stmt->execute([':id' => $contactId]);

        $stmt = $pdo->prepare('DELETE FROM contacts WHERE id = :id');
        $stmt->execute([':id' => $contactId]);
        logAction($userId, 'contact_deleted', sprintf('Deleted contact %d', $contactId));
        $redirectParams['notice'] = 'contact_deleted';
    } else {
        $redirectParams['notice'] = 'contact_error';
    }
} catch (Throwable $error) {
    logAction($userId, 'contact_delete_error', $error->getMessage());
    $redirectParams['notice'] = 'contact_error';
}

header('Location: ' . BASE_PATH . '/app/pages/communication/index.php?' . http_build_query($redirectParams));
exit;
