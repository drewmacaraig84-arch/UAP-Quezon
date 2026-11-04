<?php
require_once __DIR__ . '/../includes/config.php';

// Properly destroy the session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Clear session data
$_SESSION = [];

// Redirect to login (preserving timeout query param if idle timeout occurred)
$redirectUrl = BASE_URL . '/auth/login.php';
if (!empty($_GET['timeout'])) {
    $redirectUrl .= '?timeout=1';
}
header('Location: ' . $redirectUrl, true, 302);
exit;
