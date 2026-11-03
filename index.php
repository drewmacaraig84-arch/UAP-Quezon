<?php
/**
 * UAP-Quezon Application Router
 * Serves the public homepage / React website by default for both guests and authenticated users.
 * Allows members/admins to browse the public website in one tab while keeping their portal active in another.
 */
require_once __DIR__ . '/includes/config.php';

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
