<?php
/**
 * UAP-Quezon Application Router
 * Directs visitors to the React website by default, or logged-in users to their portal dashboard.
 */
require_once __DIR__ . '/includes/config.php';

if (isset($_SESSION['user_id']) && !isset($_GET['home'])) {
    if (in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    } else {
        header('Location: ' . BASE_URL . '/member/dashboard.php');
        exit;
    }
}

// Serve the React Website index.html if it exists
if (file_exists(__DIR__ . '/index.html')) {
    include __DIR__ . '/index.html';
    exit;
}

// Fallback to public homepage if React bundle is not compiled into root
if (file_exists(__DIR__ . '/public/homepage.php')) {
    include __DIR__ . '/public/homepage.php';
    exit;
}

// Fallback to login
header('Location: ' . (BASE_URL ?: '') . '/auth/login.php');
exit;

