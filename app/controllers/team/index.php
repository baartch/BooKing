<?php
require_once __DIR__ . '/../../models/auth/team_admin_check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/htmx_class.php';
require_once __DIR__ . '/../../models/core/layout.php';
require_once __DIR__ . '/../../models/communication/mailbox_helpers.php';
require_once __DIR__ . '/../../models/communication/email_templates_helpers.php';
require_once __DIR__ . '/../../models/communication/email_helpers.php';

$activeTab = $_GET['tab'] ?? 'members';
$validTabs = ['members', 'mailboxes', 'templates'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'members';
}

$errors = [
    'mailboxes' => [],
    'templates' => []
];
$notice = [
    'mailboxes' => '',
    'templates' => ''
];

$mailboxes = [];
$templates = [];
$teams = [];
$teamIds = [];

$noticeKey = (string) ($_GET['notice'] ?? '');
if ($noticeKey === 'mailbox_created') {
    $notice['mailboxes'] = 'Mailbox created successfully.';
} elseif ($noticeKey === 'mailbox_updated') {
    $notice['mailboxes'] = 'Mailbox updated successfully.';
} elseif ($noticeKey === 'template_created') {
    $notice['templates'] = 'Template created successfully.';
} elseif ($noticeKey === 'template_updated') {
    $notice['templates'] = 'Template updated successfully.';
}

$pdo = null;
try {
    $pdo = getDatabaseConnection();
    [$teams, $teamIds] = loadTeamAdminTeams($pdo, (int) ($currentUser['user_id'] ?? 0));
} catch (Throwable $error) {
    $errors['mailboxes'][] = 'Failed to load teams.';
    $errors['templates'][] = 'Failed to load teams.';
    logAction($currentUser['user_id'] ?? null, 'team_team_load_error', $error->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';
    if (in_array($action, ['delete_mailbox'], true)) {
        $activeTab = 'mailboxes';
    } elseif (in_array($action, ['delete_template'], true)) {
        $activeTab = 'templates';
    }

    if (!$pdo) {
        $errors[$activeTab][] = 'Database connection unavailable.';
    } elseif ($action === 'delete_mailbox') {
        $mailboxId = (int) ($_POST['mailbox_id'] ?? 0);
        if ($mailboxId <= 0) {
            $errors['mailboxes'][] = 'Select a mailbox to delete.';
        } else {
            try {
                $existingMailbox = fetchTeamMailbox($pdo, $mailboxId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingMailbox) {
                    $errors['mailboxes'][] = 'Mailbox not found.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM mailboxes WHERE id = :id');
                    $stmt->execute([':id' => $existingMailbox['id']]);
                    $notice['mailboxes'] = 'Mailbox deleted successfully.';
                    logAction($currentUser['user_id'] ?? null, 'team_mailbox_deleted', sprintf('Deleted mailbox %d', $existingMailbox['id']));
                }
            } catch (Throwable $error) {
                $errors['mailboxes'][] = 'Failed to delete mailbox.';
                logAction($currentUser['user_id'] ?? null, 'team_mailbox_delete_error', $error->getMessage());
            }
        }
    } elseif ($action === 'delete_template') {
        $templateId = (int) ($_POST['template_id'] ?? 0);
        if ($templateId <= 0) {
            $errors['templates'][] = 'Select a template to delete.';
        } else {
            try {
                $existingTemplate = fetchTeamTemplate($pdo, $templateId, (int) ($currentUser['user_id'] ?? 0));
                if (!$existingTemplate) {
                    $errors['templates'][] = 'Template not found.';
                } else {
                    $stmt = $pdo->prepare('DELETE FROM email_templates WHERE id = :id');
                    $stmt->execute([':id' => $existingTemplate['id']]);
                    $notice['templates'] = 'Template deleted successfully.';
                    logAction($currentUser['user_id'] ?? null, 'team_template_deleted', sprintf('Deleted template %d', $existingTemplate['id']));
                }
            } catch (Throwable $error) {
                $errors['templates'][] = 'Failed to delete template.';
                logAction($currentUser['user_id'] ?? null, 'team_template_delete_error', $error->getMessage());
            }
        }
    }
}

if ($pdo && $teamIds) {
    try {
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT m.*, t.name AS team_name
             FROM mailboxes m
             JOIN teams t ON t.id = m.team_id
             WHERE m.team_id IN (' . $placeholders . ')
             ORDER BY t.name, m.name'
        );
        $stmt->execute($teamIds);
        $mailboxes = $stmt->fetchAll();
    } catch (Throwable $error) {
        $errors['mailboxes'][] = 'Failed to load mailboxes.';
        logAction($currentUser['user_id'] ?? null, 'team_mailbox_list_error', $error->getMessage());
    }
}

if ($pdo) {
    try {
        $templates = loadTeamTemplates($pdo, (int) ($currentUser['user_id'] ?? 0));
    } catch (Throwable $error) {
        $errors['templates'][] = 'Failed to load templates.';
        logAction($currentUser['user_id'] ?? null, 'team_template_list_error', $error->getMessage());
    }
}

if (HTMX::isRequest() && $activeTab === 'mailboxes') {
    HTMX::pushUrl($_SERVER['REQUEST_URI']);
    require __DIR__ . '/../../views/team/mailboxes/mailboxes.php';
    return;
}

require __DIR__ . '/../../views/team/index.php';
