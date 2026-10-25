<?php
/**
 * SMTP step: global SMTP mailbox (required — sends login codes).
 * Expects: $installerUrl, $csrfToken.
 */

$smtpEncryption = (string) ($_POST['smtp_encryption'] ?? 'tls');
?>
<p class="mb-4">
    Enter your outgoing mail (SMTP) server. BooKing needs it to send the login
    codes — a verification code will be sent to your admin address right after
    saving.
</p>

<form method="post" action="<?php echo htmlspecialchars($installerUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="save_smtp">
    <input type="hidden" name="name" value="Global SMTP">

    <div class="field">
        <label class="label" for="display_name">Sender display name (optional)</label>
        <div class="control">
            <input class="input" type="text" id="display_name" name="display_name"
                   value="<?php echo htmlspecialchars((string) ($_POST['display_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="smtp_host">SMTP host</label>
        <div class="control">
            <input class="input" type="text" id="smtp_host" name="smtp_host" required
                   value="<?php echo htmlspecialchars((string) ($_POST['smtp_host'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="smtp_port">SMTP port</label>
        <div class="control">
            <input class="input" type="number" id="smtp_port" name="smtp_port" min="1" max="65535" required
                   value="<?php echo htmlspecialchars((string) ($_POST['smtp_port'] ?? '587'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="smtp_username">SMTP username</label>
        <div class="control">
            <input class="input" type="text" id="smtp_username" name="smtp_username" required
                   value="<?php echo htmlspecialchars((string) ($_POST['smtp_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>

    <div class="field">
        <label class="label" for="smtp_password">SMTP password</label>
        <div class="control">
            <input class="input" type="password" id="smtp_password" name="smtp_password" required>
        </div>
    </div>

    <div class="field">
        <label class="label" for="smtp_encryption">Encryption</label>
        <div class="control">
            <div class="select is-fullwidth">
                <select id="smtp_encryption" name="smtp_encryption">
                    <?php foreach (['tls' => 'STARTTLS (recommended, port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'None'] as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $smtpEncryption === $value ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <button type="submit" class="button is-primary is-fullwidth">Save SMTP settings and send code</button>
</form>
