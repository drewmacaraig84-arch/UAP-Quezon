<?php
/**
 * UAP Quezon - System Crash & Error Logger
 * Captures uncaught exceptions, fatal errors, warnings, and system diagnostics
 */

if (!defined('LOGGER_INITIALIZED')) {
    define('LOGGER_INITIALIZED', true);

    function get_client_ip_address() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Record a system error/crash into database and log file
     */
    function log_system_crash($severity, $message, $exceptionClass = null, $file = null, $line = null, $trace = null) {
        global $pdo;

        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/crash.log';

        $timestamp = date('Y-m-d H:i:s');
        $ip = get_client_ip_address();
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown', 0, 255);
        $url = substr(($_SERVER['REQUEST_URI'] ?? (php_sapi_name() === 'cli' ? 'CLI Command' : '')), 0, 500);
        $method = $_SERVER['REQUEST_METHOD'] ?? (php_sapi_name() === 'cli' ? 'CLI' : 'GET');
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        // 1. Write to text log file
        $fileEntry = sprintf(
            "[%s] [%s] %s%s in %s on line %d | URL: %s | User: %s | IP: %s\n%s\n----------------------------------------\n",
            $timestamp,
            strtoupper($severity),
            $exceptionClass ? "($exceptionClass) " : '',
            $message,
            $file ?: 'unknown',
            (int)$line,
            $url,
            $userId ? "ID:$userId" : 'Guest',
            $ip,
            $trace ?: 'No stack trace'
        );
        @file_put_contents($logFile, $fileEntry, FILE_APPEND | LOCK_EX);

        // 2. Write to MySQL system_crash_logs table if PDO is available
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO system_crash_logs 
                    (severity, message, exception_class, file, line, trace, ip_address, user_agent, user_id, url, method, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    strtoupper($severity),
                    substr($message, 0, 5000),
                    $exceptionClass ? substr($exceptionClass, 0, 255) : null,
                    $file ? substr($file, 0, 255) : null,
                    $line ? (int)$line : null,
                    $trace ? substr($trace, 0, 65000) : null,
                    substr($ip, 0, 45),
                    $userAgent,
                    $userId,
                    $url,
                    $method
                ]);
            } catch (Throwable $e) {
                // Fail silently to avoid infinite error loop
            }
        }
    }

    /**
     * Audit log helper for administrative actions
     */
    function audit_log($action, $category, $details = '', $userId = null) {
        global $pdo;

        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }

        $ip = get_client_ip_address();

        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs (user_id, action, category, details, ip_address, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $userId,
                    substr($action, 0, 100),
                    substr($category, 0, 50),
                    is_array($details) ? json_encode($details) : (string)$details,
                    substr($ip, 0, 45)
                ]);
            } catch (Throwable $e) {}
        }
    }

    // Set uncaught exception handler
    set_exception_handler(function(Throwable $e) {
        log_system_crash(
            'CRITICAL',
            $e->getMessage(),
            get_class($e),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            http_response_code(500);
            // If in admin or super admin, show detailed error; otherwise show friendly message
            $isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin']);
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>System Error | UAP Quezon</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0b1120; color: #f8fafc; padding: 40px 20px; text-align: center; }
                    .error-box { max-width: 680px; margin: 40px auto; background: #131d33; border: 1px solid rgba(229,138,19,0.3); border-radius: 16px; padding: 36px 30px; text-align: left; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
                    h2 { color: #f59e0b; margin-top: 0; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
                    p { color: #94a3b8; line-height: 1.6; font-size: 0.95rem; }
                    pre { background: #090d19; padding: 16px; border-radius: 8px; color: #ef4444; overflow-x: auto; font-size: 12px; border: 1px solid rgba(255,255,255,0.06); }
                    .btn { display: inline-block; background: #15803d; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; margin-top: 15px; }
                </style>
            </head>
            <body>
                <div class="error-box">
                    <h2>⚠️ UAP Quezon System Notice</h2>
                    <p>An unexpected system event occurred. This incident has been logged for the Super Admin and chapter technical team.</p>
                    <?php if ($isAdmin): ?>
                        <div style="margin-top: 18px;">
                            <strong>Exception:</strong> <?php echo htmlspecialchars(get_class($e)); ?><br>
                            <strong>Message:</strong> <?php echo htmlspecialchars($e->getMessage()); ?><br>
                            <strong>File:</strong> <?php echo htmlspecialchars($e->getFile()); ?>:<?php echo (int)$e->getLine(); ?>
                            <pre><?php echo htmlspecialchars($e->getTraceAsString()); ?></pre>
                        </div>
                    <?php endif; ?>
                    <a href="javascript:history.back()" class="btn">Return to Previous Page</a>
                    <a href="/" class="btn" style="background: #e58a13; margin-left: 10px;">Return Home</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    });

    // Set custom PHP error handler for warnings and non-fatal notices
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $severity = 'WARNING';
        switch ($errno) {
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                $severity = 'ERROR';
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $severity = 'WARNING';
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $severity = 'INFO';
                break;
        }

        // Do not crash the script on notices or warnings, but log severe ones
        if ($severity === 'ERROR') {
            log_system_crash($severity, $errstr, 'PhpError', $errfile, $errline);
        }
        return false;
    });

    // Register shutdown function to catch fatal termination
    register_shutdown_function(function() {
        $lastError = error_get_last();
        if ($lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            log_system_crash(
                'CRITICAL',
                $lastError['message'],
                'FatalError',
                $lastError['file'],
                $lastError['line']
            );
        }
    });
}

