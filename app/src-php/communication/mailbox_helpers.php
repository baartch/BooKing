<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/form_helpers.php';

function mailboxFormDefaults(int $defaultImapPort = 993, int $defaultSmtpPort = 587): array
{
    return [
        'team_id' => null,
        'user_id' => null,
        'name' => '',
        'imap_host' => '',
        'imap_port' => $defaultImapPort,
        'imap_username' => '',
        'imap_encryption' => 'ssl',
        'smtp_host' => '',
        'smtp_port' => $defaultSmtpPort,
        'smtp_username' => '',
        'smtp_encryption' => 'tls',
        'delete_after_retrieve' => false,
        'store_sent_on_server' => false
    ];
}

function mailboxFormValuesFromRow(array $mailbox, int $defaultImapPort = 993, int $defaultSmtpPort = 587): array
{
    return [
        'team_id' => isset($mailbox['team_id']) ? (int) $mailbox['team_id'] : null,
        'user_id' => isset($mailbox['user_id']) ? (int) $mailbox['user_id'] : null,
        'name' => (string) ($mailbox['name'] ?? ''),
        'imap_host' => (string) ($mailbox['imap_host'] ?? ''),
        'imap_port' => (int) ($mailbox['imap_port'] ?? $defaultImapPort),
        'imap_username' => (string) ($mailbox['imap_username'] ?? ''),
        'imap_encryption' => (string) ($mailbox['imap_encryption'] ?? 'ssl'),
        'smtp_host' => (string) ($mailbox['smtp_host'] ?? ''),
        'smtp_port' => (int) ($mailbox['smtp_port'] ?? $defaultSmtpPort),
        'smtp_username' => (string) ($mailbox['smtp_username'] ?? ''),
        'smtp_encryption' => (string) ($mailbox['smtp_encryption'] ?? 'tls'),
        'delete_after_retrieve' => !empty($mailbox['delete_after_retrieve']),
        'store_sent_on_server' => !empty($mailbox['store_sent_on_server'])
    ];
}

