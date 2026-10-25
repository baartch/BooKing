<?php
/**
 * Welcome / requirements step.
 * Expects: $extResults, $envResults (lists of ['label' => string, 'ok' => bool]).
 */

$requirementLabels = [
    'php_version' => 'PHP 8.0 or higher',
    'pdo' => 'PHP extension: PDO',
    'pdo_mysql' => 'PHP extension: pdo_mysql',
    'openssl' => 'PHP extension: openssl',
    'mbstring' => 'PHP extension: mbstring',
    'imap' => 'PHP extension: imap',
    'https' => 'HTTPS connection',
    'config_writable' => 'config/ directory writable',
    'schema_readable' => 'sql/schema.sql present',
    'compiled_assets' => 'Compiled JS assets present'
];
?>
<p class="mb-4">This wizard sets up BooKing: database connection, configuration file, your admin account and the mail server used for login codes.</p>

<table class="table is-fullwidth is-striped is-hoverable">
    <tbody>
        <?php foreach (array_merge($extResults, $envResults) as $result): ?>
            <tr>
                <td>
                    <span class="icon-text">
                        <span class="icon has-text-<?php echo $result['ok'] ? 'success' : 'danger'; ?>">
                            <?php echo $result['ok'] ? '✔' : '✘'; ?>
                        </span>
                        <span><?php echo htmlspecialchars($requirementLabels[$result['label']] ?? $result['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<form method="post" action="<?php echo htmlspecialchars($installerUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="start">
    <button type="submit" class="button is-primary is-fullwidth">Start installation</button>
</form>
