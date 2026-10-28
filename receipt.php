<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';
require_login();

$payment_id = (int)($_GET['payment_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, r.receipt_number, r.issued_at, u.name as member_name, u.id_number, 
           COALESCE(md.custom_title, d.title) as due_title,
           COALESCE(md.custom_amount, d.amount) as due_total_amount
    FROM payments p
    JOIN receipts r ON r.payment_id = p.id
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$r = $stmt->fetch();

if (!$r) {
    die('Receipt not found.');
}

// Members can only view their own receipt; admins can view any
if ($_SESSION['role'] === 'member') {
    $check = $pdo->prepare("SELECT u.id FROM payments p JOIN member_dues md ON p.member_due_id = md.id JOIN users u ON md.user_id = u.id WHERE p.id = ?");
    $check->execute([$payment_id]);
    if ($check->fetchColumn() != current_user_id()) {
        die('Not authorized.');
    }
}

$backUrl = $_SESSION['role'] === 'admin' ? BASE_URL . '/admin/reports.php' : BASE_URL . '/member/history.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Official Receipt <?php echo htmlspecialchars($r['receipt_number']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  body {
    font-family: 'Inter', sans-serif;
    background: #f1f5f9;
    padding: 30px 15px;
    margin: 0;
    color: #1e293b;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .receipt-card {
    background: #ffffff;
    max-width: 580px;
    width: 100%;
    border-radius: 12px;
    padding: 36px 32px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    position: relative;
  }
  .receipt-header {
    text-align: center;
    border-bottom: 2px solid #1b2e4b;
    padding-bottom: 18px;
    margin-bottom: 24px;
  }
  .receipt-logo {
    width: 60px;
    height: 60px;
    object-fit: contain;
    margin-bottom: 8px;
  }
  .org-title {
    font-family: 'Cinzel', serif;
    font-size: 15px;
    font-weight: 700;
    color: #1b2e4b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .org-sub {
    font-size: 12px;
    color: #64748b;
  }
  .receipt-title {
    font-size: 18px;
    font-weight: 800;
    color: #1b2e4b;
    margin-top: 14px;
    letter-spacing: 1px;
  }
  .receipt-num {
    font-size: 15px;
    font-weight: 700;
    color: #d4af37;
    margin-top: 2px;
  }
  .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
  }
  .label {
    color: #64748b;
    font-weight: 500;
  }
  .val {
    font-weight: 600;
    color: #1e293b;
    text-align: right;
  }
  .total-box {
    background: #fafaf9;
    border-radius: 8px;
    padding: 14px 18px;
    margin-top: 18px;
    border: 1px dashed #cbd5e1;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .total-val {
    font-size: 22px;
    font-weight: 800;
    color: #1b2e4b;
  }
  .footer-note {
    margin-top: 24px;
    text-align: center;
    color: #94a3b8;
    font-size: 11.5px;
    line-height: 1.5;
  }
  .action-buttons {
    margin-top: 20px;
    display: flex;
    gap: 12px;
  }
  .btn {
    padding: 9px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: 0.2s;
  }
  .btn-print { background: #1b2e4b; color: #fff; }
  .btn-print:hover { background: #2a4365; }
  .btn-back { background: #e2e8f0; color: #334155; }
  .btn-back:hover { background: #cbd5e1; }
  @media print {
    body { background: #fff; padding: 0; }
    .action-buttons { display: none; }
    .receipt-card { box-shadow: none; border: none; padding: 10px; width: 100%; max-width: 100%; }
  }
</style>
</head>
<body>

<div class="receipt-card">
  <div class="receipt-header">
    <img src="<?php echo BASE_URL; ?>/public/logo.jpg" alt="UAP Logo" class="receipt-logo" onerror="if(this.src.indexOf('uploads/logo.jpg')===-1)this.src='<?php echo BASE_URL; ?>/uploads/logo.jpg';">
    <div class="org-title">United Architects of the Philippines</div>
    <div class="org-sub">Quezon Chapter • Official Receipt</div>
    <div class="receipt-title">OFFICIAL RECEIPT</div>
    <div class="receipt-num"><?php echo htmlspecialchars($r['receipt_number']); ?></div>
  </div>

  <div class="row">
    <span class="label">Member Name</span>
    <span class="val"><?php echo htmlspecialchars($r['member_name']); ?></span>
  </div>
  <div class="row">
    <span class="label">PRC ID No.</span>
    <span class="val"><?php echo htmlspecialchars($r['id_number']); ?></span>
  </div>
  <div class="row">
    <span class="label">Obligation / Due</span>
    <span class="val"><?php echo htmlspecialchars($r['due_title']); ?></span>
  </div>
  <div class="row">
    <span class="label">Payment Method</span>
    <span class="val"><?php echo strtoupper(htmlspecialchars($r['method'])); ?></span>
  </div>
  <div class="row">
    <span class="label">Reference No.</span>
    <span class="val"><code><?php echo htmlspecialchars($r['reference_number'] ?: '—'); ?></code></span>
  </div>
  <div class="row">
    <span class="label">Date Issued</span>
    <span class="val"><?php echo htmlspecialchars(date('F d, Y h:i A', strtotime($r['issued_at']))); ?></span>
  </div>

  <div class="total-box">
    <span class="label" style="font-size: 15px; font-weight: 700; color: #1b2e4b;">Amount Paid</span>
    <span class="total-val">₱<?php echo number_format($r['amount_paid'], 2); ?></span>
  </div>

  <div class="footer-note">
    This is an electronically generated official receipt issued by the UAP Quezon Chapter dues portal. No signature is required.
  </div>
</div>

<div class="action-buttons">
  <button class="btn btn-print" onclick="window.print()" style="display:inline-flex; align-items:center; justify-content:center; gap:6px;">
    <?php echo icon('printer', '', 16); ?> <span>Print Receipt</span>
  </button>
  <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-back">← Back</a>
</div>

</body>
</html>
