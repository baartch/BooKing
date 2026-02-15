<?php
?>
<?php renderPageStart('Mailbox', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/app/public/js/mailboxes.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <h1 class="title is-3"><?php echo $editMailbox ? 'Edit Mailbox' : 'Add Mailbox'; ?></h1>
            </div>
            <div class="level-right">
              <a href="<?php echo BASE_PATH; ?>/app/controllers/team/index.php?tab=mailboxes" class="button">Back to Mailboxes</a>
            </div>
          </div>

          <?php if ($notice): ?>
            <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <div class="box">
            <form method="POST" action="" class="columns is-multiline">
              <?php renderCsrfField(); ?>
              <input type="hidden" name="action" value="<?php echo $editMailbox ? 'update_mailbox' : 'create_mailbox'; ?>">
              <?php if ($editMailbox): ?>
                <input type="hidden" name="mailbox_id" value="<?php echo (int) $editMailbox['id']; ?>">
              <?php endif; ?>

              <?php require __DIR__ . '/../../partials/mailbox_form_fields.php'; ?>

              <div class="column is-12">
                <div class="buttons">
                  <button type="submit" class="button is-primary"><?php echo $editMailbox ? 'Update Mailbox' : 'Create Mailbox'; ?></button>
                  <a href="<?php echo BASE_PATH; ?>/app/controllers/team/index.php?tab=mailboxes" class="button">Cancel</a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </section>
<?php renderPageEnd(); ?>
