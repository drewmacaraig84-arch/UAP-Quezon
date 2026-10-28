<?php
function load_env_file() {
    $envFile = dirname(__DIR__) . '/.env';

    if (!is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || substr($line, 0, 1) === '#') {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === '') {
            continue;
        }

        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

load_env_file();

$env = function ($keys, $default = null) {
    if (!is_array($keys)) {
        $keys = [$keys];
    }
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
    }
    return $default;
};

// Auto-detect connection from URL strings (Railway, Heroku, Render, etc.)
$databaseUrl = $env(['DATABASE_URL', 'MYSQL_URL', 'MYSQL_PUBLIC_URL', 'JAWSDB_URL', 'CLEARDB_DATABASE_URL']);
$urlHost = null;
$urlPort = null;
$urlUser = null;
$urlPass = null;
$urlName = null;

if (!empty($databaseUrl)) {
    $dbParts = parse_url($databaseUrl);
    if ($dbParts !== false) {
        $urlHost = $dbParts['host'] ?? null;
        $urlPort = isset($dbParts['port']) ? (int) $dbParts['port'] : null;
        $urlUser = isset($dbParts['user']) ? urldecode($dbParts['user']) : null;
        $urlPass = isset($dbParts['pass']) ? urldecode($dbParts['pass']) : null;
        $urlName = isset($dbParts['path']) ? ltrim($dbParts['path'], '/') : null;
    }
}

// Load direct database config file if present (useful for InfinityFree / cPanel / Shared Hosting)
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
}

