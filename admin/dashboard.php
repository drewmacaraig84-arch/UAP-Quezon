<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// --- 1. KPI QUERIES ---
// Total Verified Collections
$totalVerifiedCollections = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE status = 'verified'")->fetchColumn();

// Pending counts
$pendingPaymentsCount = (int)$pdo->query("SELECT COUNT(*) FROM payments p JOIN member_dues md ON p.member_due_id = md.id JOIN users u ON md.user_id = u.id WHERE p.status = 'pending'")->fetchColumn();
$pendingMembersCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'pending'")->fetchColumn();
$pendingDirectoryAppsCount = 0;
try {
    $pendingDirectoryAppsCount = (int)$pdo->query("SELECT COUNT(*) FROM directory_applications da JOIN users u ON da.user_id = u.id WHERE da.status = 'pending_fee'")->fetchColumn();
} catch (Throwable $e) {}
$pendingInquiriesCount = 0;
try {
    $pendingInquiriesCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE status = 'unread'")->fetchColumn();
} catch (Throwable $e) {}
$totalActionItems = $pendingPaymentsCount + $pendingMembersCount + $pendingDirectoryAppsCount + $pendingInquiriesCount;

// Members breakdown
$totalMembers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'approved'")->fetchColumn();

// Good standing count
$goodMembersCount = 0;
$allMembers = $pdo->query("SELECT id FROM users WHERE role = 'member' AND status = 'approved'")->fetchAll();
foreach ($allMembers as $m) {
    if (is_good_member($pdo, $m['id'])) {
        $goodMembersCount++;
    }
}
$goodStandingRate = $totalMembers > 0 ? round(($goodMembersCount / $totalMembers) * 100) : 100;

// Total Dues Packages
$totalDuesCount = (int)$pdo->query("SELECT COUNT(*) FROM dues")->fetchColumn();

