<?php
// Shared bootstrap for admin routes
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../src-php/auth/admin_check.php';
require_once __DIR__ . '/../../src-php/core/database.php';
require_once __DIR__ . '/../../src-php/core/settings.php';
require_once __DIR__ . '/../../src-php/communication/mailbox_helpers.php';
require_once __DIR__ . '/../../src-php/core/layout.php';

$errors = [];
$notice = '';

$activeTab = $_GET['tab'] ?? 'users';
$validTabs = ['users', 'teams', 'api-keys', 'smtp', 'logs'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'users';
}
