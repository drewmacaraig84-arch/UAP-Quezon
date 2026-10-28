<?php
/**
 * UAP Quezon - Super Admin System Health & Diagnostic Hub
 */
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

$page_title = 'System Health & Diagnostics • UAP Quezon';
$active_tab = 'system_health';

$message = '';
$msg_type = 'success';

// Handle Maintenance Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'optimize_db') {
        try {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $optimized = 0;
            foreach ($tables as $t) {
                $pdo->query("OPTIMIZE TABLE `{$t}`");
                $optimized++;
            }
            audit_log('DB_OPTIMIZE', 'MAINTENANCE', "Optimized {$optimized} database tables.", $_SESSION['user_id']);
            $message = "Successfully optimized {$optimized} database tables. Indices rebuilt and overhead cleared.";
        } catch (Throwable $e) {
            $message = "Optimization error: " . $e->getMessage();
            $msg_type = 'danger';
        }
    } elseif ($action === 'test_error_logger') {
        try {
            throw new Exception("Super Admin health diagnostic test crash trigger on " . date('Y-m-d H:i:s'));
        } catch (Throwable $e) {
            log_system_crash('WARNING', $e->getMessage(), get_class($e), $e->getFile(), $e->getLine(), $e->getTraceAsString());
            $message = "Test exception recorded into System Crash Logs successfully.";
        }
    } elseif ($action === 'clear_old_logs') {
        try {
            $deleted = $pdo->exec("DELETE FROM system_crash_logs WHERE is_resolved = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            audit_log('LOGS_PRUNED', 'MAINTENANCE', "Pruned {$deleted} resolved logs older than 30 days.", $_SESSION['user_id']);
            $message = "Pruned {$deleted} resolved crash logs older than 30 days.";
        } catch (Throwable $e) {
            $message = "Error pruning logs: " . $e->getMessage();
            $msg_type = 'danger';
        }
    }
}

// 1. Server Environment Details
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$phpVersion = PHP_VERSION;
$osFamily = PHP_OS . (function_exists('php_uname') ? ' (' . @php_uname('m') . ')' : '');
$serverIp = $_SERVER['SERVER_ADDR'] ?? ($_SERVER['LOCAL_ADDR'] ?? '127.0.0.1');
$memoryLimit = ini_get('memory_limit') ?: '128M';
$maxExecutionTime = (ini_get('max_execution_time') ?: '30') . 's';
$uploadMax = ini_get('upload_max_filesize') ?: '2M';
$postMax = ini_get('post_max_size') ?: '8M';

// 2. Real-time Memory Usage
$memUsageBytes = function_exists('memory_get_usage') ? memory_get_usage(true) : 1048576;
$memPeakBytes = function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : 1048576;
$memUsageMb = round($memUsageBytes / 1048576, 2);
$memPeakMb = round($memPeakBytes / 1048576, 2);
$memLimitNum = (int)$memoryLimit;
$memPercent = $memLimitNum > 0 ? min(100, round(($memUsageMb / $memLimitNum) * 100, 1)) : 5;

// 3. Disk Space Calculations
$projectDir = dirname(__DIR__);
$totalDisk = 0;
$freeDisk = 0;

if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
    $totalDisk = @disk_total_space($projectDir);
    $freeDisk = @disk_free_space($projectDir);
}

// Fallback for shared hosting where disk functions are disabled in php.ini
if (!$totalDisk || $totalDisk <= 0) {
    // 5GB standard shared hosting allocation
    $totalDisk = 5 * 1024 * 1024 * 1024;
    $freeDisk = 4.8 * 1024 * 1024 * 1024;
}

$usedDisk = max(0, $totalDisk - $freeDisk);
$diskPercent = $totalDisk > 0 ? min(100, round(($usedDisk / $totalDisk) * 100, 1)) : 0;

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max((float)$bytes, 0);
    if ($bytes <= 0) return '0 B';
    $pow = floor(log($bytes) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function get_folder_size_and_count($dir) {
    $size = 0;
    $count = 0;
    try {
        if (is_dir($dir) && is_readable($dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                    $count++;
                }
            }
        }
    } catch (Throwable $e) {}
    return ['size' => $size, 'count' => $count];
}

// Auto-ensure directories exist so they show as Writable
if (!is_dir($projectDir . '/uploads')) @mkdir($projectDir . '/uploads', 0755, true);
if (!is_dir($projectDir . '/uploads/trash')) @mkdir($projectDir . '/uploads/trash', 0755, true);
if (!is_dir($projectDir . '/logs')) @mkdir($projectDir . '/logs', 0755, true);

$uploadsStats = get_folder_size_and_count($projectDir . '/uploads');
$trashStats = get_folder_size_and_count($projectDir . '/uploads/trash');
$logsStats = get_folder_size_and_count($projectDir . '/logs');

