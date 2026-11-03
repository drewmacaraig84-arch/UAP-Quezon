<?php
/**
 * Direct entry point for /profile/?id=1 or /profile/1
 * Eliminates server 404s by providing a physical folder & script.
 */
require_once __DIR__ . '/../includes/config.php';

$id = 0;
if (!empty($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
} else {
    // Parse from PATH_INFO or REQUEST_URI e.g. /profile/1 or /profile/1/
    $uri = $_SERVER['PATH_INFO'] ?? ($_SERVER['REQUEST_URI'] ?? '');
    if (preg_match('#/profile/([0-9]+)#i', $uri, $m)) {
        $id = (int)$m[1];
    }
}

if ($id > 0) {
    $_GET['id'] = $id;
    require __DIR__ . '/../public/member_profile.php';
    exit;
}

// Fallback to Architects Directory
require __DIR__ . '/../public/architects.php';
exit;

