<?php
require_once __DIR__ . '/../../models/auth/check.php';
require_once __DIR__ . '/../../models/core/database.php';
require_once __DIR__ . '/../../models/core/layout.php';
require_once __DIR__ . '/../../models/core/settings.php';

$settings = loadSettingValues(['mapbox_api_key_public']);
$mapboxToken = (string) ($settings['mapbox_api_key_public'] ?? '');

require __DIR__ . '/../../views/map/index.php';
