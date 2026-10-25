<?php
/**
 * Installer page shell. Expects from the controller:
 *   $step, $errors, $notice, $installerUrl, $lock
 *   plus $extResults/$envResults on the welcome step.
 */

$stepLabels = [
    'welcome' => 'Requirements',
    'database' => 'Database',
    'admin' => 'Admin',
    'smtp' => 'SMTP',
    'otp' => 'Verify',
    'keys' => 'Extras',
    'done' => 'Done'
];
$stepKeys = array_keys($stepLabels);
$currentStepIndex = array_search($step, $stepKeys, true);
$csrfToken = getInstallerCsrfToken();
if ($currentStepIndex === false) {
    $currentStepIndex = 0;
}
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
                <div class="column is-6">
                    <div class="box">
                        <h1 class="title is-3">BooKing</h1>
                        <p class="subtitle is-6">Installation wizard</p>

                        <nav class="level" aria-label="Installation steps">
                            <?php foreach ($stepLabels as $stepKey => $stepLabel): ?>
                                <?php $stepPosition = array_search($stepKey, $stepKeys, true); ?>
                                <div class="level-item has-text-centered">
                                    <div>
                                        <span class="tag is-medium <?php echo $stepKey === $step ? 'is-link' : ($currentStepIndex > $stepPosition ? 'is-success' : 'is-light'); ?>">
                                            <?php echo htmlspecialchars($stepLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </nav>

                        <?php if ($notice !== ''): ?>
                            <div class="notification is-success"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <?php if ($errors): ?>
                            <div class="notification is-danger">
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php require __DIR__ . '/steps/' . $step . '.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
