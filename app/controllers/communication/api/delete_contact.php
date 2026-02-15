<?php
require_once __DIR__ . '/../../../routes/auth/check.php';
require_once __DIR__ . '/../../../src-php/core/database.php';
require_once __DIR__ . '/../../../src-php/communication/contacts_helpers.php';
require_once __DIR__ . '/../../../src-php/core/object_links.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$contactId = (int) ($_POST['contact_id'] ?? 0);
$searchQuery = trim((string) ($_POST['q'] ?? ''));
$teamId = (int) ($_POST['team_id'] ?? 0);

$redirectParams = ['tab' => 'contacts'];
if ($teamId > 0) {
    $redirectParams['team_id'] = $teamId;
}
if ($searchQuery !== '') {
    $redirectParams['q'] = $searchQuery;
}

try {
    $pdo = getDatabaseConnection();
    if ($teamId <= 0 || !userHasTeamAccess($pdo, $userId, $teamId)) {
        $redirectParams['notice'] = 'contact_error';
    } else {
        $existing = fetchContact($pdo, $teamId, $contactId);
        if ($existing) {
            $pdo->beginTransaction();
            try {
                clearAllObjectLinks($pdo, 'contact', $contactId);
                $stmt = $pdo->prepare('DELETE FROM contacts WHERE id = :id AND team_id = :team_id');
                $stmt->execute([':id' => $contactId, ':team_id' => $teamId]);
                $pdo->commit();
            } catch (Throwable $error) {
                $pdo->rollBack();
                throw $error;
            }
            logAction($userId, 'contact_deleted', sprintf('Deleted contact %d', $contactId));
            $redirectParams['notice'] = 'contact_deleted';
        } else {
            $redirectParams['notice'] = 'contact_error';
        }
    }
} catch (Throwable $error) {
    logAction($userId, 'contact_delete_error', $error->getMessage());
    $redirectParams['notice'] = 'contact_error';
}

header('Location: ' . BASE_PATH . '/app/controllers/communication/index.php?' . http_build_query($redirectParams));
exit;