// --- 2. URGENT PENDING PAYMENTS ---
$pendingPayments = $pdo->query("
    SELECT p.*, u.name as member_name, u.id_number,
           COALESCE(md.custom_title, d.title) as due_title,
           COALESCE(md.custom_amount, d.amount) as due_amount,
           md.payment_type, md.installment_months, md.total_paid,
           p.installment_number
    FROM payments p
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    WHERE p.status = 'pending'
    ORDER BY p.submitted_at ASC
    LIMIT 6
")->fetchAll();

// --- 3. URGENT PENDING REGISTRATIONS ---
$pendingMembers = $pdo->query("
    SELECT * FROM users
    WHERE role = 'member' AND status = 'pending'
    ORDER BY created_at ASC
    LIMIT 6
")->fetchAll();

// --- 4. DUES COLLECTION HEALTH BREAKDOWN ---
$duesSummary = $pdo->query("
    SELECT d.id, d.title, d.amount as base_amount, d.due_date,
           COUNT(md.id) as assigned_count,
           SUM(COALESCE(md.custom_amount, d.amount)) as total_expected,
           SUM(md.total_paid) as total_collected,
           SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) as fully_paid_count
    FROM dues d
    LEFT JOIN member_dues md ON md.due_id = d.id
    GROUP BY d.id, d.title, d.amount, d.due_date
    ORDER BY d.created_at DESC
    LIMIT 5
")->fetchAll();

// --- 5. RECENT VERIFIED TRANSACTIONS ---
$recentTransactions = $pdo->query("
    SELECT p.id, u.name as member_name, u.id_number,
           COALESCE(md.custom_title, d.title) as due_title,
           p.amount_paid, p.method, p.verified_at, r.receipt_number
    FROM payments p
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    LEFT JOIN receipts r ON r.payment_id = p.id
    WHERE p.status = 'verified'
    ORDER BY p.verified_at DESC
    LIMIT 5
")->fetchAll();

$page_title = 'Executive Dashboard • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<!-- EXECUTIVE HERO SECTION -->
<div class="page-hero">
  <div>
    <p class="eyebrow">UAP Quezon Chapter &bull; Admin Management Portal</p>
    <h1>Executive Dashboard</h1>
    <p class="page-subtitle">Live overview of chapter collections, member compliance, pending verification queues, and active dues.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('zap', '', 14); ?> <span>Command Center</span>
  </div>
</div>

<?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
  <!-- SUPER ADMIN QUICK HUD -->
  <div style="background: linear-gradient(135deg, rgba(229,138,19,0.12) 0%, rgba(21,128,61,0.12) 100%); border: 1px solid rgba(229,138,19,0.35); border-radius: 14px; padding: 18px 22px; margin-bottom: 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
    <div style="display: flex; align-items: center; gap: 14px;">
      <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(229,138,19,0.2); color: #fbbf24; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('shield', '', 22); ?>
      </div>
      <div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <strong style="color: #ffffff; font-size: 15px;">Super Admin Telemetry Hub</strong>
          <span style="font-size: 11px; background: #15803d; color: #ffffff; font-weight: 800; padding: 2px 8px; border-radius: 999px;">ACTIVE</span>
        </div>
        <p style="margin: 2px 0 0; font-size: 13px; color: var(--text-secondary);">
          System health watchdog, unhandled crash log capture, and soft-delete file recovery are active.
        </p>
      </div>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
      <a href="<?php echo BASE_URL; ?>/admin/system_health.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('activity', '', 13); ?> System Health
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/crash_logs.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; border-color: rgba(239,68,68,0.3); color: #f87171;">
        <?php echo icon('terminal', '', 13); ?> Crash Logs (<?php echo $unresolved_crash_count; ?>)
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/soft_deleted_files.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('archive', '', 13); ?> Trash Bin (<?php echo $soft_deleted_count; ?>)
      </a>
      <a href="<?php echo BASE_URL; ?>/admin/database_backup.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('database', '', 13); ?> DB Backup
      </a>
    </div>
  </div>
<?php endif; ?>

<!-- TOP KPI METRIC CARDS -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
  
  <!-- Verified Collections -->
  <div class="stat-card" style="border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Verified Collections</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('wallet', '', 20); ?>
      </div>
    </div>
    <strong style="color: #10b981;">₱<?php echo number_format($totalVerifiedCollections, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 6px; display: flex; align-items: center; gap: 4px;">
      <?php echo icon('check', '', 12); ?> Lifetime approved payments
    </span>
  </div>

  <!-- Action Items Needed -->
  <div class="stat-card" style="border-top: 3px solid <?php echo $totalActionItems > 0 ? '#f59e0b' : '#3b82f6'; ?>;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Action Items Needed</span>
      <div class="stat-card-icon" style="background: <?php echo $totalActionItems > 0 ? 'rgba(245,158,11,0.12)' : 'rgba(59,130,246,0.12)'; ?>; color: <?php echo $totalActionItems > 0 ? '#f59e0b' : '#3b82f6'; ?>;">
        <?php echo icon('clock', '', 20); ?>
      </div>
    </div>
    <strong style="color: <?php echo $totalActionItems > 0 ? '#f59e0b' : 'inherit'; ?>;">
      <?php echo $totalActionItems; ?>
    </strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 6px; display: block;">
      <?php echo $pendingPaymentsCount; ?> payments, <?php echo $pendingMembersCount; ?> sign-ups pending
    </span>
  </div>

  <!-- Approved Members -->
  <div class="stat-card" style="border-top: 3px solid #3b82f6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Approved Members</span>
      <div class="stat-card-icon" style="background: rgba(59,130,246,0.12); color: #3b82f6;">
        <?php echo icon('members', '', 20); ?>
      </div>
    </div>
    <strong><?php echo $totalMembers; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 6px; display: flex; align-items: center; gap: 4px;">
      <?php echo icon('good_members', '', 12); ?> <?php echo $goodMembersCount; ?> in Good Standing (<?php echo $goodStandingRate; ?>%)
    </span>
  </div>

  <!-- Active Dues Packages -->
  <div class="stat-card" style="border-top: 3px solid #8b5cf6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Active Dues Packages</span>
      <div class="stat-card-icon" style="background: rgba(139,92,246,0.12); color: #8b5cf6;">
        <?php echo icon('dues', '', 20); ?>
      </div>
    </div>
    <strong><?php echo $totalDuesCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 6px; display: block;">
      Configured chapter dues
    </span>
  </div>

</div>

<!-- QUICK ACTION SHORTCUTS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px;">
  <a href="payments.php" class="btn" style="padding: 12px; font-size: 13px;">
    <?php echo icon('payments', '', 16); ?> <span>Review Payments (<?php echo $pendingPaymentsCount; ?>)</span>
  </a>
  <a href="approvals.php" class="btn btn-secondary" style="padding: 12px; font-size: 13px;">
    <?php echo icon('approvals', '', 16); ?> <span>Member Approvals (<?php echo $pendingMembersCount; ?>)</span>
  </a>
  <a href="website_directory.php" class="btn btn-secondary" style="padding: 12px; font-size: 13px; border-color: <?php echo $pendingDirectoryAppsCount > 0 ? '#f59e0b' : 'var(--border-color)'; ?>;">
    <?php echo icon('website_directory', '', 16); ?> <span>Directory Apps (<?php echo $pendingDirectoryAppsCount; ?>)</span>
  </a>
  <a href="dues.php" class="btn btn-secondary" style="padding: 12px; font-size: 13px;">
    <?php echo icon('plus', '', 16); ?> <span>Add New Due</span>
  </a>
  <a href="inquiries.php" class="btn btn-secondary" style="padding: 12px; font-size: 13px; border-color: <?php echo $pendingInquiriesCount > 0 ? 'rgba(245,158,11,0.6)' : 'var(--border-color)'; ?>; <?php echo $pendingInquiriesCount > 0 ? 'background: rgba(245,158,11,0.06);' : ''; ?>">
    <?php echo icon('mail', '', 16); ?> <span style="<?php echo $pendingInquiriesCount > 0 ? 'color: var(--accent-primary); font-weight: 700;' : ''; ?>">Inquiries (<?php echo $pendingInquiriesCount; ?>)</span>
  </a>
  <a href="good_members.php" class="btn btn-secondary" style="padding: 12px; font-size: 13px;">
    <?php echo icon('good_members', '', 16); ?> <span>Good Members</span>
  </a>
  <a href="export_csv.php" class="btn btn-success" style="padding: 12px; font-size: 13px;">
    <?php echo icon('download', '', 16); ?> <span>Download CSV</span>
  </a>
</div>

<!-- DUAL COLUMN: PENDING ACTIONS -->
<div class="grid-2" style="margin-bottom: 24px; gap: 20px;">
  
  <!-- COLUMN 1: PENDING PAYMENTS QUEUE -->
  <div class="card" style="margin-bottom: 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="color: var(--accent-primary);"><?php echo icon('payments', '', 18); ?></span>
        <h2 style="font-size: 16px; margin: 0;">Pending Payment Proofs</h2>
      </div>
      <a href="payments.php" style="font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
        <span>View All (<?php echo $pendingPaymentsCount; ?>)</span> <?php echo icon('arrow_right', '', 12); ?>
      </a>
    </div>

    <?php if (empty($pendingPayments)): ?>
      <div style="text-align: center; padding: 36px 12px; color: var(--text-secondary);">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16,185,129,0.1); color: #10b981; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
          <?php echo icon('check', '', 24); ?>
        </div>
        <strong style="display: block; font-size: 14px; color: var(--text-primary);">All payments are reviewed!</strong>
        <p style="font-size: 12px; margin-top: 4px;" class="muted">No pending payment proofs awaiting administrative verification.</p>
      </div>
    <?php else: ?>
      <div class="table-shell">
        <table>
          <thead>
            <tr>
              <th>Member</th>
              <th>Due</th>
              <th>Amount</th>
              <th>Proof</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingPayments as $p): ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars($p['member_name']); ?></strong><br>
                  <span class="muted" style="font-size: 11px;"><?php echo htmlspecialchars($p['id_number']); ?></span>
                </td>
                <td><span style="font-size: 13px;"><?php echo htmlspecialchars($p['due_title']); ?></span></td>
                <td><strong>₱<?php echo number_format($p['amount_paid'], 2); ?></strong></td>
                <td>
                  <?php if ($p['proof_image']): ?>
                    <?php 
                      $proofPath = media_url($p['proof_image']); 
                      $isPdf = strtolower(pathinfo($p['proof_image'], PATHINFO_EXTENSION)) === 'pdf';
                    ?>
                    <?php if ($isPdf): ?>
                      <a href="<?php echo htmlspecialchars($proofPath); ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 11px;">
                        <?php echo icon('file', '', 12); ?> PDF
                      </a>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 11px;" onclick="openProofModal('<?php echo htmlspecialchars($proofPath); ?>', '<?php echo htmlspecialchars(addslashes($p['member_name'])); ?>', '<?php echo htmlspecialchars(addslashes($p['reference_number'])); ?>')">
                        <?php echo icon('image', '', 12); ?> View
                      </button>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
                <td style="white-space: nowrap; text-align: right;">
                  <form method="post" action="verify.php" class="inline" style="display:inline-block;margin-right:4px;"
                        data-confirm="Approve payment of ₱<?php echo number_format($p['amount_paid'], 2); ?> for <?php echo htmlspecialchars($p['member_name']); ?>?"
                        data-confirm-title="Approve Payment"
                        data-confirm-btn="Approve"
                        data-confirm-class="btn-success">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success" type="submit" style="padding: 5px 9px;" title="Approve Payment">
                      <?php echo icon('check', '', 13); ?>
                    </button>
                  </form>
                  <form method="post" action="verify.php" class="inline" style="display:inline-block;"
                        data-confirm="Reject this payment submission from <?php echo htmlspecialchars($p['member_name']); ?>?"
                        data-confirm-title="Reject Payment"
                        data-confirm-btn="Reject"
                        data-confirm-class="btn-danger">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button class="btn btn-sm btn-danger" type="submit" style="padding: 5px 9px;" title="Reject Payment">
                      <?php echo icon('x', '', 13); ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- COLUMN 2: PENDING MEMBER REGISTRATIONS -->
  <div class="card" style="margin-bottom: 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="color: #3b82f6;"><?php echo icon('approvals', '', 18); ?></span>
        <h2 style="font-size: 16px; margin: 0;">Pending Member Sign-Ups</h2>
      </div>
      <a href="approvals.php" style="font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
        <span>View All (<?php echo $pendingMembersCount; ?>)</span> <?php echo icon('arrow_right', '', 12); ?>
      </a>
    </div>

    <?php if (empty($pendingMembers)): ?>
      <div style="text-align: center; padding: 36px 12px; color: var(--text-secondary);">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59,130,246,0.1); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
          <?php echo icon('sparkles', '', 24); ?>
        </div>
        <strong style="display: block; font-size: 14px; color: var(--text-primary);">No pending registrations!</strong>
        <p style="font-size: 12px; margin-top: 4px;" class="muted">All member account applications have been processed.</p>
      </div>
    <?php else: ?>
      <div class="table-shell">
        <table>
          <thead>
            <tr>
              <th>Applicant Name</th>
              <th>PRC ID No.</th>
              <th>Registered</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingMembers as $m): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                <td><code><?php echo htmlspecialchars($m['id_number']); ?></code></td>
                <td><span class="muted" style="font-size: 11px;"><?php echo htmlspecialchars(date('M d, Y', strtotime($m['created_at']))); ?></span></td>
                <td style="white-space: nowrap; text-align: right;">
                  <form method="post" action="approvals.php" class="inline" style="display:inline-block;margin-right:4px;"
                        data-confirm="Approve registration for <?php echo htmlspecialchars($m['name']); ?>?"
                        data-confirm-title="Approve Member"
                        data-confirm-btn="Approve"
                        data-confirm-class="btn-success">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success" type="submit" style="padding: 4px 8px; font-size: 11px;">
                      <?php echo icon('check', '', 12); ?> Approve
                    </button>
                  </form>
                  <form method="post" action="approvals.php" class="inline" style="display:inline-block;"
                        data-confirm="Reject registration for <?php echo htmlspecialchars($m['name']); ?>?"
                        data-confirm-title="Reject Member"
                        data-confirm-btn="Reject"
                        data-confirm-class="btn-danger">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button class="btn btn-sm btn-danger" type="submit" style="padding: 4px 8px; font-size: 11px;">
                      <?php echo icon('x', '', 12); ?> Reject
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- DUES COLLECTION PROGRESS TRACKER -->
<div class="card" style="margin-bottom: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <div style="width: 36px; height: 36px; border-radius: 9px; background: rgba(245,158,11,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
        <?php echo icon('reports', '', 18); ?>
      </div>
      <div>
        <h2 style="font-size: 16px; margin: 0;">Dues Financial Health &amp; Collection Progress</h2>
        <p class="muted" style="font-size: 12px; margin: 2px 0 0;">Monitoring expected targets versus collected funds per due cycle.</p>
      </div>
    </div>
    <a href="dues.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 4px;">
      <span>Manage Dues</span> <?php echo icon('arrow_right', '', 12); ?>
    </a>
  </div>

  <?php if (empty($duesSummary)): ?>
    <p class="muted" style="text-align: center; padding: 24px;">No dues created yet.</p>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 14px;">
      <?php foreach ($duesSummary as $due):
        $expected = (float)($due['total_expected'] ?? 0);
        $collected = (float)($due['total_collected'] ?? 0);
        $percent = $expected > 0 ? min(100, round(($collected / $expected) * 100, 1)) : 0;
      ?>
        <div style="background: var(--bg-secondary); padding: 14px 18px; border-radius: 12px; border: 1px solid var(--border-color);">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
            <div>
              <strong style="font-size: 14px; color: var(--text-primary);"><?php echo htmlspecialchars($due['title']); ?></strong>
              <span class="muted" style="font-size: 12px; margin-left: 8px;">
                (Due: <?php echo date('M d, Y', strtotime($due['due_date'])); ?>)
              </span>
            </div>
            <div style="text-align: right;">
              <span style="font-size: 14px; font-weight: 700; color: #10b981;">₱<?php echo number_format($collected, 2); ?></span>
              <span class="muted" style="font-size: 13px;"> / ₱<?php echo number_format($expected, 2); ?></span>
              <span style="font-weight: 800; margin-left: 8px; color: var(--accent-primary);"><?php echo $percent; ?>%</span>
            </div>
          </div>
          
          <!-- Progress Bar -->
          <div style="width: 100%; height: 8px; background: rgba(0,0,0,0.12); border-radius: 999px; overflow: hidden; margin-bottom: 8px;">
            <div style="height: 100%; width: <?php echo $percent; ?>%; background: linear-gradient(90deg, #f59e0b, #10b981); border-radius: 999px; transition: width 0.4s ease;"></div>
          </div>

          <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary); flex-wrap: wrap; gap: 8px;">
            <span>Assigned: <strong><?php echo (int)$due['assigned_count']; ?> members</strong></span>
            <span>Fully Paid: <strong><?php echo (int)$due['fully_paid_count']; ?> members</strong></span>
            <span>Outstanding Balance: <strong style="color:<?php echo ($expected - $collected) > 0 ? '#ef4444' : '#10b981'; ?>;">₱<?php echo number_format(max(0, $expected - $collected), 2); ?></strong></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- RECENT TRANSACTIONS STREAM -->
