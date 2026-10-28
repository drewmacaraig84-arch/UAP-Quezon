<?php
/**
 * UAP Quezon - Super Admin Audit Trail & Activity Logs
 */
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

$page_title = 'Audit Logs & Activity Trail • UAP Quezon';
$active_tab = 'audit_logs';

// Handle Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=uap_quezon_audit_trail_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User ID', 'Action', 'Category', 'Details', 'IP Address', 'Timestamp']);
    $rows = $pdo->query("SELECT id, user_id, action, category, details, ip_address, created_at FROM audit_logs ORDER BY id DESC");
    while ($r = $rows->fetch()) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// Filters
$category = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');

$where = [];
$params = [];

if ($category !== '') {
    $where[] = "a.category = ?";
    $params[] = $category;
}

if ($search !== '') {
    $where[] = "(a.action LIKE ? OR a.details LIKE ? OR a.ip_address LIKE ? OR u.name LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Summary counts
$totalAuditCount = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
$todayCount = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$authCount = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE category = 'AUTH'")->fetchColumn();

// Fetch Logs
$sql = "
    SELECT a.*, u.name as user_name, u.role as user_role, u.id_number
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    $whereSql
    ORDER BY a.id DESC
    LIMIT 150
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
        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;">
          <?php echo icon('shield_check', '', 13); ?> System Compliance &amp; Accountability
        </span>
        <span style="font-size: 13px; color: var(--text-secondary);">Immutable Event Record</span>
      </div>
      <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em;">Audit Trail &amp; Activity Log</h1>
      <p style="margin: 4px 0 0; color: var(--text-secondary); font-size: 14px;">Complete forensic history of administrative actions, user authentication, and system events.</p>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
      <a href="?export=csv" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('download', '', 14); ?> Export CSV Log
      </a>
    </div>
  </div>

  <!-- SUMMARY CARDS -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Today's Events</span>
      <div style="font-size: 32px; font-weight: 800; color: #22c55e; margin-top: 6px;"><?php echo $todayCount; ?></div>
      <span style="font-size: 12px; color: var(--text-secondary);">Actions recorded today</span>
    </div>

    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Auth Operations</span>
      <div style="font-size: 32px; font-weight: 800; color: #fbbf24; margin-top: 6px;"><?php echo $authCount; ?></div>
      <span style="font-size: 12px; color: var(--text-secondary);">Logins &amp; access attempts</span>
    </div>

    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Total Audit Entries</span>
      <div style="font-size: 32px; font-weight: 800; color: var(--text-primary); margin-top: 6px;"><?php echo $totalAuditCount; ?></div>
      <span style="font-size: 12px; color: var(--text-secondary);">Lifetime system records</span>
    </div>
  </div>

  <!-- FILTER -->
  <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; margin-bottom: 20px;">
    <form method="GET" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; margin: 0;">
      <div style="flex: 1; min-width: 220px;">
        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px;">Search Log</label>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search action, details, user, or IP..." class="form-control" style="width: 100%;">
      </div>

      <div style="min-width: 180px;">
        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px;">Category</label>
        <select name="category" class="form-control" style="width: 100%;">
          <option value="">All Categories</option>
          <option value="AUTH" <?php echo $category === 'AUTH' ? 'selected' : ''; ?>>AUTH</option>
          <option value="PAYMENTS" <?php echo $category === 'PAYMENTS' ? 'selected' : ''; ?>>PAYMENTS</option>
          <option value="MEMBERS" <?php echo $category === 'MEMBERS' ? 'selected' : ''; ?>>MEMBERS</option>
          <option value="STORAGE" <?php echo $category === 'STORAGE' ? 'selected' : ''; ?>>STORAGE</option>
          <option value="DATABASE" <?php echo $category === 'DATABASE' ? 'selected' : ''; ?>>DATABASE</option>
          <option value="MAINTENANCE" <?php echo $category === 'MAINTENANCE' ? 'selected' : ''; ?>>MAINTENANCE</option>
          <option value="SYSTEM" <?php echo $category === 'SYSTEM' ? 'selected' : ''; ?>>SYSTEM</option>
        </select>
      </div>

      <div>
        <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('filter', '', 14); ?> Filter
        </button>
        <?php if ($search || $category): ?>
          <a href="<?php echo BASE_URL; ?>/admin/audit_logs.php" class="btn btn-secondary" style="margin-left: 6px;">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- TABLE -->
  <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
    <div style="overflow-x: auto;">
      <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
        <thead>
          <tr style="border-bottom: 2px solid var(--card-border); background: var(--bg-secondary);">
            <th style="padding: 10px 14px; text-align: left;">Category / Action</th>
            <th style="padding: 10px 14px; text-align: left;">Actor / User</th>
            <th style="padding: 10px 14px; text-align: left;">Event Details</th>
            <th style="padding: 10px 14px; text-align: left;">Client IP</th>
            <th style="padding: 10px 14px; text-align: right;">Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="5" style="padding: 24px; text-align: center; color: var(--text-secondary);">No audit records found matching your filters.</td></tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <tr style="border-bottom: 1px solid var(--card-border);">
                <td style="padding: 10px 14px;">
                  <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; background: rgba(229,138,19,0.12); color: #fbbf24; letter-spacing: 0.05em; margin-bottom: 4px;">
                    <?php echo htmlspecialchars($log['category']); ?>
                  </span>
                  <div style="font-weight: 600; color: var(--text-primary); font-family: monospace; font-size: 12px;">
                    <?php echo htmlspecialchars($log['action']); ?>
                  </div>
                </td>
                <td style="padding: 10px 14px;">
                  <?php if ($log['user_name']): ?>
                    <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($log['user_name']); ?></div>
                    <div style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($log['id_number']); ?> (<?php echo htmlspecialchars($log['user_role']); ?>)</div>
                  <?php else: ?>
                    <span style="color: var(--text-secondary); font-style: italic;">System / Anonymous</span>
                  <?php endif; ?>
                </td>
                <td style="padding: 10px 14px; color: var(--text-primary);">
                  <?php echo htmlspecialchars($log['details']); ?>
                </td>
                <td style="padding: 10px 14px; font-family: monospace; font-size: 12px; color: var(--text-secondary);">
                  <?php echo htmlspecialchars($log['ip_address'] ?: '127.0.0.1'); ?>
                </td>
                <td style="padding: 10px 14px; text-align: right; color: var(--text-secondary); font-size: 12px; white-space: nowrap;">
                  <?php echo date('M d, Y h:i:s A', strtotime($log['created_at'])); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