// ===== DATABASE CONFIGURATION =====
// Supports standard .env, Railway (MYSQLHOST, MYSQLPORT, etc.), and local Laragon defaults.
if (!defined('DB_HOST')) {
    define('DB_HOST', $urlHost ?? $env(['DB_HOST', 'MYSQLHOST', 'MYSQL_HOST'], '127.0.0.1'));
}
if (!defined('DB_PORT')) {
    define('DB_PORT', $urlPort ?? (int) $env(['DB_PORT', 'MYSQLPORT', 'MYSQL_PORT'], 3306));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', $urlName ?? $env(['DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE'], 'uap_quezon'));
}
if (!defined('DB_USER')) {
    define('DB_USER', $urlUser ?? $env(['DB_USER', 'MYSQLUSER', 'MYSQL_USER'], 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', $urlPass ?? $env(['DB_PASS', 'DB_PASSWORD', 'MYSQLPASSWORD', 'MYSQL_PASSWORD'], ''));
}
if (!defined('REDIS_HOST')) {
    define('REDIS_HOST', $env(['REDIS_HOST', 'REDISHOST'], '127.0.0.1'));
}
if (!defined('REDIS_PORT')) {
    define('REDIS_PORT', (int) $env(['REDIS_PORT', 'REDISPORT'], 6379));
}
if (!defined('REDIS_DB')) {
    define('REDIS_DB', (int) $env(['REDIS_DB', 'REDISDB'], 0));
}
if (!defined('REDIS_PASSWORD')) {
    define('REDIS_PASSWORD', $env(['REDIS_PASSWORD', 'REDISPASSWORD', 'REDIS_AUTH'], ''));
}
if (!defined('ENABLE_REDIS')) {
    define('ENABLE_REDIS', (bool) $env('ENABLE_REDIS', false));
}
if (!defined('CACHE_TTL')) {
    define('CACHE_TTL', (int) $env('CACHE_TTL', 300));
}

if (!defined('BASE_URL_OVERRIDE') && $env('APP_BASE_URL') !== null) {
    define('BASE_URL_OVERRIDE', $env('APP_BASE_URL'));
}
// ===================================

function redis_raw_command($socket, array $args) {
    $payload = '*' . count($args) . "\r\n";
    foreach ($args as $arg) {
        $argValue = (string) $arg;
        $payload .= '$' . strlen($argValue) . "\r\n" . $argValue . "\r\n";
    }

    $written = fwrite($socket, $payload);
    if ($written === false || $written === 0) {
        return null;
    }

    $line = fgets($socket, 8192);
    if ($line === false) {
        return null;
    }

    $prefix = substr($line, 0, 1);
    $data = trim($line);

    if ($prefix === '+') {
        return substr($data, 1);
    }

    if ($prefix === '-') {
        throw new RuntimeException(substr($data, 1));
    }

    if ($prefix === ':') {
        return (int) substr($data, 1);
    }

    if ($prefix === '$') {
        $length = (int) substr($data, 1);
        if ($length === -1) {
            return null;
        }

        $body = '';
        while (strlen($body) < $length) {
            $chunk = fread($socket, $length - strlen($body));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        fread($socket, 2);
        return $body;
    }

    if ($prefix === '*') {
        $count = (int) substr($data, 1);
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = redis_raw_command($socket, []);
        }
        return $items;
    }

    return $data;
}

function redis_client() {
    static $client = null;

    if ($client !== null) {
        return $client;
    }

    $client = false;

    // Skip Redis on shared hosting like InfinityFree to eliminate connection latency
    if (!defined('ENABLE_REDIS') || !ENABLE_REDIS) {
        return false;
    }

    try {
        $socket = @fsockopen(REDIS_HOST, REDIS_PORT, $errno, $errstr, 2);
        if ($socket === false) {
            return false;
        }

        stream_set_timeout($socket, 2);

        if (REDIS_PASSWORD !== '') {
            $authResult = redis_raw_command($socket, ['AUTH', REDIS_PASSWORD]);
            if ($authResult === null || $authResult === false) {
                fclose($socket);
                return false;
            }
        }

        if (REDIS_DB > 0) {
            $selectResult = redis_raw_command($socket, ['SELECT', REDIS_DB]);
            if ($selectResult === null || $selectResult === false) {
                fclose($socket);
                return false;
            }
        }

        $client = ['socket' => $socket];
    } catch (Throwable $e) {
        $client = false;
    }

    return $client;
}

function cache_get($key, $default = null) {
    $redis = redis_client();
    if ($redis === false) {
        return $default;
    }

    try {
        $socket = $redis['socket'];
        $value = redis_raw_command($socket, ['GET', $key]);
        if ($value === false || $value === null) {
            return $default;
        }
        $decoded = @unserialize($value);
        return $decoded !== false ? $decoded : $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function cache_set($key, $value, $ttl = CACHE_TTL) {
    $redis = redis_client();
    if ($redis === false) {
        return false;
    }

    try {
        $socket = $redis['socket'];
        $payload = serialize($value);
        $result = redis_raw_command($socket, ['SET', $key, $payload, 'EX', (string) (int) $ttl]);
        return $result === 'OK';
    } catch (Throwable $e) {
        return false;
    }
}

function cache_delete($key) {
    $redis = redis_client();
    if ($redis === false) {
        return false;
    }

    try {
        $socket = $redis['socket'];
        $result = redis_raw_command($socket, ['DEL', $key]);
        return (int) $result > 0;
    } catch (Throwable $e) {
        return false;
    }
}

$pdo = null;
$pdoError = null;

try {
    $dbPortStr = defined('DB_PORT') && DB_PORT ? ';port=' . DB_PORT : '';
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . $dbPortStr . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"
        ]
    );
} catch (PDOException $e) {
    $pdoError = $e->getMessage();
    
    // Auto-discover candidate database names on shared hosts like InfinityFree if name or casing differed
    if (str_contains($e->getMessage(), '1044') || str_contains($e->getMessage(), '1049') || (int)$e->getCode() === 1044 || (int)$e->getCode() === 1049) {
        $candidateDbs = array_unique([
            'if0_43293362_Uap_qc',
            'if0_43293362_uap_qc',
            (defined('DB_USER') ? DB_USER : '') . '_Uap_qc',
            (defined('DB_USER') ? DB_USER : '') . '_uap_qc'
        ]);
        foreach ($candidateDbs as $cand) {
            if (!$cand || $cand === DB_NAME) continue;
            try {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . $dbPortStr . ";dbname=" . $cand . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"
                    ]
                );
                $pdoError = null;
                break;
            } catch (Throwable $eCand) {}
        }
    }

    if ($pdo === null) {
        if (php_sapi_name() === 'cli') {
            if (!defined('CONFIG_IGNORE_DB_FAILURE')) {
                throw $e;
            }
        } else {
            $isSharedHost = defined('IS_HOSTED') && IS_HOSTED;
            $msg = "Database connection failed: " . htmlspecialchars($e->getMessage());
            if ($isSharedHost && (str_contains($e->getMessage(), '1044') || str_contains($e->getMessage(), '1049'))) {
                $msg .= "<br><br><div style='font-family:sans-serif; background:#fef2f2; border:1px solid #ef4444; border-radius:8px; padding:16px; color:#991b1b; max-width:600px;'>";
                $msg .= "<strong>Database Setup Required on InfinityFree:</strong><br>";
                $msg .= "The database could not be accessed. If you have not created a database yet:<br>";
                $msg .= "<ol style='margin-top:8px;'>";
                $msg .= "<li>Open your InfinityFree <strong>vPanel</strong> &rarr; <strong>MySQL Databases</strong>.</li>";
                $msg .= "<li>In the <strong>Create Database</strong> box, type a database name (e.g. <code>uap_qc</code>) and click Create.</li>";
                $msg .= "<li>Click <strong>phpMyAdmin</strong> next to your new database and import <code>schema.sql</code>.</li>";
                $msg .= "</ol>";
                $msg .= "<a href='db_test.php' style='color:#b91c1c; font-weight:bold; text-decoration:underline;'>Click here to run the Database Diagnostic tool &rarr;</a>";
                $msg .= "</div>";
            }
            die($msg);
        }
    }
}