function buildMailboxFormInput(array $post, array $options = []): array
{
    $allowedEncryptions = $options['allowed_encryptions'] ?? ['ssl', 'tls', 'none'];
    $defaultImapPort = (int) ($options['default_imap_port'] ?? 993);
    $defaultSmtpPort = (int) ($options['default_smtp_port'] ?? 587);
    $requireTeam = (bool) ($options['require_team'] ?? false);
    $teamIds = $options['team_ids'] ?? [];
    $defaultTeamId = isset($options['default_team_id']) ? (int) $options['default_team_id'] : 0;
    $isCreate = (bool) ($options['is_create'] ?? true);
    $requireImap = (bool) ($options['require_imap'] ?? true);
    $requireSmtp = (bool) ($options['require_smtp'] ?? true);

    $teamId = (int) ($post['team_id'] ?? 0);
    if ($teamId <= 0 && $defaultTeamId > 0) {
        $teamId = $defaultTeamId;
    }

    $useSameCredentials = ($post['use_same_credentials'] ?? '') === '1';
    $mailboxName = trim((string) ($post['name'] ?? ''));
    $imapHost = trim((string) ($post['imap_host'] ?? ''));
    $imapPort = (int) ($post['imap_port'] ?? $defaultImapPort);
    $imapUsername = trim((string) ($post['imap_username'] ?? ''));
    $imapPassword = trim((string) ($post['imap_password'] ?? ''));
    $imapEncryption = (string) ($post['imap_encryption'] ?? 'ssl');
    $deleteAfterRetrieve = ($post['delete_after_retrieve'] ?? '') === '1';
    $storeSentOnServer = ($post['store_sent_on_server'] ?? '') === '1';
    $smtpHost = trim((string) ($post['smtp_host'] ?? ''));
    $smtpPort = (int) ($post['smtp_port'] ?? $defaultSmtpPort);
    $smtpUsername = trim((string) ($post['smtp_username'] ?? ''));
    $smtpPassword = trim((string) ($post['smtp_password'] ?? ''));
    $smtpEncryption = (string) ($post['smtp_encryption'] ?? 'tls');

    if ($useSameCredentials) {
        if ($imapPassword !== '') {
            $smtpPassword = $imapPassword;
        }
        if ($imapUsername !== '') {
            $smtpUsername = $imapUsername;
        }
    }

    $errors = [];
    if ($requireTeam) {
        if ($teamId <= 0 || !in_array($teamId, $teamIds, true)) {
            $errors[] = 'Select a valid team.';
        }
    } else {
        $teamId = null;
    }

    if ($mailboxName === '') {
        $errors[] = 'Mailbox name is required.';
    }

    $imapRequired = $requireImap || $imapHost !== '' || $imapUsername !== '' || $imapPassword !== '';
    $smtpRequired = $requireSmtp || $smtpHost !== '' || $smtpUsername !== '' || $smtpPassword !== '';

    if ($imapRequired && ($imapHost === '' || $imapUsername === '')) {
        $errors[] = 'IMAP host and username are required.';
    }

    if ($smtpRequired && ($smtpHost === '' || $smtpUsername === '')) {
        $errors[] = 'SMTP host and username are required.';
    }

    if ($imapRequired && ($imapPort < 1 || $imapPort > 65535)) {
        $errors[] = 'IMAP port must be between 1 and 65535.';
    }

    if ($smtpRequired && ($smtpPort < 1 || $smtpPort > 65535)) {
        $errors[] = 'SMTP port must be between 1 and 65535.';
    }

    if ($imapRequired && !in_array($imapEncryption, $allowedEncryptions, true)) {
        $errors[] = 'Select a valid IMAP encryption setting.';
    }

    if ($smtpRequired && !in_array($smtpEncryption, $allowedEncryptions, true)) {
        $errors[] = 'Select a valid SMTP encryption setting.';
    }

    if ($isCreate && $imapRequired && $imapPassword === '') {
        $errors[] = 'IMAP password is required when creating a mailbox.';
    }

    if ($isCreate && $smtpRequired && $smtpPassword === '') {
        $errors[] = 'SMTP password is required when creating a mailbox.';
    }

    $values = [
        'team_id' => $teamId,
        'user_id' => null,
        'name' => $mailboxName,
        'imap_host' => $imapHost,
        'imap_port' => $imapPort,
        'imap_username' => $imapUsername,
        'imap_encryption' => $imapEncryption,
        'delete_after_retrieve' => $deleteAfterRetrieve,
        'store_sent_on_server' => $storeSentOnServer,
        'smtp_host' => $smtpHost,
        'smtp_port' => $smtpPort,
        'smtp_username' => $smtpUsername,
        'smtp_encryption' => $smtpEncryption
    ];

    return [
        'values' => $values,
        'imap_password' => $imapPassword,
        'smtp_password' => $smtpPassword,
        'errors' => $errors
    ];
}