<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <span style="color: #10b981;"><?php echo icon('dues', '', 18); ?></span>
      <h2 style="font-size: 16px; margin: 0;">Recent Verified Transactions</h2>
    </div>
    <a href="reports.php" style="font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
      <span>View All Reports</span> <?php echo icon('arrow_right', '', 12); ?>
    </a>
  </div>

  <?php if (empty($recentTransactions)): ?>
    <p class="muted" style="text-align: center; padding: 24px;">No verified transactions recorded yet.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>Due Title</th>
            <th>Amount Paid</th>
            <th>Method</th>
            <th>Official Receipt #</th>
            <th>Verified Date</th>
            <th style="text-align: right;">Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentTransactions as $tx): ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($tx['member_name']); ?></strong><br>
                <span class="muted" style="font-size: 11px;"><?php echo htmlspecialchars($tx['id_number']); ?></span>
              </td>
              <td><?php echo htmlspecialchars($tx['due_title']); ?></td>
              <td><strong style="color: #10b981;">₱<?php echo number_format($tx['amount_paid'], 2); ?></strong></td>
              <td><span class="badge-pill badge-paid"><?php echo strtoupper(htmlspecialchars($tx['method'])); ?></span></td>
              <td><code><?php echo htmlspecialchars($tx['receipt_number'] ?: '—'); ?></code></td>
              <td><span class="muted" style="font-size: 12px;"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($tx['verified_at']))); ?></span></td>
              <td style="text-align: right;">
                <?php if ($tx['receipt_number']): ?>
                  <a href="../receipt.php?payment_id=<?php echo $tx['id']; ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('file', '', 12); ?> <span>Receipt</span>
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Proof Lightbox Modal -->
<div id="proofModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.75);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #131d33);border-radius:16px;max-width:600px;width:100%;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.6);border:1px solid var(--border-color);">
    <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);">
      <h3 id="modalMemberTitle" style="margin:0;font-size:16px;color:var(--text-primary);">Proof of Payment</h3>
      <button type="button" onclick="closeProofModal()" style="border:none;background:transparent;cursor:pointer;color:var(--text-primary);display:flex;align-items:center;justify-content:center;padding:4px;">
        <?php echo icon('x', '', 18); ?>
      </button>
    </div>
    <div style="padding:18px;overflow-y:auto;text-align:center;background:rgba(0,0,0,0.1);">
      <img id="modalProofImg" src="" alt="Proof Preview" style="max-width:100%;max-height:65vh;object-fit:contain;border-radius:10px;border:1px solid var(--border-color);">
      <div id="modalProofFallback" style="display:none; padding:32px 16px; text-align:center; background:rgba(239,68,68,0.06); border:1px dashed rgba(239,68,68,0.3); border-radius:10px;">
        <div style="font-size:24px; color:#ef4444; margin-bottom:8px;">⚠️</div>
        <strong style="color:var(--text-primary); font-size:14px; display:block;">Proof Image Not Found in Storage</strong>
        <p class="muted" style="font-size:12px; margin:6px 0 0 0;">The uploaded screenshot file is not found on the server. You can verify this transaction using the reference number below.</p>
      </div>
      <div id="modalRefInfo" style="margin-top:10px;font-size:13px;color:var(--text-secondary);"></div>
    </div>
    <div style="padding:12px 20px;display:flex;justify-content:flex-end;border-top:1px solid var(--border-color);gap:8px;">
      <a id="modalDirectLink" href="#" target="_blank" class="btn btn-sm btn-secondary" style="display:inline-flex;align-items:center;gap:4px;">
        <?php echo icon('external_link', '', 12); ?> <span>Open Full Image</span>
      </a>
      <button type="button" class="btn btn-sm btn-secondary" onclick="closeProofModal()">Close</button>
    </div>
  </div>
</div>

<script>
function openProofModal(imgSrc, memberName, refNo) {
  var img = document.getElementById('modalProofImg');
  var fallback = document.getElementById('modalProofFallback');
  if (fallback) fallback.style.display = 'none';
  img.style.display = 'block';
  img.onerror = function() {
    this.style.display = 'none';
    if (fallback) fallback.style.display = 'block';
  };
  img.src = imgSrc;
  document.getElementById('modalDirectLink').href = imgSrc;
  document.getElementById('modalMemberTitle').innerText = 'Payment Proof: ' + memberName;
  document.getElementById('modalRefInfo').innerText = refNo ? 'Reference Number: ' + refNo : '';
  document.getElementById('proofModal').style.display = 'flex';
}

function closeProofModal() {
  document.getElementById('proofModal').style.display = 'none';
  var img = document.getElementById('modalProofImg');
  if (img) {
    img.src = '';
    img.onerror = null;
  }
}

document.getElementById('proofModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeProofModal();
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
