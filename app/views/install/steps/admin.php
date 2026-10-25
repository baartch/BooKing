<?php
/**
 * Admin step: create the first admin user.
 * Expects: $installerUrl, $csrfToken.
 */
?>
<p class="mb-4">
    Create the first admin account. Login is done with a one-time code sent to
    this email address, so make sure the mailbox is reachable.
</p>

<form method="post" action="<?php echo htmlspecialchars($installerUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="create_admin">

    <div class="field">
        <label class="label" for="admin_email">Admin email address</label>
        <div class="control">
            <input class="input" type="email" id="admin_email" name="admin_email" required
                   value="<?php echo htmlspecialchars((string) ($_POST['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="display_name">Display name (optional)</label>
        <div class="control">
            <input class="input" type="text" id="display_name" name="display_name" maxlength="120"
                   value="<?php echo htmlspecialchars((string) ($_POST['display_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <button type="submit" class="button is-primary is-fullwidth">Create admin account</button>
</form>
