<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$payments = $pdo->query("
    SELECT p.id, u.name as member_name, u.id_number, 
           COALESCE(md.custom_title, d.title) as due_title, 
           p.amount_paid, p.method, p.reference_number, p.status, 
           p.submitted_at, p.verified_at, r.receipt_number
    FROM payments p
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    LEFT JOIN receipts r ON r.payment_id = p.id
    ORDER BY p.submitted_at DESC
")->fetchAll();

$total_verified = 0;
$total_pending = 0;
$total_rejected = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'verified') $total_verified += (float)$p['amount_paid'];
    if ($p['status'] === 'pending') $total_pending += (float)$p['amount_paid'];
    if ($p['status'] === 'rejected') $total_rejected += (float)$p['amount_paid'];
}

$page_title = 'Financial Reports • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">AUDIT &amp; ACCOUNTING</p>
    <h1>Financial &amp; Collection Reports</h1>
    <p class="page-subtitle">Complete ledger of all payments, verified collections, and exportable financial data.</p>
  </div>
  <div style="display:flex; gap:10px; align-items:center;">
    <a class="btn btn-success" href="export_csv.php" style="display:inline-flex; align-items:center; gap:6px;">
      <?php echo icon('download', '', 16); ?> <span>Export to Excel (CSV)</span>
    </a>
  </div>
</div>

<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
  <div class="stat-card" style="border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Verified Collections</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('check', '', 18); ?>
      </div>
    </div>
    <strong style="color: #10b981;">₱<?php echo number_format($total_verified, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Officially receipted funds</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #f59e0b;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Pending In Queue</span>
      <div class="stat-card-icon" style="background: rgba(245,158,11,0.12); color: #f59e0b;">
        <?php echo icon('clock', '', 18); ?>
      </div>
    </div>
    <strong style="color: #f59e0b;">₱<?php echo number_format($total_pending, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Awaiting admin approval</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #3b82f6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Transactions</span>
      <div class="stat-card-icon" style="background: rgba(59,130,246,0.12); color: #3b82f6;">
        <?php echo icon('reports', '', 18); ?>
      </div>
    </div>
    <strong><?php echo count($payments); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">All payment submission records</span>
  </div>
</div>

<div class="card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
    <h2 style="font-size: 17px; margin: 0;">Complete Transaction Ledger</h2>
    <span class="muted" style="font-size: 12px;"><?php echo count($payments); ?> total records</span>
  </div>

  <?php if (empty($payments)): ?>
    <p class="muted" style="text-align: center; padding: 32px;">No payment records logged in the system.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member Architect</th>
            <th>Due Title</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Status</th>
            <th>Official Receipt #</th>
            <th>Submitted On</th>
            <th style="text-align: right;">Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td>
                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($p['member_name']); ?></strong><br>
                <span class="muted" style="font-size: 11px;"><?php echo htmlspecialchars($p['id_number']); ?></span>
              </td>
              <td><?php echo htmlspecialchars($p['due_title']); ?></td>
              <td><strong style="color: #10b981;">₱<?php echo number_format($p['amount_paid'], 2); ?></strong></td>
              <td><span class="badge-pill badge-paid"><?php echo strtoupper(htmlspecialchars($p['method'])); ?></span></td>
              <td>
                <span class="badge-pill badge-<?php echo $p['status'] === 'verified' ? 'paid' : ($p['status'] === 'rejected' ? 'unpaid' : 'pending'); ?>">
                  <?php echo ucfirst($p['status']); ?>
                </span>
              </td>
              <td><code><?php echo htmlspecialchars($p['receipt_number'] ?: '—'); ?></code></td>
              <td><span class="muted" style="font-size: 12px;"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($p['submitted_at']))); ?></span></td>
              <td style="text-align: right;">
                <?php if ($p['status'] === 'verified' && $p['receipt_number']): ?>
                  <a href="../receipt.php?payment_id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('file', '', 11); ?> <span>Receipt</span>
                  </a>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