// Auto-initialize tables from schema.sql if database exists but tables haven't been created yet
if ($pdo instanceof PDO) {
    try {
        $userCheck = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if (!$userCheck) {
            $schemaPath = dirname(__DIR__) . '/schema.sql';
            if (file_exists($schemaPath)) {
                $rawSql = file_get_contents($schemaPath);
                $cleanSql = '';
                foreach (explode("\n", $rawSql) as $line) {
                    $t = trim($line);
                    if ($t !== '' && !str_starts_with($t, '--') && !str_starts_with($t, '/*')) {
                        $cleanSql .= $line . "\n";
                    }
                }
                foreach (array_filter(array_map('trim', explode(';', $cleanSql))) as $stmt) {
                    if ($stmt) {
                        try {
                            $pdo->exec($stmt);
                        } catch (Throwable $ignoreStmt) {}
                    }
                }
            }
        }
    } catch (Throwable $ignoreSchema) {}
}

// Ensure member_attendance exists if tables were previously created
if ($pdo instanceof PDO) {
    try {
        $attCheck = $pdo->query("SHOW TABLES LIKE 'member_attendance'")->fetch();
        if (!$attCheck) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS member_attendance (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    fiscal_year VARCHAR(20) NOT NULL DEFAULT '2026-2027',
                    month_july VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_august VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_september VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_october VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_november VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_december VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_january VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_february VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_march VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_april VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_may VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_june VARCHAR(20) NOT NULL DEFAULT 'absent',
                    event_induction TINYINT(1) NOT NULL DEFAULT 0,
                    event_anniversary TINYINT(1) NOT NULL DEFAULT 0,
                    event_christmas_party TINYINT(1) NOT NULL DEFAULT 0,
                    event_wad TINYINT(1) NOT NULL DEFAULT 0,
                    event_nca TINYINT(1) NOT NULL DEFAULT 0,
                    event_pafnam TINYINT(1) NOT NULL DEFAULT 0,
                    event_natcon TINYINT(1) NOT NULL DEFAULT 0,
                    event_summer_outing TINYINT(1) NOT NULL DEFAULT 0,
                    event_csr TINYINT(1) NOT NULL DEFAULT 0,
                    total_f2f INT NOT NULL DEFAULT 0,
                    total_zoom INT NOT NULL DEFAULT 0,
                    total_attended INT NOT NULL DEFAULT 0,
                    activity_status ENUM('active','inactive','probationary') NOT NULL DEFAULT 'inactive',
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_user_fy (user_id, fiscal_year),
                    KEY idx_user_id (user_id),
                    KEY idx_activity_status (activity_status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Throwable $e) {}
}

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    @session_start();
}

// Detect the app's base URL so it works on Laragon virtual hosts and subfolders.
$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

if (php_sapi_name() === 'cli' || empty($scriptName) || preg_match('/^[a-zA-Z]:/i', $scriptName)) {
    $baseUrl = '';
} else {
    $scriptDir   = $scriptFilename ? str_replace('\\', '/', dirname(realpath($scriptFilename))) : '';
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $relative    = $scriptDir ? ltrim(str_ireplace($projectRoot, '', $scriptDir), '/') : '';
    $depth       = ($relative === '') ? 0 : substr_count($relative, '/') + 1;

    $baseUrl = $scriptName ? str_replace('\\', '/', dirname($scriptName)) : '';
    for ($i = 0; $i < $depth; $i++) {
        $baseUrl = dirname($baseUrl);
    }
    if ($baseUrl === '/' || $baseUrl === '\\' || $baseUrl === '.' || empty($baseUrl) || preg_match('/^[a-zA-Z]:/i', $baseUrl)) {
        $baseUrl = '';
    }
}

define('BASE_URL', defined('BASE_URL_OVERRIDE') ? BASE_URL_OVERRIDE : $baseUrl);

// ==============================================================================
// HOSTED VS LOCAL ENVIRONMENT HANDLER & MEDIA URL RESOLVER
// ==============================================================================
if (!defined('IS_HOSTED')) {
    $isRailway = !empty(getenv('RAILWAY_ENVIRONMENT')) || !empty(getenv('RAILWAY_PROJECT_ID')) || !empty(getenv('RAILWAY_STATIC_URL'));
    $isDocker  = file_exists('/.dockerenv') || file_exists('/run/.containerenv');
    $httpHost  = $_SERVER['HTTP_HOST'] ?? '';
    $isCloud   = (!empty($httpHost) && (
        str_contains($httpHost, 'railway.app') || 
        str_contains($httpHost, 'render.com') || 
        str_contains($httpHost, 'fly.dev') ||
        str_contains($httpHost, 'infinityfreeapp.com') ||
        str_contains($httpHost, 'epizy.com') ||
        str_contains($httpHost, 'rf.gd') ||
        str_contains($httpHost, 'byetcluster.com')
    ));
    define('IS_HOSTED', $isRailway || $isDocker || $isCloud);
    define('IS_LOCAL', !IS_HOSTED);
}

function is_hosted() {
    return defined('IS_HOSTED') && IS_HOSTED;
}

function is_local() {
    return !is_hosted();
}

/**
 * Returns absolute public URL scheme and host (e.g. https://uapmc-production.up.railway.app)
 * Used for generating scannable QR codes and full public link URLs.
 */
function get_public_base_url() {
    // 1. Check APP_BASE_URL or BASE_URL_OVERRIDE
    if (defined('BASE_URL_OVERRIDE') && !empty(BASE_URL_OVERRIDE) && str_starts_with(BASE_URL_OVERRIDE, 'http')) {
        return rtrim(BASE_URL_OVERRIDE, '/');
    }
    $appUrl = getenv('APP_BASE_URL');
    if (!empty($appUrl) && str_starts_with($appUrl, 'http')) {
        return rtrim($appUrl, '/');
    }
    
    // 2. Check HTTP Host from active web request
    if (!empty($_SERVER['HTTP_HOST'])) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') 
                 || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) 
                 ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        
        // In local development (Laragon)
        if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
            $base = defined('BASE_URL') && BASE_URL !== '' ? BASE_URL : (str_contains($_SERVER['REQUEST_URI'] ?? '', '/UAP-Quezon') ? '/UAP-Quezon' : '');
            return rtrim($proto . $host . $base, '/');
        }
        
        // In production (e.g. uapmc-production.up.railway.app)
        return rtrim($proto . $host, '/');
    }

    // 3. Check Railway / Cloud public domain env vars
    $railwayDomain = getenv('RAILWAY_PUBLIC_DOMAIN');
    if (!empty($railwayDomain)) {
        return 'https://' . rtrim($railwayDomain, '/');
    }
    $railwayStatic = getenv('RAILWAY_STATIC_URL');
    if (!empty($railwayStatic)) {
        return 'https://' . rtrim($railwayStatic, '/');
    }
    
    // 4. Default hosted fallback for UAP Mindoro production
    if (defined('IS_HOSTED') && IS_HOSTED) {
        return 'https://uapmc-production.up.railway.app';
    }
    
    return 'http://localhost/UAP-Quezon';
}

