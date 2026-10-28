<?php
/**
 * UAP Quezon - Super Admin Database Backup & Maintenance Hub
 */
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

$page_title = 'Database Backup & Tools • UAP Quezon';
$active_tab = 'database_backup';

$message = '';
$msg_type = 'success';

// Handle Export .sql dump
if (isset($_GET['action']) && $_GET['action'] === 'download_sql') {
    // Generate full SQL dump
    $dbName = DB_NAME;
    $dump = "-- ====================================================================\n";
    $dump .= "-- UAP Quezon Chapter - Full Database SQL Backup\n";
    $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $dump .= "-- Database: " . $dbName . "\n";
    $dump .= "-- Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Localhost') . "\n";
    $dump .= "-- ====================================================================\n\n";
    $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        // Table create statement
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch();
        $dump .= "DROP TABLE IF EXISTS `{$t}`;\n";
        $dump .= $createStmt['Create Table'] . ";\n\n";

        // Table rows
        $rows = $pdo->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $dump .= "-- Data for `{$t}` (" . count($rows) . " rows)\n";
            $dump .= "INSERT INTO `{$t}` VALUES\n";
            $insertRows = [];
            foreach ($rows as $r) {
                $values = [];
                foreach ($r as $val) {
                    if ($val === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = $pdo->quote($val);
                    }
                }
                $insertRows[] = '(' . implode(', ', $values) . ')';
            }
            $dump .= implode(",\n", $insertRows) . ";\n\n";
        }
    }
    $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    audit_log('DB_BACKUP_DOWNLOAD', 'DATABASE', "Downloaded full SQL dump ($dbName)", $_SESSION['user_id']);

    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename=uap_quezon_backup_' . date('Ymd_His') . '.sql');
    echo $dump;
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'optimize_all') {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($tables as $t) {
            $pdo->query("OPTIMIZE TABLE `{$t}`");
            $count++;
        }
        audit_log('DB_OPTIMIZE_ALL', 'DATABASE', "Optimized all $count database tables.", $_SESSION['user_id']);
        $message = "All $count tables optimized and indexed successfully.";
    } elseif ($action === 'optimize_table') {
        $table = $_POST['table'] ?? '';
        $validTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if ($table && in_array($table, $validTables, true)) {
            $pdo->query("OPTIMIZE TABLE `{$table}`");
            audit_log('DB_OPTIMIZE_TABLE', 'DATABASE', "Optimized table '$table'", $_SESSION['user_id']);
            $message = "Table `{$table}` optimized successfully.";
        }
    }
}

// Query Table stats
$dbStats = [];
$totalRows = 0;
$totalSizeMb = 0;
try {
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
    $tableQuery->execute([DB_NAME]);
    $dbStats = $tableQuery->fetchAll();
    foreach ($dbStats as $row) {
        $totalRows += (int)$row['rows'];
        $totalSizeMb += (float)$row['size_mb'];
    }
} catch (Throwable $e) {}

include __DIR__ . '/../includes/header.php';
?>

<div class="content-wrapper">
  <!-- TOP HEADER -->
  <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px;">
    <div>
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(21,128,61,0.15); color: #22c55e; border: 1px solid rgba(21,128,61,0.3); padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;">
          <?php echo icon('database', '', 13); ?> MySQL Engine &amp; Disaster Recovery
        </span>
        <span style="font-size: 13px; color: var(--text-secondary);">Super Admin Data Protection</span>
      </div>
      <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em;">Database Backup &amp; Maintenance</h1>
      <p style="margin: 4px 0 0; color: var(--text-secondary); font-size: 14px;">1-Click SQL database dump, table optimization, index repair, and storage management.</p>
    </div>

    <!-- Actions -->
    <div style="display: flex; gap: 10px; align-items: center;">
      <a href="?action=download_sql" class="btn btn-primary" style="background: linear-gradient(135deg, #e58a13 0%, #15803d 100%); border: none; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('download', '', 14); ?> Download SQL Backup
      </a>
      <form method="POST" style="margin: 0;" onsubmit="return confirm('Optimize all database tables?');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="optimize_all">
        <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('refresh_cw', '', 14); ?> Optimize All Tables
        </button>
      </form>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'danger' ? 'danger' : 'success'; ?>" style="margin-bottom: 20px; border-radius: 12px;">
      <?php echo icon($msg_type === 'danger' ? 'alert' : 'check', '', 16); ?>
      <span><?php echo htmlspecialchars($message); ?></span>
    </div>
  <?php endif; ?>

  <!-- SUMMARY CARDS -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Active Database</span>
      <div style="font-size: 26px; font-weight: 800; color: var(--accent-primary); margin-top: 6px; font-family: monospace;">
        <?php echo htmlspecialchars(DB_NAME); ?>
      </div>
      <span style="font-size: 12px; color: var(--text-secondary);">Host: <?php echo htmlspecialchars(DB_HOST); ?></span>
    </div>

    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Total Database Size</span>
      <div style="font-size: 32px; font-weight: 800; color: #22c55e; margin-top: 6px;">
        <?php echo round($totalSizeMb, 2); ?> <span style="font-size: 16px;">MB</span>
      </div>
      <span style="font-size: 12px; color: var(--text-secondary);"><?php echo count($dbStats); ?> relational tables</span>
    </div>

    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Total Records</span>
      <div style="font-size: 32px; font-weight: 800; color: #60a5fa; margin-top: 6px;">
        <?php echo number_format($totalRows); ?>
      </div>
      <span style="font-size: 12px; color: var(--text-secondary);">Across all active schemas</span>
    </div>
  </div>

  <!-- TABLES LIST -->
  <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
    <div style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <thead>
          <tr style="border-bottom: 2px solid var(--card-border); background: var(--bg-secondary);">
            <th style="padding: 10px 14px; text-align: left;">Table Name</th>
            <th style="padding: 10px 14px; text-align: right;">Estimated Rows</th>
            <th style="padding: 10px 14px; text-align: right;">Physical Size</th>
            <th style="padding: 10px 14px; text-align: right;">Overhead</th>
            <th style="padding: 10px 14px; text-align: right;">Action</th>
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
              <td style="padding: 10px 14px; text-align: right;">
                <form method="POST" style="margin: 0; display: inline;">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="optimize_table">
                  <input type="hidden" name="table" value="<?php echo htmlspecialchars($row['table']); ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 4px 8px;">
                    Optimize
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

