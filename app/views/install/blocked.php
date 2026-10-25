<?php
/**
 * Installer blocked page. Expects: $blockReason ('locked' | 'completed' | 'invalid' | 'existing').
 */

$messages = [
    'locked' => [
        'title' => 'Installation already in progress',
        'body' => 'Setup was started in another browser session and is locked to it. '
            . 'If that session was lost (browser closed, cookies deleted), remove the file '
            . '<code>config/installer.lock</code> from the server via FTP and reload this page to restart. '
            . 'See README.md ("Setup") for details.'
    ],
    'unwritable' => [
        'title' => 'config/ directory is not writable',
        'body' => 'The installer needs to create <code>config/installer.lock</code>, but PHP cannot '
            . 'write to the <code>config/</code> directory. Fix the permissions via FTP (set the '
            . 'directory writable for the web server user, e.g. 755 or 775) and reload this page.'
    ],
    'completed' => [
        'title' => 'Installation already completed',
        'body' => 'BooKing is installed and the installer is permanently disabled. '
            . 'For security, delete <code>install.php</code> from the server via FTP. '
            . 'Removing <code>config/installer.lock</code> will not re-enable the installer for an existing installation.'
    ],
    'invalid' => [
        'title' => 'Installer lock unreadable',
        'body' => 'The file <code>config/installer.lock</code> exists but cannot be read. '
            . 'Remove it via FTP and reload this page to restart setup. See README.md ("Setup").'
    ],
    'existing' => [
        'title' => 'BooKing is already installed',
        'body' => 'The file <code>config/config.php</code> already exists, so this installation is '
            . 'already configured. The installer refuses to run to protect the existing installation.'
    ]
];

$message = $messages[$blockReason] ?? $messages['invalid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BooKing Installation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
</head>
<body class="has-background-light">
    <section class="section is-flex is-flex-grow-1 is-align-items-center">
        <div class="container">
            <div class="columns is-centered">
                <div class="column is-5">
                    <div class="box has-text-centered">
                        <h1 class="title is-4"><?php echo htmlspecialchars($message['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p><?php echo $message['body']; ?></p>
                        <a class="button is-link mt-4" href="<?php echo htmlspecialchars(BASE_PATH . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">Go to BooKing</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
