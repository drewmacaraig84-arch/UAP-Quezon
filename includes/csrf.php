<?php
/**
 * CSRF Protection Helper
 */

function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_token() {
    return generate_csrf_token();
}

function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function validate_csrf_token($token = null) {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    
    $token = $token ?? ($_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));
    
    if (empty($_SESSION['_csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['_csrf_token'], $token);
}

function require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validate_csrf_token()) {
            http_response_code(403);
            die('Invalid or expired security token. Please go back, refresh the page, and try again.');
        }
    }
}
