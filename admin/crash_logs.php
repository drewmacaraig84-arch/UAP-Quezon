<?php
/**
 * UAP Quezon - Super Admin System Crash & Error Logs Monitor
 */
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

$page_title = 'System Crash & Error Logs • UAP Quezon';
$active_tab = 'crash_logs';

$message = '';
$msg_type = 'success';

// Handle POST actions (Resolve, Delete, Test Error)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'resolve_log') {
        $logId = (int)($_POST['log_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $stmt = $pdo->prepare("
            UPDATE system_crash_logs 
            SET is_resolved = 1, resolved_at = NOW(), resolved_by = ?, notes = ? 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $notes, $logId]);
        audit_log('CRASH_LOG_RESOLVED', 'SYSTEM', "Resolved crash log ID #$logId. Notes: $notes", $_SESSION['user_id']);
        $message = "Crash log #$logId marked as resolved.";
    } elseif ($action === 'unresolve_log') {
        $logId = (int)($_POST['log_id'] ?? 0);
        $stmt = $pdo->prepare("
            UPDATE system_crash_logs 
            SET is_resolved = 0, resolved_at = NULL, resolved_by = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$logId]);
        $message = "Crash log #$logId reopened.";
    } elseif ($action === 'resolve_all') {
        $stmt = $pdo->prepare("
            UPDATE system_crash_logs 
            SET is_resolved = 1, resolved_at = NOW(), resolved_by = ?, notes = 'Bulk resolved by Super Admin' 
            WHERE is_resolved = 0
        ");
        $stmt->execute([$_SESSION['user_id']]);
        audit_log('CRASH_LOGS_BULK_RESOLVED', 'SYSTEM', "Marked all open crash logs as resolved.", $_SESSION['user_id']);
        $message = "All open crash logs have been marked as resolved.";
    } elseif ($action === 'clear_resolved') {
        $deleted = $pdo->exec("DELETE FROM system_crash_logs WHERE is_resolved = 1");
        audit_log('CRASH_LOGS_CLEARED', 'SYSTEM', "Deleted $deleted resolved crash logs.", $_SESSION['user_id']);
        $message = "Successfully removed $deleted resolved crash logs.";
    } elseif ($action === 'clear_all') {
        $deleted = $pdo->exec("DELETE FROM system_crash_logs");
        audit_log('CRASH_LOGS_PURGED', 'SYSTEM', "Purged all $deleted crash logs.", $_SESSION['user_id']);
        $message = "All $deleted crash logs have been completely cleared.";
    } elseif ($action === 'delete_log') {
        $logId = (int)($_POST['log_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM system_crash_logs WHERE id = ?");
        $stmt->execute([$logId]);
        audit_log('CRASH_LOG_DELETED', 'SYSTEM', "Deleted crash log ID #$logId.", $_SESSION['user_id']);
        $message = "Crash log #$logId was permanently deleted.";
    } elseif ($action === 'clear_test_logs') {
        $deleted = $pdo->exec("DELETE FROM system_crash_logs WHERE exception_class = 'TestSuiteNotice' OR message LIKE '%automated test%'");
        audit_log('CRASH_LOGS_TEST_CLEARED', 'SYSTEM', "Cleared $deleted automated test logs.", $_SESSION['user_id']);
        $message = "Successfully cleaned up $deleted test verification notices.";
    } elseif ($action === 'trigger_test_crash') {
        try {
            // Trigger a simulated database syntax error
            throw new RuntimeException("Simulated runtime crash triggered by Super Admin for testing telemetry pipeline.");
        } catch (Throwable $e) {
            log_system_crash('CRITICAL', $e->getMessage(), get_class($e), $e->getFile(), $e->getLine(), $e->getTraceAsString());
            $message = "Test CRITICAL crash event generated and logged successfully.";
        }
    }
}

// Export CSV handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=uap_quezon_crash_logs_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Severity', 'Message', 'Exception', 'File', 'Line', 'URL', 'Method', 'IP', 'User ID', 'Resolved', 'Created At']);
    $logsExport = $pdo->query("SELECT id, severity, message, exception_class, file, line, url, method, ip_address, user_id, is_resolved, created_at FROM system_crash_logs ORDER BY id DESC");
    while ($row = $logsExport->fetch()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Filters & Query
$filterSeverity = trim($_GET['severity'] ?? '');
$filterStatus = trim($_GET['status'] ?? 'unresolved');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($filterSeverity !== '') {
    $where[] = "l.severity = ?";
    $params[] = $filterSeverity;
}

if ($filterStatus === 'unresolved') {
    $where[] = "l.is_resolved = 0";
} elseif ($filterStatus === 'resolved') {
    $where[] = "l.is_resolved = 1";
}

if ($search !== '') {
    $where[] = "(l.message LIKE ? OR l.exception_class LIKE ? OR l.file LIKE ? OR l.url LIKE ? OR l.ip_address LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Summary counts
$totalLogsCount = (int)$pdo->query("SELECT COUNT(*) FROM system_crash_logs")->fetchColumn();
$unresolvedCount = (int)$pdo->query("SELECT COUNT(*) FROM system_crash_logs WHERE is_resolved = 0")->fetchColumn();
$criticalCount = (int)$pdo->query("SELECT COUNT(*) FROM system_crash_logs WHERE severity = 'CRITICAL'")->fetchColumn();
$lastCrashTime = $pdo->query("SELECT created_at FROM system_crash_logs ORDER BY id DESC LIMIT 1")->fetchColumn();

// Fetch Logs
$sql = "
    SELECT l.*, u.name as user_name, u.role as user_role, rb.name as resolved_by_name
    FROM system_crash_logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN users rb ON l.resolved_by = rb.id
    $whereSql
    ORDER BY l.id DESC
    LIMIT 100
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="content-wrapper">
  <!-- TOP HEADER -->
  <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px;">
    <div>
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(239,68,68,0.15); color: #f87171; border: 1px solid rgba(239,68,68,0.3); padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;">
          <?php echo icon('alert', '', 13); ?> Telemetry &amp; Crash Watchdog
        </span>
        <span style="font-size: 13px; color: var(--text-secondary);">Super Admin Sentinel</span>
      </div>
      <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em;">System Crash &amp; Error Logs</h1>
      <p style="margin: 4px 0 0; color: var(--text-secondary); font-size: 14px;">Real-time automated logging of fatal errors, unhandled exceptions, and runtime warnings.</p>
    </div>

    <!-- Actions -->
    <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
      <form method="POST" style="margin: 0;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="trigger_test_crash">
        <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; border-color: rgba(239,68,68,0.4); color: #f87171;">
          <?php echo icon('zap', '', 14); ?> Trigger Test Crash
        </button>
      </form>

      <?php if ($unresolvedCount > 0): ?>
        <form method="POST" style="margin: 0;" onsubmit="return confirm('Mark all open crash logs as resolved?');">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="resolve_all">
          <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; border-color: rgba(34,197,94,0.4); color: #22c55e;">
            <?php echo icon('check', '', 14); ?> Resolve All Open
          </button>
        </form>
      <?php endif; ?>

      <?php
        $testLogCount = (int)$pdo->query("SELECT COUNT(*) FROM system_crash_logs WHERE exception_class = 'TestSuiteNotice' OR message LIKE '%automated test%'")->fetchColumn();
        if ($testLogCount > 0):
      ?>
        <form method="POST" style="margin: 0;" onsubmit="return confirm('Clean up all <?php echo $testLogCount; ?> automated test suite notices?');">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="clear_test_logs">
          <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; border-color: rgba(59,130,246,0.3); color: #3b82f6;">
            <?php echo icon('trash', '', 14); ?> Clear Test Notices (<?php echo $testLogCount; ?>)
          </button>
        </form>
      <?php endif; ?>

      <form method="POST" style="margin: 0;" onsubmit="return confirm('Permanently remove all resolved crash logs?');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="clear_resolved">
        <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('trash', '', 14); ?> Clear Resolved
        </button>
      </form>

      <?php if ($totalLogsCount > 0): ?>
        <form method="POST" style="margin: 0;" onsubmit="return confirm('Permanently purge ALL <?php echo $totalLogsCount; ?> crash logs?');">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="clear_all">
          <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; border-color: rgba(239,68,68,0.4); color: #f87171;">
            <?php echo icon('trash', '', 14); ?> Purge All (<?php echo $totalLogsCount; ?>)
          </button>
        </form>
      <?php endif; ?>

      <a href="?export=csv" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('download', '', 14); ?> Export CSV
      </a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'danger' ? 'danger' : 'success'; ?>" style="margin-bottom: 20px; border-radius: 12px;">
      <?php echo icon($msg_type === 'danger' ? 'alert' : 'check', '', 16); ?>
      <span><?php echo htmlspecialchars($message); ?></span>
    </div>
  <?php endif; ?>

  <!-- METRIC SUMMARY CARDS -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Open / Unresolved</span>
      <div style="font-size: 32px; font-weight: 800; color: <?php echo $unresolvedCount > 0 ? '#ef4444' : '#22c55e'; ?>; margin-top: 6px;">
        <?php echo $unresolvedCount; ?>
      </div>
      <span style="font-size: 12px; color: var(--text-secondary);">Requiring technical investigation</span>
    </div>

    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Critical Exceptions</span>
      <div style="font-size: 32px; font-weight: 800; color: #f59e0b; margin-top: 6px;">
        <?php echo $criticalCount; ?>
      </div>
      <span style="font-size: 12px; color: var(--text-secondary);">Fatal errors or uncaught throws</span>
    </div>

    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Total Telemetry Logs</span>
      <div style="font-size: 32px; font-weight: 800; color: var(--text-primary); margin-top: 6px;">
        <?php echo $totalLogsCount; ?>
      </div>
      <span style="font-size: 12px; color: var(--text-secondary);">Lifetime logged incidents</span>
    </div>

    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Latest Recorded Event</span>
      <div style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-top: 14px;">
        <?php echo $lastCrashTime ? date('M d, Y h:i A', strtotime($lastCrashTime)) : 'No incidents logged'; ?>
      </div>
      <span style="font-size: 12px; color: #22c55e;">Log storage active</span>
    </div>
  </div>

  <!-- FILTER BAR -->
  <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; margin-bottom: 20px;">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; margin: 0;">
      <div style="flex: 1; min-width: 220px;">
        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px;">Search Log Content</label>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search error message, class, file, IP, or URL..." class="form-control" style="width: 100%;">
      </div>

      <div style="min-width: 150px;">
        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px;">Severity</label>
        <select name="severity" class="form-control" style="width: 100%;">
          <option value="">All Severities</option>
          <option value="CRITICAL" <?php echo $filterSeverity === 'CRITICAL' ? 'selected' : ''; ?>>CRITICAL</option>
          <option value="ERROR" <?php echo $filterSeverity === 'ERROR' ? 'selected' : ''; ?>>ERROR</option>
          <option value="WARNING" <?php echo $filterSeverity === 'WARNING' ? 'selected' : ''; ?>>WARNING</option>
          <option value="INFO" <?php echo $filterSeverity === 'INFO' ? 'selected' : ''; ?>>INFO</option>
        </select>
      </div>

      <div style="min-width: 150px;">
        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px;">Status</label>
        <select name="status" class="form-control" style="width: 100%;">
          <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Statuses</option>
          <option value="unresolved" <?php echo $filterStatus === 'unresolved' ? 'selected' : ''; ?>>Open / Unresolved Only</option>
          <option value="resolved" <?php echo $filterStatus === 'resolved' ? 'selected' : ''; ?>>Resolved Only</option>
        </select>
      </div>

      <div>
        <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('filter', '', 14); ?> Apply Filter
        </button>
        <?php if ($search || $filterSeverity || $filterStatus !== 'unresolved'): ?>
          <a href="<?php echo BASE_URL; ?>/admin/crash_logs.php" class="btn btn-secondary" style="margin-left: 6px;">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- CRASH LOG ENTRIES LIST -->
  <?php if (empty($logs)): ?>
    <div class="card" style="padding: 48px 24px; text-align: center; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <div style="width: 54px; height: 54px; border-radius: 50%; background: rgba(34,197,94,0.15); color: #22c55e; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px;">
        <?php echo icon('check', '', 28); ?>
      </div>
      <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--text-primary);">No crash incidents found!</h3>
      <p style="margin: 6px auto 0; max-width: 440px; color: var(--text-secondary); font-size: 14px;">
        The system watchdog has not detected matching crashes with your active filters. Everything is running smoothly.
      </p>
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 14px;">
      <?php foreach ($logs as $log): ?>
        <?php
          $severityColor = '#ef4444';
          $severityBg = 'rgba(239,68,68,0.15)';
          if ($log['severity'] === 'CRITICAL') {
              $severityColor = '#dc2626';
              $severityBg = 'rgba(220,38,38,0.2)';
          } elseif ($log['severity'] === 'WARNING') {
              $severityColor = '#f59e0b';
              $severityBg = 'rgba(245,158,11,0.15)';
          } elseif ($log['severity'] === 'INFO') {
              $severityColor = '#3b82f6';
              $severityBg = 'rgba(59,130,246,0.15)';
          }
        ?>
        <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid <?php echo $log['is_resolved'] ? 'var(--card-border)' : 'rgba(239,68,68,0.3)'; ?>; border-radius: 14px; position: relative;">
          <!-- Top Row -->
          <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
              <span style="background: <?php echo $severityBg; ?>; color: <?php echo $severityColor; ?>; font-weight: 800; font-size: 11px; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.05em;">
                <?php echo htmlspecialchars($log['severity']); ?>
              </span>
              <span style="font-family: monospace; font-size: 13px; font-weight: 700; color: var(--text-primary);">
                #<?php echo $log['id']; ?> <?php echo htmlspecialchars($log['exception_class'] ?: 'SystemError'); ?>
              </span>
              <?php if ($log['is_resolved']): ?>
                <span style="background: rgba(34,197,94,0.15); color: #22c55e; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                  <?php echo icon('check', '', 12); ?> Resolved
                </span>
              <?php else: ?>
                <span style="background: rgba(239,68,68,0.15); color: #ef4444; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px;">
                  ● Open Issue
                </span>
              <?php endif; ?>
            </div>

            <!-- Timestamp & IP -->
            <div style="font-size: 12px; color: var(--text-secondary); text-align: right;">
              <span><?php echo date('M d, Y h:i:s A', strtotime($log['created_at'])); ?></span>
              <span style="margin-left: 8px; opacity: 0.7;">• IP: <?php echo htmlspecialchars($log['ip_address'] ?: '127.0.0.1'); ?></span>
            </div>
          </div>

          <!-- Message -->
          <div style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 10px; word-break: break-word;">
            <?php echo htmlspecialchars($log['message']); ?>
          </div>

          <!-- Meta Context -->
          <div style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 12px; color: var(--text-secondary); background: var(--bg-secondary); padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-family: monospace;">
            <div><strong>Location:</strong> <?php echo htmlspecialchars($log['file'] ?: 'Unknown'); ?><?php echo $log['line'] ? ':' . $log['line'] : ''; ?></div>
            <div><strong>Request:</strong> <?php echo htmlspecialchars($log['method'] ?: 'GET'); ?> <?php echo htmlspecialchars($log['url'] ?: '/'); ?></div>
            <?php if (!empty($log['user_name'])): ?>
              <div><strong>User:</strong> <?php echo htmlspecialchars($log['user_name']); ?> (ID: <?php echo (int)$log['user_id']; ?>)</div>
            <?php endif; ?>
          </div>

          <!-- Expandable Stack Trace -->
          <?php if (!empty($log['trace'])): ?>
            <details style="margin-bottom: 14px;">
              <summary style="font-size: 12px; font-weight: 700; color: var(--accent-primary); cursor: pointer; user-select: none;">
                View Stack Trace &amp; Debug Call Stack
              </summary>
              <pre style="margin-top: 10px; background: #090d19; color: #f87171; padding: 14px; border-radius: 8px; font-size: 11px; line-height: 1.5; overflow-x: auto; border: 1px solid rgba(255,255,255,0.06); max-height: 250px;"><?php echo htmlspecialchars($log['trace']); ?></pre>
            </details>
          <?php endif; ?>

          <!-- Resolution Info & Action Form -->
          <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; border-top: 1px solid var(--card-border); padding-top: 12px;">
            <?php if ($log['is_resolved']): ?>
              <div style="font-size: 12px; color: var(--text-secondary);">
                Resolved by <strong><?php echo htmlspecialchars($log['resolved_by_name'] ?: 'Super Admin'); ?></strong> on <?php echo date('M d, Y h:i A', strtotime($log['resolved_at'])); ?>
                <?php if ($log['notes']): ?>
                  • <em>Notes: <?php echo htmlspecialchars($log['notes']); ?></em>
                <?php endif; ?>
              </div>
              <div style="display: flex; gap: 6px; align-items: center;">
                <form method="POST" style="margin: 0;">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="unresolve_log">
                  <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 11px;">Reopen Issue</button>
                </form>
                <form method="POST" style="margin: 0;" onsubmit="return confirm('Permanently delete crash log #<?php echo $log['id']; ?>?');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="delete_log">
                  <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 11px; color: #ef4444; border-color: rgba(239,68,68,0.3); padding: 4px 8px;" title="Delete Log">
                    <?php echo icon('trash', '', 12); ?>
                  </button>
                </form>
              </div>
            <?php else: ?>
              <div style="display: flex; gap: 8px; width: 100%; align-items: center;">
                <form method="POST" style="display: flex; gap: 8px; flex: 1; align-items: center; margin: 0;">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="resolve_log">
                  <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                  <input type="text" name="notes" placeholder="Resolution notes (e.g. fixed null check in payments.php)..." class="form-control" style="flex: 1; font-size: 12px; padding: 6px 12px;">
                  <button type="submit" class="btn btn-sm btn-primary" style="background: #15803d; border-color: #15803d; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">
                    <?php echo icon('check', '', 13); ?> Mark Resolved
                  </button>
                </form>
                <form method="POST" style="margin: 0;" onsubmit="return confirm('Permanently delete crash log #<?php echo $log['id']; ?>?');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="delete_log">
                  <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 12px; color: #ef4444; border-color: rgba(239,68,68,0.3); padding: 6px 10px;" title="Delete Log">
                    <?php echo icon('trash', '', 13); ?>
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

