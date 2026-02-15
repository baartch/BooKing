<?php
?>
<?php renderPageStart('Login', ['includeSidebar' => false, 'bodyClass' => 'is-flex is-flex-direction-column is-fullheight']); ?>
  <section class="section is-flex is-flex-grow-1 is-align-items-center">
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-4">
          <div class="box">
            <h1 class="title is-3">BooKing</h1>
            <p class="subtitle is-6">Please login to access the app.</p>
            <p class="help">You will receive a one-time code by email.</p>

            <?php if ($notice): ?>
              <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
              <div class="notification"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($step === 'otp' && $email !== ''): ?>
              <form method="POST" action="<?php echo BASE_PATH; ?>/app/routes/auth/otp_verify.php">
                <?php renderCsrfField(); ?>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <div class="field">
                  <label for="otp_code" class="label">One-time code</label>
                  <div class="control">
                    <input type="text" id="otp_code" name="otp_code" class="input" inputmode="numeric" autocomplete="one-time-code" maxlength="8" pattern="\d{8}" required autofocus>
                  </div>
                  <p class="help">Enter the 8-digit code sent to your email. Code expires in 10 minutes.</p>
                </div>

                <div class="field">
                  <div class="control">
                    <button type="submit" class="button is-fullwidth">Verify code</button>
                  </div>
                </div>
              </form>

              <form method="POST" action="<?php echo BASE_PATH; ?>/app/routes/auth/otp_request.php" class="mt-2">
                <?php renderCsrfField(); ?>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <button type="submit" class="button is-text is-small">Send a new code</button>
              </form>
            <?php else: ?>
              <form method="POST" action="<?php echo BASE_PATH; ?>/app/routes/auth/otp_request.php">
                <?php renderCsrfField(); ?>
                <div class="field">
                  <label for="email" class="label">Email address</label>
                  <div class="control">
                    <input type="email" id="email" name="email" class="input" required autofocus>
                  </div>
                  <p class="help">Enter your email to receive a one-time code.</p>
                </div>

                <div class="field">
                  <div class="control">
                    <button type="submit" class="button is-fullwidth">Send code</button>
                  </div>
                </div>
              </form>
            <?php endif; ?>

            <p>Keep your Booking organized.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
<?php renderPageEnd(['includeSidebar' => false]); ?>
