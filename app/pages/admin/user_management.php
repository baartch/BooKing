<?php
require_once __DIR__ . '/../../routes/auth/check.php';

// Deprecated: kept for backward compatibility.
// The admin panel entry point is now /app/pages/admin/index.php

header('Location: ' . BASE_PATH . '/app/pages/admin/index.php' . (
    isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
        ? ('?' . $_SERVER['QUERY_STRING'])
        : ''
));
exit;
