<?php
require_once __DIR__ . '/../../routes/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/layout.php';
require_once __DIR__ . '/../../models/core/settings.php';

$settings = loadSettingValues(['mapbox_api_key']);
$mapboxToken = (string) ($settings['mapbox_api_key'] ?? '');

require __DIR__ . '/../../views/map/index.php';
