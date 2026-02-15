<?php
/**
 * Admin users tab controller.
 * Expects: $currentUser, &$errors, &$notice
 * Provides: $users, $teamsByUser, $editUserId, $editUser
 */

$editUserId = isset($_GET['edit_user_id']) ? (int) $_GET['edit_user_id'] : 0;
$editUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $role = (string) ($_POST['role'] ?? 'agent');

        if ($username === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if (!in_array($role, ['admin', 'agent'], true)) {
            $errors[] = 'Invalid role selected.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username');
                $stmt->execute([':username' => $username]);
                if ($stmt->fetch()) {
                    $errors[] = 'Username already exists.';
                } else {
                    $randomPassword = bin2hex(random_bytes(16));
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (username, display_name, password_hash, role)
                         VALUES (:username, :display_name, :password_hash, :role)'
                    );
                    $stmt->execute([
                        ':username' => $username,
                        ':display_name' => null,
                        ':password_hash' => password_hash($randomPassword, PASSWORD_DEFAULT),
                        ':role' => $role
                    ]);
                    logAction($currentUser['user_id'] ?? null, 'user_created', sprintf('Created user %s', $username));
                    $notice = 'User created successfully.';
                }
            } catch (Throwable $error) {
                $errors[] = 'Failed to create user.';
                logAction($currentUser['user_id'] ?? null, 'user_create_error', $error->getMessage());
            }
        }
    }

    if ($action === 'update_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $role = (string) ($_POST['role'] ?? '');

        if ($userId <= 0 || $username === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }

        if ($role !== '' && !in_array($role, ['admin', 'agent'], true)) {
            $errors[] = 'Invalid role selected.';
        }

        if (($currentUser['user_id'] ?? 0) === $userId && $role !== '' && $role !== ($currentUser['role'] ?? '')) {
            $errors[] = 'You cannot change your own role.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id != :id');
                $stmt->execute([
                    ':username' => $username,
                    ':id' => $userId
                ]);
                if ($stmt->fetch()) {
                    $errors[] = 'Username already exists.';
                } else {
                    $pdo->beginTransaction();
                    $displayName = trim((string) ($_POST['display_name'] ?? ''));
                    if ($displayName !== '' && mb_strlen($displayName) > 120) {
                        $errors[] = 'Displayname must be at most 120 characters.';
                    }

                    if (!$errors) {
                        $stmt = $pdo->prepare('UPDATE users SET username = :username, display_name = :display_name, role = :role WHERE id = :id');
                        $stmt->execute([
                            ':username' => $username,
                            ':display_name' => $displayName !== '' ? $displayName : null,
                            ':role' => $role !== '' ? $role : 'agent',
                            ':id' => $userId
                        ]);
                        $pdo->commit();
                        logAction($currentUser['user_id'] ?? null, 'user_updated', sprintf('Updated user %d', $userId));
                        $notice = 'User updated successfully.';
                        $editUserId = 0;
                    } else {
                        $pdo->rollBack();
                    }
                }
            } catch (Throwable $error) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Failed to update user.';
                logAction($currentUser['user_id'] ?? null, 'user_update_error', $error->getMessage());
            }
        }
    }

    if ($action === 'delete') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $errors[] = 'Please select a user to delete.';
        }

        if (($currentUser['user_id'] ?? 0) === $userId) {
            $errors[] = 'You cannot delete your own account.';
        }

        if (!$errors) {
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute([':id' => $userId]);
                logAction($currentUser['user_id'] ?? null, 'user_deleted', sprintf('Deleted user %d', $userId));
                $notice = 'User deleted successfully.';
            } catch (Throwable $error) {
                $errors[] = 'Failed to delete user.';
                logAction($currentUser['user_id'] ?? null, 'user_delete_error', $error->getMessage());
            }
        }
    }
}

try {
    $pdo = getDatabaseConnection();

    $stmt = $pdo->query('SELECT id, username, display_name, role, created_at FROM users ORDER BY username');
    $users = $stmt->fetchAll();

    // Teams by user (for display only)
    $teamMembersStmt = $pdo->query(
        'SELECT tm.team_id, tm.user_id, tm.role, u.username, t.name AS team_name
         FROM team_members tm
         JOIN users u ON u.id = tm.user_id
         JOIN teams t ON t.id = tm.team_id
         ORDER BY u.username'
    );
    $teamMembersRows = $teamMembersStmt->fetchAll();

    $teamsByUser = [];
    foreach ($teamMembersRows as $row) {
        $userId = (int) $row['user_id'];
        $teamName = (string) $row['team_name'];
        $teamsByUser[$userId][] = $teamName;
    }

    if ($editUserId > 0) {
        foreach ($users as $user) {
            if ((int) $user['id'] === $editUserId) {
                $editUser = $user;
                break;
            }
        }
        if (!$editUser) {
            $errors[] = 'User not found.';
            $editUserId = 0;
        }
    }
} catch (Throwable $error) {
    $users = $users ?? [];
    $teamsByUser = $teamsByUser ?? [];
    $errors[] = 'Failed to load users.';
    logAction($currentUser['user_id'] ?? null, 'admin_users_load_error', $error->getMessage());
}
