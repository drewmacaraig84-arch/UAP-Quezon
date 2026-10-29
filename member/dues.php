<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qr_helper.php';
require_member();

$currentUid = current_user_id();

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$currentUid]);
$memberUser = $stmt->fetch();

if (!$memberUser) {
    die("Member record not found.");
}

// Fetch member attendance and standing
$attendance = get_or_create_member_attendance($pdo, $currentUid);
$standing = function_exists('get_member_standing_details') ? get_member_standing_details($pdo, $currentUid) : ['is_good' => true, 'is_revoked' => false];

// Fetch all assigned dues and obligations for this member
$stmt = $pdo->prepare("
    SELECT md.id as member_due_id, md.status, md.payment_type, md.installment_months,
           md.total_paid, md.discount_amount, md.other_obligations, md.due_period,
           COALESCE(md.custom_title, d.title) as title,
           COALESCE(md.custom_description, d.description) as description,
           COALESCE(md.custom_amount, d.amount) as amount,
           COALESCE(md.custom_due_date, d.due_date) as due_date,
           COALESCE(md.custom_term, d.term) as term
    FROM member_dues md
    JOIN dues d ON md.due_id = d.id
    WHERE md.user_id = ?
    ORDER BY COALESCE(md.custom_due_date, d.due_date) ASC
");
$stmt->execute([$currentUid]);
$dues = $stmt->fetchAll();

// Calculate totals according to client requirements:
// 1. Payable Dues (Base sum)
// 2. Total Discounts
// 3. Other Obligations
// 4. Overall Total Payable (Net total balance remaining)
$total_payable_dues = 0.00;
$total_discounts = 0.00;
$total_other_obligations = 0.00;
$total_settled = 0.00;
$overall_total_payable = 0.00;

foreach ($dues as $d) {
    $baseDue = round((float)$d['amount'], 2);
    $discount = round((float)($d['discount_amount'] ?? 0), 2);
    $otherOblig = round((float)($d['other_obligations'] ?? 0), 2);
    $paid = round((float)$d['total_paid'], 2);

    $netDueForThis = max(0, round($baseDue - $discount + $otherOblig, 2));
    $balanceForThis = max(0, round($netDueForThis - $paid, 2));

    $total_payable_dues += $baseDue;
    $total_discounts += $discount;
    $total_other_obligations += $otherOblig;
    $total_settled += $paid;
    $overall_total_payable += $balanceForThis;
}

$page_title = 'My Chapter Dues & Obligations • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>

<!-- PAGE HERO -->
<div class="page-hero">
  <div>
    <p class="eyebrow">MEMBER FINANCIAL PORTAL • 2ND PAGE</p>
    <h1>My Chapter Dues &amp; Obligations</h1>
    <p class="page-subtitle">
      Review your assigned fiscal period dues, applicable discounts, other chapter obligations, and real-time settlement status.
    </p>
  </div>
  <div style="display: flex; gap: 8px; flex-wrap: wrap;">
    <a href="chapter_status.php" style="text-decoration: none;">
      <?php echo render_activity_badge($attendance); ?>
    </a>
    <div class="hero-badge">
      <?php if ($standing['is_revoked']): ?>
        <?php echo icon('alert', '', 14); ?> <span style="color: #ef4444;">Standing Revoked</span>
      <?php elseif ($standing['is_good']): ?>
        <?php echo icon('shield_check', '', 14); ?> <span>Good Standing</span>
      <?php else: ?>
        <?php echo icon('clock', '', 14); ?> <span>Pending Settlement</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- CLIENT SPECIFIED STAT CARDS:
     1. Payable Dues
     2. Total Discounts
     3. Overall Total Payable
     + Other Obligations & Total Settled Summary
-->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
  <!-- Card 1: Payable Dues -->
  <div class="stat-card" style="border-top: 3px solid #3b82f6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Payable Dues</span>
      <div class="stat-card-icon" style="background: rgba(59,130,246,0.12); color: #3b82f6;">
        <?php echo icon('dues', '', 18); ?>
      </div>
    </div>
    <strong style="font-size: 24px;">₱<?php echo number_format($total_payable_dues, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Base chapter assessment</span>
  </div>

  <!-- Card 2: Total Discounts -->
  <div class="stat-card" style="border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Discounts</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('check', '', 18); ?>
      </div>
    </div>
    <strong style="font-size: 24px; color: #10b981;">₱<?php echo number_format($total_discounts, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Early bird &amp; chapter privileges</span>
  </div>

  <!-- Card: Other Obligations -->
  <div class="stat-card" style="border-top: 3px solid #8b5cf6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Other Obligations</span>
      <div class="stat-card-icon" style="background: rgba(139,92,246,0.12); color: #8b5cf6;">
        <?php echo icon('wallet', '', 18); ?>
      </div>
    </div>
    <strong style="font-size: 24px; color: #8b5cf6;">₱<?php echo number_format($total_other_obligations, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Building fund, NATCON, etc.</span>
  </div>

  <!-- Card 3: Overall Total Payable -->
  <div class="stat-card" style="border-top: 3px solid <?php echo $overall_total_payable > 0 ? '#ef4444' : '#10b981'; ?>;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Overall Total Payable</span>
      <div class="stat-card-icon" style="background: <?php echo $overall_total_payable > 0 ? 'rgba(239,68,68,0.12)' : 'rgba(16,185,129,0.12)'; ?>; color: <?php echo $overall_total_payable > 0 ? '#ef4444' : '#10b981'; ?>;">
        <?php echo icon('alert', '', 18); ?>
      </div>
    </div>
    <strong style="font-size: 24px; color: <?php echo $overall_total_payable > 0 ? '#ef4444' : '#10b981'; ?>;">
      ₱<?php echo number_format($overall_total_payable, 2); ?>
    </strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
      <?php echo $overall_total_payable > 0 ? 'Outstanding balance remaining' : 'Fully settled &amp; cleared'; ?>
    </span>
  </div>
</div>

<!-- ============================================================== -->
<!-- CLIENT SPECIFIED SECTION: MY ACCOUNT STATEMENT                 -->
<!-- Columns:                                                       -->
<!-- - Due Period                                                   -->
<!-- - Due                                                          -->
<!-- - Other Obligations                                            -->
<!-- - Balance                                                      -->
<!-- - Due Date                                                     -->
<!-- - Status (paid, unpaid)                                        -->
<!-- - Action (pay now)                                             -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 24px;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h2 style="font-size: 18px; margin: 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
        <?php echo icon('dues', '', 20); ?>
        My Account Statement
      </h2>
      <p class="muted" style="font-size: 12.5px; margin: 4px 0 0 0;">
        Official itemized assessment of chapter dues, special event levies, and verified payments.
      </p>
    </div>
    <div style="display: flex; gap: 8px;">
      <a href="history.php" class="btn btn-secondary" style="font-size: 12.5px; padding: 6px 14px;">
        <?php echo icon('clock', '', 14); ?> View Payment History
      </a>
    </div>
  </div>

  <?php if (empty($dues)): ?>
    <div style="text-align: center; padding: 48px 20px; background: rgba(255,255,255,0.01); border-radius: 8px;">
      <div style="font-size: 32px; color: var(--text-muted); margin-bottom: 8px;"><?php echo icon('shield_check', '', 36); ?></div>
      <h3 style="font-size: 16px; margin: 0 0 6px 0;">No Active Dues Assigned</h3>
      <p class="muted" style="font-size: 13px; max-width: 480px; margin: 0 auto;">
        You currently have no outstanding chapter dues or obligations assigned to your account.
      </p>
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th style="min-width: 140px;">Due Period</th>
            <th style="min-width: 200px;">Due / Description</th>
            <th style="text-align: right; min-width: 110px;">Other Obligations</th>
            <th style="text-align: right; min-width: 100px;">Discount</th>
            <th style="text-align: right; min-width: 110px;">Balance</th>
            <th style="min-width: 120px;">Due Date</th>
            <th style="min-width: 110px;">Status</th>
            <th style="text-align: right; min-width: 120px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dues as $d):
            $baseAmount = round((float)$d['amount'], 2);
            $disc = round((float)($d['discount_amount'] ?? 0), 2);
            $otherOblig = round((float)($d['other_obligations'] ?? 0), 2);
            $paidAmount = round((float)$d['total_paid'], 2);
            $netTotal = max(0, round($baseAmount - $disc + $otherOblig, 2));
            $balance = max(0, round($netTotal - $paidAmount, 2));

            $duePeriod = !empty($d['due_period']) ? $d['due_period'] : (!empty($d['term']) ? $d['term'] : 'FY 2026 - 2027');
            $isPaid = ($balance <= 0 && $d['status'] === 'paid');
            $isUnpaid = ($d['status'] === 'unpaid' || ($balance > 0 && $d['status'] !== 'paid' && $d['status'] !== 'pending'));
          ?>
            <tr>
              <!-- 1. Due Period -->
              <td>
                <span class="badge" style="background: rgba(245,158,11,0.12); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); font-size: 11px; font-weight: 600;">
                  <?php echo htmlspecialchars($duePeriod); ?>
                </span>
              </td>

              <!-- 2. Due / Title & Description -->
              <td>
                <strong style="color: var(--text-primary); font-size: 13.5px;"><?php echo htmlspecialchars($d['title']); ?></strong>
                <div style="font-size: 11.5px; color: var(--text-secondary); margin-top: 2px;">
                  Base Due: <strong>₱<?php echo number_format($baseAmount, 2); ?></strong>
                  <?php if (!empty($d['description'])): ?>
                    &bull; <?php echo htmlspecialchars($d['description']); ?>
                  <?php endif; ?>
                </div>
                <?php if ($d['payment_type'] === 'partial' && $d['installment_months']): ?>
                  <span class="badge-pill badge-partial" style="font-size: 10px; margin-top: 4px; display: inline-block;">
                    <?php echo (int)$d['installment_months']; ?>-month installment plan
                  </span>
                <?php endif; ?>
              </td>

              <!-- 3. Other Obligations -->
              <td style="text-align: right;">
                <?php if ($otherOblig > 0): ?>
                  <span style="color: #8b5cf6; font-weight: 600;">+₱<?php echo number_format($otherOblig, 2); ?></span>
                <?php else: ?>
                  <span class="muted">₱0.00</span>
                <?php endif; ?>
              </td>

              <!-- 4. Discounts -->
              <td style="text-align: right;">
                <?php if ($disc > 0): ?>
                  <span style="color: #10b981; font-weight: 600;">-₱<?php echo number_format($disc, 2); ?></span>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>

              <!-- 5. Balance -->
              <td style="text-align: right;">
                <strong style="font-size: 14px; color: <?php echo $balance > 0 ? '#ef4444' : '#10b981'; ?>;">
                  ₱<?php echo number_format($balance, 2); ?>
                </strong>
                <?php if ($paidAmount > 0): ?>
                  <div style="font-size: 10.5px; color: #10b981;">Paid: ₱<?php echo number_format($paidAmount, 2); ?></div>
                <?php endif; ?>
              </td>

              <!-- 6. Due Date -->
              <td>
                <?php if (!empty($d['due_date'])): ?>
                  <?php
                    $dueTime = strtotime($d['due_date']);
                    $isOverdue = ($balance > 0 && $dueTime < time());
                  ?>
                  <span style="<?php echo $isOverdue ? 'color: #ef4444; font-weight: 600;' : ''; ?>">
                    <?php echo date('M d, Y', $dueTime); ?>
                  </span>
                  <?php if ($isOverdue): ?>
                    <span class="badge" style="background: rgba(239,68,68,0.12); color: #ef4444; font-size: 9.5px; padding: 1px 4px;">Overdue</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>

              <!-- 7. Status (paid, unpaid, partial, pending) -->
              <td>
                <?php if ($d['status'] === 'paid' || $balance <= 0): ?>
                  <span class="badge" style="background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981;">
                    <?php echo icon('check', '', 12); ?> Paid
                  </span>
                <?php elseif ($d['status'] === 'pending'): ?>
                  <span class="badge" style="background: rgba(59,130,246,0.15); color: #3b82f6; border: 1px solid #3b82f6;">
                    <?php echo icon('clock', '', 12); ?> Under Verification
                  </span>
                <?php elseif ($d['status'] === 'partial'): ?>
                  <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid #f59e0b;">
                    <?php echo icon('clock', '', 12); ?> Partially Paid
                  </span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid #ef4444;">
                    <?php echo icon('alert', '', 12); ?> Unpaid
                  </span>
                <?php endif; ?>
              </td>

              <!-- 8. Action (Pay Now) -->
              <td style="text-align: right;">
                <?php if ($balance > 0): ?>
                  <a href="pay.php?member_due_id=<?php echo $d['member_due_id']; ?>" class="btn btn-primary" style="font-size: 12px; padding: 6px 12px; white-space: nowrap;">
                    <?php echo icon('wallet', '', 14); ?> Pay Now
                  </a>
                <?php else: ?>
                  <a href="history.php" class="btn btn-secondary" style="font-size: 11.5px; padding: 4px 10px; white-space: nowrap; opacity: 0.85;">
                    <?php echo icon('check', '', 12); ?> Settled
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

<!-- SETTLEMENT NOTICE AND POLICIES -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-top: 20px;">
  <div class="card" style="border-left: 4px solid var(--accent-primary);">
    <h3 style="font-size: 14px; margin: 0 0 8px 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
      <?php echo icon('shield_check', '', 16); ?>
      Payment &amp; Good Standing Policy
    </h3>
    <p style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.5; margin: 0;">
      Settlement of annual chapter dues and obligations is required to maintain your certificate of <strong>Good Standing</strong>. Upon payment submission via GCash, Maya, or Bank Transfer, your reference number and proof of payment will be verified by the chapter treasurer within 24 to 48 hours.
    </p>
  </div>

  <div class="card" style="border-left: 4px solid #10b981;">
    <h3 style="font-size: 14px; margin: 0 0 8px 0; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
      <?php echo icon('dues', '', 16); ?>
      Official Receipts &amp; Installments
    </h3>
    <p style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.5; margin: 0;">
      Once a payment is verified, an official cryptographic digital receipt (with QR verification hash) is instantly generated and archived under your <a href="history.php" style="color: var(--accent-primary); text-decoration: underline;">Payment History</a>. Installment plans allow splitting dues over approved monthly schedules.
    </p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

