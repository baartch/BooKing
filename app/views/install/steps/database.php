<?php
/**
 * Database step: credentials, config.php generation, schema import.
 * Expects: $lock, $errors, $installerUrl, $csrfToken and the controller
 * set-up session state ($_SESSION['install_config_content'] on fallback).
 */

if (!empty($lock['config_written'])) {
    // config.php exists (written by the wizard or uploaded via FTP).
    // Only the schema import remains.
    ?>
    <p class="mb-4">The configuration file is in place. The database schema will be imported next.</p>
    <form method="post" action="<?php echo htmlspecialchars($installerUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="save_database">
        <button type="submit" class="button is-primary is-fullwidth">Import schema and continue</button>
    </form>
    <?php
    return;
}
?>
<p class="mb-4">
    Create an <strong>empty</strong> database in your hosting panel (e.g. Plesk) first,
    then enter its credentials here. The wizard writes <code>config/config.php</code>
    and imports the database schema.
</p>

<form method="post" action="<?php echo htmlspecialchars($installerUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="save_database">

    <div class="field">
        <label class="label" for="db_host">Database host</label>
        <div class="control">
            <input class="input" type="text" id="db_host" name="db_host" required
                   value="<?php echo htmlspecialchars((string) ($_POST['db_host'] ?? 'localhost'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="db_port">Database port</label>
        <div class="control">
            <input class="input" type="number" id="db_port" name="db_port" min="1" max="65535" required
                   value="<?php echo htmlspecialchars((string) ($_POST['db_port'] ?? '3306'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="db_name">Database name</label>
        <div class="control">
            <input class="input" type="text" id="db_name" name="db_name" required
                   value="<?php echo htmlspecialchars((string) ($_POST['db_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="db_user">Database user</label>
        <div class="control">
            <input class="input" type="text" id="db_user" name="db_user" required
                   value="<?php echo htmlspecialchars((string) ($_POST['db_user'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="db_password">Database password</label>
        <div class="control">
            <input class="input" type="password" id="db_password" name="db_password">
        </div>
    </div>

    <button type="submit" class="button is-primary is-fullwidth">Save database configuration</button>
</form>

<?php if (!empty($_SESSION['install_config_content'])): ?>
    <hr>
    <h2 class="title is-5">Manual config upload</h2>
    <p class="mb-4">
        PHP cannot write <code>config/config.php</code> on this server. Download the
        generated file and upload it into the <code>config/</code> directory via FTP.
    </p>
    <div class="buttons">
        <a class="button is-link" href="<?php echo htmlspecialchars($installerUrl . '?step=config_download', ENT_QUOTES, 'UTF-8'); ?>">Download config.php</a>
    </div>
    <form method="post" action="<?php echo htmlspecialchars($installerUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="check_config_upload">
        <button type="submit" class="button is-primary is-fullwidth">I uploaded the file</button>
    </form>
<?php endif; ?>
