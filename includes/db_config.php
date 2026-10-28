<?php
/**
 * ==============================================================================
 * UAP Quezon Chapter - Direct Database & Hosting Configuration
 * ==============================================================================
 * 
 * This file allows you to set your database connection parameters directly,
 * which is especially helpful on shared hosting like InfinityFree, 000webhost,
 * or cPanel where .env files might be hidden or blocked by the web server.
 * 
 * HOW TO CONFIGURE FOR INFINITYFREE:
 * 1. Log in to your InfinityFree Account -> Client Area -> Manage -> vPanel.
 * 2. Click "MySQL Databases".
 * 3. Note down the credentials displayed:
 *      - MySQL Host Name (e.g. sql100.infinityfree.com or sql200.epizy.com)
 *      - MySQL Database Name (e.g. if0_12345678_uap_quezon)
 *      - MySQL User Name (e.g. if0_12345678)
 *      - MySQL Password (your InfinityFree vPanel password)
 * 4. Fill them into the constants below.
 */

// If credentials are not already set via environment variables or .env file,
// you can define them here:

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: (isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : '127.0.0.1'));
}

if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ? (int)getenv('DB_PORT') : (isset($_ENV['DB_PORT']) ? (int)$_ENV['DB_PORT'] : 3306));
}

if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: (isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : 'uap_quezon'));
}

if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: (isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'root'));
}

if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : (isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : ''));
}

// Set to true only if you have Redis running (keep false on shared hosting like InfinityFree)
if (!defined('ENABLE_REDIS')) {
    define('ENABLE_REDIS', (bool)(getenv('ENABLE_REDIS') ?: (isset($_ENV['ENABLE_REDIS']) ? $_ENV['ENABLE_REDIS'] : false)));
}
