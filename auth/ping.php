<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    echo json_encode([
        'success'   => true,
        'user_id'   => (int)$_SESSION['user_id'],
        'timestamp' => time(),
    ]);
} else {
    echo json_encode([
        'success'    => false,
        'logged_out' => true,
    ]);
}
exit;

