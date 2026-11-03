<?php
/**
 * Flash Message Helper
 */

function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function get_flash_messages() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function display_flash() {
    $messages = get_flash_messages();
    if (empty($messages)) {
        return;
    }
    
    foreach ($messages as $msg) {
        $alertClass = 'alert-info';
        $iconName = 'info';
        if ($msg['type'] === 'success') { $alertClass = 'alert-success'; $iconName = 'check'; }
        if ($msg['type'] === 'error' || $msg['type'] === 'danger') { $alertClass = 'alert-error'; $iconName = 'alert'; }
        if ($msg['type'] === 'warning') { $alertClass = 'alert-warning'; $iconName = 'alert'; }
        
        echo '<div class="alert ' . htmlspecialchars($alertClass, ENT_QUOTES, 'UTF-8') . '" style="margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">';
        if (function_exists('icon')) {
            echo icon($iconName, '', 18);
        }
        echo '<div>' . htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div>';
    }
}
