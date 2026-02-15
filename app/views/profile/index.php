<?php
?>
<?php renderPageStart('Profile', [
    'bodyClass' => 'is-flex is-flex-direction-column is-fullheight',
    'extraScripts' => [
        '<script type="module" src="' . BASE_PATH . '/app/public/js/tabs.js" defer></script>',
        '<script type="module" src="' . BASE_PATH . '/app/public/js/appearance.js" defer></script>'
    ]
]); ?>
      <section class="section">
        <div class="container is-fluid">
          <div class="level mb-4">
            <div class="level-left">
              <h1 class="title is-3">Profile</h1>
            </div>
          </div>

          <?php if ($notice): ?>
            <div class="notification"><?php echo htmlspecialchars($notice); ?></div>
          <?php endif; ?>

          <?php foreach ($errors as $error): ?>
            <div class="notification"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>

          <div class="tabs is-boxed" role="tablist">
            <ul>
              <li class="<?php echo $activeTab === 'venues' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="venues" role="tab" aria-selected="<?php echo $activeTab === 'venues' ? 'true' : 'false'; ?>">Venues</a>
              </li>
              <li class="<?php echo $activeTab === 'mailboxes' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="mailboxes" role="tab" aria-selected="<?php echo $activeTab === 'mailboxes' ? 'true' : 'false'; ?>">Mailboxes</a>
              </li>
              <li class="<?php echo $activeTab === 'appearance' ? 'is-active' : ''; ?>">
                <a href="#" data-tab="appearance" role="tab" aria-selected="<?php echo $activeTab === 'appearance' ? 'true' : 'false'; ?>">Appearance</a>
              </li>
            </ul>
          </div>

          <div class="tab-panel <?php echo $activeTab === 'venues' ? '' : 'is-hidden'; ?>" data-tab-panel="venues" role="tabpanel">
            <div class="columns is-multiline">
              <div class="column is-12">
                <div class="box">
                  <h2 class="title is-5">Venues List</h2>
                  <form method="POST" action="" class="columns is-multiline">
                    <?php renderCsrfField(); ?>
                    <input type="hidden" name="action" value="update_page_size">
                    <div class="column is-4">
                      <div class="field">
                        <label for="venues_page_size" class="label">Venues per page (25-500)</label>
                        <div class="control">
                          <input
                            type="number"
                            id="venues_page_size"
                            name="venues_page_size"
                            class="input"
                            min="<?php echo (int) $minPageSize; ?>"
                            max="<?php echo (int) $maxPageSize; ?>"
                            value="<?php echo (int) $currentPageSize; ?>"
                            required
                          >
                        </div>
                      </div>
                    </div>
                    <div class="column is-12">
                      <button type="submit" class="button is-primary">Update Page Size</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-panel <?php echo $activeTab === 'mailboxes' ? '' : 'is-hidden'; ?>" data-tab-panel="mailboxes" role="tabpanel">
            <div class="level mb-4">
              <div class="level-left">
                <h2 class="title is-4">Mailboxes</h2>
              </div>
              <div class="level-right">
                <a href="<?php echo BASE_PATH; ?>/app/controllers/profile/mailbox_form.php" class="button is-primary">Add Mailbox</a>
              </div>
            </div>

            <div class="box">
              <h3 class="title is-5">Configured Mailboxes</h3>
              <?php if (!$personalMailboxes): ?>
                <p>No mailboxes configured yet.</p>
              <?php else: ?>
                <div class="table-container">
                  <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                      <tr>
                        <th>Name</th>
                        <th>Display Name</th>
                        <th>IMAP Host</th>
                        <th>IMAP Port</th>
                        <th>IMAP User</th>
                        <th>IMAP Encryption</th>
                        <th>SMTP Host</th>
                        <th>SMTP Port</th>
                        <th>SMTP User</th>
                        <th>SMTP Encryption</th>
                        <th>Delete After Retrieve</th>
                        <th>Store Sent on Server</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($personalMailboxes as $mailbox): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($mailbox['name'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($mailbox['display_name'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($mailbox['imap_host'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($mailbox['imap_port'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($mailbox['imap_username'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars(strtoupper($mailbox['imap_encryption'] ?? '')); ?></td>
                          <td><?php echo htmlspecialchars($mailbox['smtp_host'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($mailbox['smtp_port'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($mailbox['smtp_username'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars(strtoupper($mailbox['smtp_encryption'] ?? '')); ?></td>
                          <td><?php echo !empty($mailbox['delete_after_retrieve']) ? 'Yes' : 'No'; ?></td>
                          <td><?php echo !empty($mailbox['store_sent_on_server']) ? 'Yes' : 'No'; ?></td>
                          <td>
                            <div class="buttons are-small">
                              <a href="<?php echo BASE_PATH; ?>/app/controllers/profile/mailbox_form.php?edit_mailbox_id=<?php echo (int) $mailbox['id']; ?>" class="button" aria-label="Edit mailbox" title="Edit mailbox">
                                <span class="icon"><i class="fa-solid fa-pen"></i></span>
                              </a>
                              <form method="POST" action="<?php echo BASE_PATH; ?>/app/controllers/profile/index.php?tab=mailboxes" onsubmit="return confirm('Delete this mailbox?');">
                                <?php renderCsrfField(); ?>
                                <input type="hidden" name="action" value="delete_mailbox">
                                <input type="hidden" name="mailbox_id" value="<?php echo (int) $mailbox['id']; ?>">
                                <button type="submit" class="button" aria-label="Delete mailbox" title="Delete mailbox">
                                  <span class="icon"><i class="fa-solid fa-trash"></i></span>
                                </button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="tab-panel <?php echo $activeTab === 'appearance' ? '' : 'is-hidden'; ?>" data-tab-panel="appearance" role="tabpanel">
            <div class="columns is-multiline">
              <div class="column is-12">
                <div class="box">
                  <h2 class="title is-5">Appearance</h2>
                  <div class="field">
                    <label class="label" for="appearance_theme">Theme</label>
                    <div class="control">
                      <div class="select">
                        <select id="appearance_theme" name="appearance_theme" data-appearance-theme>
                          <option value="system">System</option>
                          <option value="light">Light</option>
                          <option value="dark">Dark</option>
                        </select>
                      </div>
                    </div>
                    <p class="help">Choose a theme preference for this browser.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </section>
<?php renderPageEnd(); ?>
