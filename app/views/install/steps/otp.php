<?php
/**
 * OTP verification step: proves SMTP works and the admin can receive codes.
 * Expects: $lock, $installerUrl, $csrfToken.
 */
?>
<p class="mb-4">
    Enter the verification code that was sent to
    <strong><?php echo htmlspecialchars((string) ($lock['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>.
    The code expires in 10 minutes.
</p>

<form method="post" action="<?php echo htmlspecialchars($installerUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="verify_otp">

    <div class="field">
        <label class="label" for="otp_code">Verification code</label>
        <div class="control">
            <input class="input" type="text" id="otp_code" name="otp_code" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required>
        </div>
    </div>

    <button type="submit" class="button is-primary is-fullwidth">Verify code</button>
</form>

<div class="buttons mt-4">
    <form method="post" action="<?php echo htmlspecialchars($installerUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="resend_otp">
        <button type="submit" class="button is-light">Resend code</button>
    </form>
    <a class="button is-light" href="<?php echo htmlspecialchars($installerUrl . '?step=smtp', ENT_QUOTES, 'UTF-8'); ?>">Change SMTP settings</a>
</div>