/**
 * Resolves a media file path on disk, checking multiple fallback locations.
 */
function resolve_media_filesystem_path($relativePath) {
    if (empty($relativePath)) return null;
    $cleanPath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $root = str_replace('\\', '/', dirname(__DIR__));
    
    if (file_exists($root . '/' . $cleanPath)) {
        return $root . '/' . $cleanPath;
    }
    
    $filename = basename($cleanPath);
    $candidates = [
        $root . '/uploads/' . $filename,
        $root . '/uploads/qr_codes/' . $filename,
        $root . '/uploads/avatars/' . $filename,
        $root . '/uploads/members/' . $filename,
        $root . '/uploads/sponsors/' . $filename,
        $root . '/uploads/news/' . $filename,
        $root . '/uploads/receipts/' . $filename,
        $root . '/uploads/home_images/' . $filename,
        $root . '/uploads/proofs/' . $filename,
        $root . '/public/' . $filename,
    ];
    
    foreach ($candidates as $cand) {
        if (file_exists($cand)) {
            return $cand;
        }
    }
    return null;
}

/**
 * Generates the correct web URL for any media asset (QR code, avatar, proof, logo, sponsor).
 * Automatically prepends BASE_URL, checks alternate directories if needed, and applies fallback.
 */
function media_url($path, $fallback = '') {
    if (empty($path)) {
        return $fallback ? (str_starts_with($fallback, 'http') ? $fallback : (BASE_URL !== '' ? BASE_URL : '') . '/' . ltrim($fallback, '/')) : '';
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
        return $path;
    }
    
    $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
    $root = str_replace('\\', '/', dirname(__DIR__));
    $prefix = (BASE_URL !== '' ? rtrim(BASE_URL, '/') : '');
    
    if (file_exists($root . '/' . $cleanPath)) {
        return $prefix . '/' . $cleanPath;
    }
    
    $fsPath = resolve_media_filesystem_path($cleanPath);
    if ($fsPath) {
        $cleanFs = str_replace('\\', '/', $fsPath);
        $foundRel = ltrim(str_ireplace($root, '', $cleanFs), '/');
        return $prefix . '/' . $foundRel;
    }
    
    if ($fallback && !file_exists($root . '/' . $cleanPath)) {
        return str_starts_with($fallback, 'http') ? $fallback : $prefix . '/' . ltrim($fallback, '/');
    }
    return $prefix . '/' . $cleanPath;
}

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/dues_service.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/soft_delete.php';

if (!function_exists('is_super_admin')) {
    function is_super_admin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
    }
}

if (!function_exists('is_admin_or_super')) {
    function is_admin_or_super() {
        return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'], true);
    }
}

if (!function_exists('get_site_setting')) {
    function get_site_setting(PDO $pdo, $key, $default = '') {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return ($val !== false && $val !== null) ? $val : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}




