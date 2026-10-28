<?php
/**
 * ==============================================================================
 * UAP Quezon Chapter - Dual-Environment Database & Hosting Configuration
 * ==============================================================================
 * Auto-detects whether the application is running locally (Laragon / XAMPP)
 * or on the live InfinityFree hosting server (uap-quezonchapter.page.gd).
 */

$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isInfinityFree = (
    str_contains($httpHost, 'infinityfree') || 
    str_contains($httpHost, 'epizy') || 
    str_contains($httpHost, 'page.gd') || 
    str_contains($httpHost, 'rf.gd') ||
    str_contains(__DIR__, 'if0_') ||
    str_contains(__DIR__, 'epiz_') ||
    str_contains(__DIR__, '/htdocs')
);

if ($isInfinityFree) {
    // ==========================================================================
    // 1. LIVE INFINITYFREE HOSTING CREDENTIALS
    // ==========================================================================
    if (!defined('DB_HOST')) {
        define('DB_HOST', getenv('DB_HOST') ?: 'sql108.infinityfree.com');
    }
    if (!defined('DB_PORT')) {
        define('DB_PORT', getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306);
    }
    if (!defined('DB_USER')) {
        define('DB_USER', getenv('DB_USER') ?: 'if0_43293362');
    }
    if (!defined('DB_PASS')) {
        define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'xDdiuKOGkEJ4O');
    }
    if (!defined('DB_NAME')) {
        define('DB_NAME', getenv('DB_NAME') ?: 'if0_43293362_uap_qc');
    }
    if (!defined('ENABLE_REDIS')) {
        define('ENABLE_REDIS', false);
    }
} else {
    // ==========================================================================
    // 2. LOCAL ENVIRONMENT (Laragon / XAMPP)
    // ==========================================================================
    if (!defined('DB_HOST')) {
        define('DB_HOST', getenv('DB_HOST') ?: (isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : '127.0.0.1'));
    }
    if (!defined('DB_PORT')) {
        define('DB_PORT', getenv('DB_PORT') ? (int)getenv('DB_PORT') : (isset($_ENV['DB_PORT']) ? (int)$_ENV['DB_PORT'] : 3306));
    }
    if (!defined('DB_USER')) {
        define('DB_USER', getenv('DB_USER') ?: (isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'root'));
    }
    if (!defined('DB_PASS')) {
        define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : (isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : ''));
    }
    if (!defined('DB_NAME')) {
        define('DB_NAME', getenv('DB_NAME') ?: (isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : 'uap_quezon'));
    }
    if (!defined('ENABLE_REDIS')) {
        define('ENABLE_REDIS', false);
    }
}
