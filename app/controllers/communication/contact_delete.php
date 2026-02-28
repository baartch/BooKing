<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/communication/contacts_helpers.php';
require_once __DIR__ . '/../../models/communication/navigation_helpers.php';
require_once __DIR__ . '/../../models/core/error_helpers.php';
require_once __DIR__ . '/../../models/core/object_links.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

verifyCsrfToken();

$userId = (int) ($currentUser['user_id'] ?? 0);
$contactId = (int) ($_POST['contact_id'] ?? 0);
$searchQuery = trim((string) ($_POST['q'] ?? ''));
$teamId = (int) ($_POST['team_id'] ?? 0);

$redirectParams = buildContactsTabQuery(
    $teamId > 0 ? $teamId : null,
    $searchQuery !== '' ? $searchQuery : null
);

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
    logThrowable($userId, 'contact_delete_error', $error);
    $redirectParams['notice'] = 'contact_error';
}

header('Location: ' . buildCommunicationUrl($redirectParams));
exit;