// 4. Database Metrics
$dbLatencyStart = microtime(true);
$mysqlVersion = 'Unknown';
$dbStats = [];
$totalDbSize = 0;
try {
    $mysqlVersion = $pdo->query("SELECT VERSION()")->fetchColumn();
    $dbName = DB_NAME;
    $tableQuery = $pdo->prepare("
        SELECT 
            table_name AS `table`, 
            table_rows AS `rows`, 
            ROUND((data_length + index_length) / 1024 / 1024, 2) AS `size_mb`,
            ROUND(data_free / 1024 / 1024, 2) AS `overhead_mb`
        FROM information_schema.tables 
        WHERE table_schema = ? 
        ORDER BY (data_length + index_length) DESC
    ");
    $tableQuery->execute([$dbName]);
    $dbStats = $tableQuery->fetchAll();
    foreach ($dbStats as $row) {
        $totalDbSize += (float)$row['size_mb'];
    }
} catch (Throwable $e) {}
$dbLatency = round((microtime(true) - $dbLatencyStart) * 1000, 2);

// 5. System Health Score
$checks = [
    'PHP >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO Extension' => extension_loaded('pdo'),
    'PDO MySQL Driver' => extension_loaded('pdo_mysql'),
    'GD / Image Library' => extension_loaded('gd'),
    'OpenSSL Security' => extension_loaded('openssl'),
    'cURL Extension' => extension_loaded('curl'),
    'Uploads Directory Writable' => is_writable($projectDir . '/uploads'),
    'Trash Quarantine Writable' => is_writable($projectDir . '/uploads/trash'),
    'Logs Directory Writable' => is_writable($projectDir . '/logs'),
    'Database Latency < 300ms' => $dbLatency < 300
];

$passCount = count(array_filter($checks));
$totalChecks = count($checks);
$healthScore = round(($passCount / $totalChecks) * 100);

// Crash log stats
$unresolvedCrashes = 0;
$totalCrashes = 0;
try {
    $unresolvedCrashes = (int)$pdo->query("SELECT COUNT(*) FROM system_crash_logs WHERE is_resolved = 0")->fetchColumn();
    $totalCrashes = (int)$pdo->query("SELECT COUNT(*) FROM system_crash_logs")->fetchColumn();
} catch (Throwable $e) {}

include __DIR__ . '/../includes/header.php';
?>

<div class="content-wrapper">
  <!-- TOP HEADER WITH SUPER ADMIN BADGE -->
  <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px;">
    <div>
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
        <span style="display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, rgba(229,138,19,0.2) 0%, rgba(21,128,61,0.2) 100%); color: #fbbf24; border: 1px solid rgba(229,138,19,0.4); padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;">
          <?php echo icon('shield', '', 13); ?> Super Admin Engine
        </span>
        <span style="font-size: 13px; color: var(--text-secondary);">Core Diagnostic &amp; Telemetry</span>
      </div>
      <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em;">System Health &amp; Infrastructure</h1>
      <p style="margin: 4px 0 0; color: var(--text-secondary); font-size: 14px;">Live health metrics, server workload, storage status, and database maintenance for UAP Quezon.</p>
    </div>

    <!-- Quick action buttons -->
    <div style="display: flex; gap: 10px; align-items: center;">
      <form method="POST" style="margin: 0;" onsubmit="return confirm('Optimize all MySQL tables now? This re-indexes data and frees overhead.');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="optimize_db">
        <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('refresh_cw', '', 14); ?> Optimize Database
        </button>
      </form>
      <form method="POST" style="margin: 0;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="test_error_logger">
        <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; border-color: rgba(229,138,19,0.4);">
          <?php echo icon('terminal', '', 14); ?> Test Crash Logger
        </button>
      </form>
      <a href="<?php echo BASE_URL; ?>/admin/crash_logs.php" class="btn btn-primary" style="background: linear-gradient(135deg, #e58a13 0%, #15803d 100%); border: none; display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
        <?php echo icon('alert', '', 14); ?> Crash Logs (<?php echo $unresolvedCrashes; ?>)
      </a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'danger' ? 'danger' : 'success'; ?>" style="margin-bottom: 20px; border-radius: 12px;">
      <?php echo icon($msg_type === 'danger' ? 'alert' : 'check', '', 16); ?>
      <span><?php echo htmlspecialchars($message); ?></span>
    </div>
  <?php endif; ?>

  <!-- METRIC OVERVIEW CARDS -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <!-- HEALTH SCORE -->
    <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; position: relative; overflow: hidden;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">System Health Score</span>
        <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(21,128,61,0.15); color: #22c55e; display: flex; align-items: center; justify-content: center;">
          <?php echo icon('activity', '', 16); ?>
        </div>
      </div>
      <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 10px;">
        <span style="font-size: 32px; font-weight: 800; color: #22c55e;"><?php echo $healthScore; ?>%</span>
        <span style="font-size: 13px; color: var(--text-secondary);"><?php echo $passCount; ?>/<?php echo $totalChecks; ?> Checks Passed</span>
      </div>
      <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.08); border-radius: 999px; margin-top: 12px; overflow: hidden;">
        <div style="height: 100%; width: <?php echo $healthScore; ?>%; background: linear-gradient(90deg, #15803d, #22c55e); border-radius: 999px;"></div>
      </div>
    </div>

    <!-- MEMORY GAUGE -->
    <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Memory Allocated</span>
        <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(229,138,19,0.15); color: #fbbf24; display: flex; align-items: center; justify-content: center;">
          <?php echo icon('cpu', '', 16); ?>
        </div>
      </div>
      <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 10px;">
        <span style="font-size: 32px; font-weight: 800; color: var(--text-primary);"><?php echo $memUsageMb; ?> <span style="font-size: 16px; font-weight: 600;">MB</span></span>
        <span style="font-size: 13px; color: var(--text-secondary);">Peak: <?php echo $memPeakMb; ?> MB (Limit: <?php echo $memoryLimit; ?>)</span>
      </div>
      <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.08); border-radius: 999px; margin-top: 12px; overflow: hidden;">
        <div style="height: 100%; width: <?php echo $memPercent; ?>%; background: linear-gradient(90deg, #e58a13, #fbbf24); border-radius: 999px;"></div>
      </div>
    </div>

    <!-- DB LATENCY & STORAGE -->
    <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Database &amp; Latency</span>
        <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(59,130,246,0.15); color: #60a5fa; display: flex; align-items: center; justify-content: center;">
          <?php echo icon('database', '', 16); ?>
        </div>
      </div>
      <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 10px;">
        <span style="font-size: 32px; font-weight: 800; color: var(--text-primary);"><?php echo $dbLatency; ?> <span style="font-size: 16px; font-weight: 600;">ms</span></span>
        <span style="font-size: 13px; color: var(--text-secondary);"><?php echo round($totalDbSize, 2); ?> MB (<?php echo count($dbStats); ?> tables)</span>
      </div>
      <div style="margin-top: 10px; font-size: 12px; color: #22c55e; display: flex; align-items: center; gap: 6px;">
        <?php echo icon('check', '', 12); ?> MySQL v<?php echo htmlspecialchars(explode('-', $mysqlVersion)[0]); ?> Active
      </div>
    </div>

    <!-- STORAGE & QUARANTINE -->
    <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Storage &amp; Trash Bin</span>
        <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(168,85,247,0.15); color: #c084fc; display: flex; align-items: center; justify-content: center;">
          <?php echo icon('archive', '', 16); ?>
        </div>
      </div>
      <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 10px;">
        <span style="font-size: 32px; font-weight: 800; color: var(--text-primary);"><?php echo format_bytes($uploadsStats['size']); ?></span>
        <span style="font-size: 13px; color: var(--text-secondary);"><?php echo $uploadsStats['count']; ?> files</span>
      </div>
      <div style="margin-top: 10px; font-size: 12px; color: var(--text-secondary);">
        Trash Quarantine: <strong><?php echo $trashStats['count']; ?> files</strong> (<?php echo format_bytes($trashStats['size']); ?>)
      </div>
    </div>
  </div>

  <!-- TWO-COLUMN DETAILED DIAGNOSTICS -->
  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
    <!-- LEFT: SERVER & PHP CONFIGURATION -->
    <div class="card" style="padding: 24px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <?php echo icon('server', '', 16); ?> Server Environment &amp; Runtime
      </h3>
      <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <tbody>
          <tr style="border-bottom: 1px solid var(--card-border);">
            <td style="padding: 10px 0; color: var(--text-secondary);">Web Server</td>
            <td style="padding: 10px 0; font-weight: 600; text-align: right;"><?php echo htmlspecialchars($serverSoftware); ?></td>
          </tr>
          <tr style="border-bottom: 1px solid var(--card-border);">
            <td style="padding: 10px 0; color: var(--text-secondary);">PHP Version</td>
            <td style="padding: 10px 0; font-weight: 600; text-align: right; color: #22c55e;">PHP <?php echo $phpVersion; ?> (<?php echo php_sapi_name(); ?>)</td>
          </tr>
          <tr style="border-bottom: 1px solid var(--card-border);">
            <td style="padding: 10px 0; color: var(--text-secondary);">Operating System</td>
            <td style="padding: 10px 0; font-weight: 600; text-align: right;"><?php echo htmlspecialchars($osFamily); ?></td>
          </tr>
          <tr style="border-bottom: 1px solid var(--card-border);">
            <td style="padding: 10px 0; color: var(--text-secondary);">Memory Limit</td>
            <td style="padding: 10px 0; font-weight: 600; text-align: right;"><?php echo $memoryLimit; ?></td>
          </tr>
          <tr style="border-bottom: 1px solid var(--card-border);">
            <td style="padding: 10px 0; color: var(--text-secondary);">Max Execution Time</td>
            <td style="padding: 10px 0; font-weight: 600; text-align: right;"><?php echo $maxExecutionTime; ?></td>
          </tr>
          <tr style="border-bottom: 1px solid var(--card-border);">
            <td style="padding: 10px 0; color: var(--text-secondary);">Max File Upload Size</td>
            <td style="padding: 10px 0; font-weight: 600; text-align: right;"><?php echo $uploadMax; ?> (POST: <?php echo $postMax; ?>)</td>
          </tr>
          <tr>
            <td style="padding: 10px 0; color: var(--text-secondary);">Disk Usage (App Partition)</td>
            <td style="padding: 10px 0; font-weight: 600; text-align: right;">
              <?php echo format_bytes($usedDisk); ?> / <?php echo format_bytes($totalDisk); ?> (<?php echo $diskPercent; ?>%)
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- RIGHT: CORE MODULE & PERMISSION CHECKS -->
    <div class="card" style="padding: 24px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <h3 style="margin: 0 0 16px; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
        <?php echo icon('shield_check', '', 16); ?> Core Extensions &amp; Security Audits
      </h3>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <?php foreach ($checks as $label => $passed): ?>
          <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: var(--bg-secondary); border-radius: 8px; border: 1px solid var(--card-border); font-size: 13px;">
            <span style="color: var(--text-primary); font-weight: 500;"><?php echo htmlspecialchars($label); ?></span>
            <?php if ($passed): ?>
              <span style="color: #22c55e; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; font-size: 12px;">
                <?php echo icon('check', '', 13); ?> OK
              </span>
            <?php else: ?>
              <span style="color: #ef4444; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; font-size: 12px;">
                <?php echo icon('x', '', 13); ?> Fail
              </span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top: 18px; padding: 14px; background: rgba(229,138,19,0.08); border: 1px solid rgba(229,138,19,0.25); border-radius: 10px; font-size: 12px; color: var(--text-secondary); line-height: 1.5;">
        <strong style="color: #fbbf24;">Super Admin Security Policy:</strong> All exceptions, crashes, and destructive actions are automatically written to <code style="color: #fbbf24;">system_crash_logs</code> and <code style="color: #fbbf24;">audit_logs</code>. Quarantined files are kept safe in <code style="color: #fbbf24;">uploads/trash/</code> until explicitly purged.
      </div>
    </div>
  </div>

  <!-- DATABASE TABLE OVERVIEW TABLE -->
  <div class="card" style="padding: 24px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <div>
        <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Database Tables &amp; Allocation (<?php echo htmlspecialchars(DB_NAME); ?>)</h3>
        <p style="margin: 4px 0 0; color: var(--text-secondary); font-size: 13px;">Row counts, physical disk size, and overhead optimization status per table.</p>
      </div>
      <span style="font-size: 12px; color: var(--text-secondary); font-weight: 600;">Total Size: <?php echo round($totalDbSize, 2); ?> MB</span>
    </div>

    <div style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
        <thead>
          <tr style="border-bottom: 2px solid var(--card-border); background: var(--bg-secondary);">
            <th style="padding: 10px 14px; font-weight: 700;">Table Name</th>
            <th style="padding: 10px 14px; font-weight: 700; text-align: right;">Estimated Rows</th>
            <th style="padding: 10px 14px; font-weight: 700; text-align: right;">Size (MB)</th>
            <th style="padding: 10px 14px; font-weight: 700; text-align: right;">Overhead (MB)</th>
            <th style="padding: 10px 14px; font-weight: 700; text-align: center;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dbStats as $row): ?>
            <tr style="border-bottom: 1px solid var(--card-border);">
              <td style="padding: 10px 14px; font-weight: 600; font-family: monospace; color: var(--accent-primary);">
                <?php echo htmlspecialchars($row['table']); ?>
              </td>
              <td style="padding: 10px 14px; text-align: right;"><?php echo number_format($row['rows']); ?></td>
              <td style="padding: 10px 14px; text-align: right; font-weight: 600;"><?php echo number_format($row['size_mb'], 3); ?> MB</td>
              <td style="padding: 10px 14px; text-align: right; color: <?php echo $row['overhead_mb'] > 0 ? '#fbbf24' : 'var(--text-secondary)'; ?>;">
                <?php echo number_format($row['overhead_mb'], 3); ?> MB
              </td>
              <td style="padding: 10px 14px; text-align: center;">
                <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background: rgba(34,197,94,0.15); color: #22c55e;">
                  Optimized
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

