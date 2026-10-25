<?php
/**
 * Done step: rendered directly after completion (the installer is now locked).
 * Expects: $installerUrl.
 */
?>
<div class="notification is-success mb-4">
    <strong>Installation complete!</strong> BooKing is ready.
</div>

<h2 class="title is-5">Finish these steps</h2>
<ol class="content">
    <li>
        <strong>Scheduled task:</strong> in your hosting panel (Plesk: Scheduled Tasks),
        add a cron job that runs every minute:
        <code>php /path/to/your/app/controllers/tasks/taskhandler.php</code>
        (replace the path with the full server path to your BooKing directory).
    </li>
    <li>
        <strong>Backup:</strong> download and store <code>config/config.php</code> safely.
        The <code>ENCRYPTION_KEY</code> inside cannot be regenerated — without it,
        encrypted settings (like mailbox passwords) are unrecoverable.
    </li>
    <li>
        <strong>Cleanup:</strong> delete <code>install.php</code> from the server via FTP.
        The installer is also permanently disabled by <code>config/installer.lock</code>.
    </li>
    <li>
        <strong>First login:</strong> open the app and log in with your admin email
        address — the login code is sent there.
    </li>
</ol>

<a class="button is-primary" href="<?php echo htmlspecialchars(BASE_PATH . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">Open BooKing</a>