function persistMailbox(PDO $pdo, array $values, string $imapPassword, string $smtpPassword, ?array $existingMailbox = null): int
{
    if ($existingMailbox) {
        $imapPasswordValue = $imapPassword !== '' ? encryptSettingValue($imapPassword) : $existingMailbox['imap_password'];
        $smtpPasswordValue = $smtpPassword !== '' ? encryptSettingValue($smtpPassword) : $existingMailbox['smtp_password'];
        $imapPasswordValue = $imapPasswordValue ?? '';
        $smtpPasswordValue = $smtpPasswordValue ?? '';
        $stmt = $pdo->prepare(
            'UPDATE mailboxes
             SET name = :name,
                 imap_host = :imap_host,
                 imap_port = :imap_port,
                 imap_username = :imap_username,
                 imap_password = :imap_password,
                 imap_encryption = :imap_encryption,
                 delete_after_retrieve = :delete_after_retrieve,
                 store_sent_on_server = :store_sent_on_server,
                 smtp_host = :smtp_host,
                 smtp_port = :smtp_port,
                 smtp_username = :smtp_username,
                 smtp_password = :smtp_password,
                 smtp_encryption = :smtp_encryption
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $values['name'],
            ':imap_host' => $values['imap_host'],
            ':imap_port' => $values['imap_port'],
            ':imap_username' => $values['imap_username'],
            ':imap_password' => $imapPasswordValue,
            ':imap_encryption' => $values['imap_encryption'],
            ':delete_after_retrieve' => $values['delete_after_retrieve'] ? 1 : 0,
            ':store_sent_on_server' => $values['store_sent_on_server'] ? 1 : 0,
            ':smtp_host' => $values['smtp_host'],
            ':smtp_port' => $values['smtp_port'],
            ':smtp_username' => $values['smtp_username'],
            ':smtp_password' => $smtpPasswordValue,
            ':smtp_encryption' => $values['smtp_encryption'],
            ':id' => $existingMailbox['id']
        ]);

        return (int) $existingMailbox['id'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO mailboxes
         (team_id, user_id, name, imap_host, imap_port, imap_username, imap_password, imap_encryption,
          delete_after_retrieve, store_sent_on_server,
          smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption)
         VALUES
         (:team_id, :user_id, :name, :imap_host, :imap_port, :imap_username, :imap_password, :imap_encryption,
          :delete_after_retrieve, :store_sent_on_server,
          :smtp_host, :smtp_port, :smtp_username, :smtp_password, :smtp_encryption)'
    );
    $imapPasswordValue = encryptSettingValue($imapPassword) ?? '';
    $smtpPasswordValue = encryptSettingValue($smtpPassword) ?? '';
    $stmt->execute([
        ':team_id' => $values['team_id'],
        ':user_id' => $values['user_id'],
        ':name' => $values['name'],
        ':imap_host' => $values['imap_host'],
        ':imap_port' => $values['imap_port'],
        ':imap_username' => $values['imap_username'],
        ':imap_password' => $imapPasswordValue,
        ':imap_encryption' => $values['imap_encryption'],
        ':delete_after_retrieve' => $values['delete_after_retrieve'] ? 1 : 0,
        ':store_sent_on_server' => $values['store_sent_on_server'] ? 1 : 0,
        ':smtp_host' => $values['smtp_host'],
        ':smtp_port' => $values['smtp_port'],
        ':smtp_username' => $values['smtp_username'],
        ':smtp_password' => $smtpPasswordValue,
        ':smtp_encryption' => $values['smtp_encryption']
    ]);

    return (int) $pdo->lastInsertId();
}

function fetchGlobalMailbox(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        'SELECT *
         FROM mailboxes
         WHERE team_id IS NULL AND user_id IS NULL
         ORDER BY id
         LIMIT 1'
    );
    $mailbox = $stmt->fetch();
    return $mailbox ?: null;
}

function fetchTeamMailbox(PDO $pdo, int $mailboxId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, t.name AS team_name
         FROM mailboxes m
         JOIN team_members tm ON tm.team_id = m.team_id
         JOIN teams t ON t.id = m.team_id
         WHERE m.id = :id AND tm.user_id = :user_id AND tm.role = "admin"
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $mailboxId,
        ':user_id' => $userId
    ]);
    $mailbox = $stmt->fetch();

    return $mailbox ?: null;
}

function loadTeamAdminTeams(PDO $pdo, int $userId): array
{
    $teamsStmt = $pdo->prepare(
        'SELECT t.id, t.name
         FROM teams t
         JOIN team_members tm ON tm.team_id = t.id
         WHERE tm.user_id = :user_id AND tm.role = "admin"
         ORDER BY t.name'
    );
    $teamsStmt->execute([':user_id' => $userId]);
    $teams = $teamsStmt->fetchAll();
    $teamIds = array_map('intval', array_column($teams, 'id'));

    return [$teams, $teamIds];
}
