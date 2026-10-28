<?php
require_once __DIR__ . '/../includes/auth.php';
require_member();

$member_due_id = (int)($_GET['member_due_id'] ?? 0);
if ($member_due_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT md.*, 
           COALESCE(md.custom_title, d.title) as title, 
           COALESCE(md.custom_description, d.description) as description, 
           COALESCE(md.custom_amount, d.amount) as amount, 
           COALESCE(md.custom_due_date, d.due_date) as due_date
    FROM member_dues md
    JOIN dues d ON md.due_id = d.id
    WHERE md.id = ? AND md.user_id = ?
");
$stmt->execute([$member_due_id, current_user_id()]);
$due = $stmt->fetch();

if (!$due) {
    if (function_exists('set_flash')) {
        set_flash('error', 'Due record not found.');
    }
    header('Location: dashboard.php');
    exit;
}

$paid_stmt = $pdo->prepare("SELECT SUM(amount_paid) as total FROM payments WHERE member_due_id = ? AND status = 'verified'");
$paid_stmt->execute([$member_due_id]);
$already_paid = round((float)($paid_stmt->fetch()['total'] ?? 0), 2);
$discount = round((float)($due['discount_amount'] ?? 0), 2);
$otherObligations = round((float)($due['other_obligations'] ?? 0), 2);
$effectiveTotal = max(0, round((float)$due['amount'] - $discount + $otherObligations, 2));
$remaining = max(0, round($effectiveTotal - $already_paid, 2));

// Check for existing pending payment
$pendingStmt = $pdo->prepare("SELECT id FROM payments WHERE member_due_id = ? AND status = 'pending' LIMIT 1");
$pendingStmt->execute([$member_due_id]);
$hasPendingPayment = (bool)$pendingStmt->fetch();

// Count VERIFIED payments only
$paymentCountStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM payments
    WHERE member_due_id = ?
    AND status IN ('pending','verified')
");
$paymentCountStmt->execute([$member_due_id]);
$verifiedPayments = (int)$paymentCountStmt->fetchColumn();

$halfAmount = round($effectiveTotal / 2, 2);

// Load QR codes
$qrcodes = $pdo->query("SELECT * FROM qr_codes")->fetchAll();
$qr_by_method = [];
foreach ($qrcodes as $q) { $qr_by_method[$q['method']] = $q['image_path']; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($hasPendingPayment) {
        if (function_exists('set_flash')) {
            set_flash('warning', 'You already have a payment proof pending verification. Please wait for the admin to verify before submitting again.');
        }
        header("Location: dashboard.php");
        exit;
    }

    $method = $_POST['method'] ?? '';
    $reference_number = trim($_POST['reference_number'] ?? '');
    $payment_type = $_POST['payment_type'] ?? '';

    switch ($payment_type) {
        case "full":
            $amount_paid = ($already_paid > 0) ? $remaining : (float)$due['amount'];
            break;
        case "first_half":
            $amount_paid = $halfAmount;
            break;
        case "second_half":
            $amount_paid = $remaining;
            break;
        default:
            $amount_paid = 0;
    }

    $error = '';

    if ($reference_number === '') {
        $error = 'Please provide a transaction or reference number.';
    } elseif ($amount_paid <= 0) {
        $error = 'Invalid payment amount.';
    } elseif ($amount_paid > ($remaining + 0.01)) {
        $error = 'Amount exceeds remaining balance of ₱' . number_format($remaining, 2) . '.';
    } elseif ($verifiedPayments > 0 && $payment_type === "first_half") {
        $error = "The first tranche has already been submitted.";
    } else {
        // Check duplicate reference number for this due
        $refCheck = $pdo->prepare("SELECT id FROM payments WHERE member_due_id = ? AND reference_number = ? AND status != 'rejected' LIMIT 1");
        $refCheck->execute([$member_due_id, $reference_number]);
        if ($refCheck->fetch()) {
            $error = 'A payment with this transaction reference number has already been recorded for this due.';
        } elseif (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
            $uploadErr = $_FILES['proof']['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) {
                $error = 'The uploaded file exceeds the allowed server limit.';
            } else {
                $error = 'Please upload proof of payment.';
            }
        } elseif ($_FILES['proof']['size'] > 25 * 1024 * 1024) {
            $error = 'Proof file is too large. Maximum allowed size is 25MB.';
        } else {
            $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['proof']['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

            if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
                $error = 'Invalid file type. Only JPG, JPEG, PNG, WEBP and PDF documents are allowed.';
            } else {
                $filename = 'proof_' . $member_due_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = __DIR__ . '/../uploads/' . $filename;

                if (!move_uploaded_file($_FILES['proof']['tmp_name'], $destination)) {
                    $error = 'Failed to save uploaded file. Please try again.';
                } else {
                    $proof_path = 'uploads/' . $filename;

                    switch ($payment_type) {
                        case "full":
                            $inst_num = 0;
                            break;
                        case "first_half":
                            $inst_num = 1;
                            break;
                        case "second_half":
                            $inst_num = 2;
                            break;
                        default:
                            $inst_num = null;
                    }

                    try {
                        $pdo->beginTransaction();

                        $stmt = $pdo->prepare("
                            INSERT INTO payments
                            (
                                member_due_id,
                                amount_paid,
                                method,
                                reference_number,
                                proof_image,
                                installment_number,
                                status
                            )
                            VALUES
                            (?, ?, ?, ?, ?, ?, 'pending')
                        ");

                        $stmt->execute([
                            $member_due_id,
                            $amount_paid,
                            $method,
                            $reference_number,
                            $proof_path,
                            $inst_num
                        ]);

                        $stmt = $pdo->prepare("
                            UPDATE member_dues
                            SET status = 'pending',
                                payment_type = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $payment_type,
                            $member_due_id
                        ]);

                        $pdo->commit();

                        if (function_exists('set_flash')) {
                            set_flash('success', 'Payment submitted successfully! Please wait for admin verification.');
                        }
                        header("Location: dashboard.php");
                        exit;
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        @unlink($destination);
                        $error = 'A database error occurred while submitting payment. Please try again.';
                    }
                }
            }
        }
    }

    // Always redirect on error using PRG pattern to prevent browser "Confirm Form Resubmission"
    if ($error) {
        if (function_exists('set_flash')) {
            set_flash('error', $error);
        }
        header("Location: pay.php?member_due_id=" . $member_due_id);
        exit;
    }
}

$page_title = 'Submit Payment';
include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:560px;margin:0 auto;">
  <h1>Pay: <?php echo htmlspecialchars($due['title']); ?></h1>

  <?php if (!empty($due['description'])): ?>
    <p class="muted"><?php echo htmlspecialchars($due['description']); ?></p>
  <?php endif; ?>

  <?php if ($hasPendingPayment): ?>
    <div class="alert alert-warning" style="margin-top:16px;">
      <strong>⏳ Payment Pending Verification:</strong> You have already submitted a payment proof for this due. The admin is currently reviewing your transaction. You will be notified once it is verified.
    </div>
    <div style="margin-top:20px; text-align:center;">
      <a href="dashboard.php" class="btn">Return to My Dues</a>
    </div>
  <?php else: ?>
    <!-- Payment submission form -->
    <form method="post" enctype="multipart/form-data" style="margin-top:20px;" id="paymentForm" onsubmit="return handlePaymentSubmit(this);">
      <?php echo csrf_field(); ?>

      <!-- Payment Option -->
      <div class="field">
        <label>Payment Option</label>
        <select name="payment_type" id="paymentType" onchange="updatePaymentAmount()">
          <?php if ($verifiedPayments == 0): ?>
            <option value="full">Full Payment</option>
            <option value="first_half">First Tranche (50%)</option>
          <?php else: ?>
            <option value="second_half">Second Tranche (Remaining Balance)</option>
          <?php endif; ?>
        </select>
      </div>

      <!-- Amount to pay -->
      <div class="field">
        <label>Payment Amount</label>
        <input
          type="text"
          id="paymentAmount"
          readonly
          class="form-control"
          style="
            background:var(--field-bg, rgba(0,0,0,0.25));
            border:1px solid var(--border-color, rgba(255,255,255,0.15));
            font-size:22px;
            font-weight:800;
            text-align:center;
            color:var(--accent-primary, #f5b800);
            cursor:not-allowed;
            width:100%;
            padding:12px;
            border-radius:8px;
          ">
        <input type="hidden" id="paymentAmountHidden" name="amount_paid">
      </div>

      <!-- Payment method -->
      <div class="field">
        <label>Payment Method</label>
        <select name="method" id="methodSelect" required onchange="updateQR()">
          <option value="gcash">GCash</option>
          <option value="maya">Maya</option>
          <option value="card">Visa / Mastercard</option>
          <option value="bank">Online Banking</option>
        </select>
      </div>

      <!-- QR code display -->
      <div id="qrBox" style="text-align:center;margin-bottom:16px;display:none;">
        <p class="muted" style="margin-bottom:6px;">Scan to pay:</p>
        <img id="qrImage" src="" alt="Payment QR Code" style="max-width:220px;border:1px solid var(--border-color, rgba(255,255,255,0.12));border-radius:8px;background:rgba(255,255,255,0.05);padding:6px;">
      </div>
      <div id="cardNotice" class="alert alert-info" style="display:none;">
        Pay using your card via the org's payment terms, then enter the transaction reference below.
      </div>
      <div id="noQrNotice" class="alert alert-warning" style="display:none;">
        No QR code uploaded yet for this method. Coordinate payment details with the admin.
      </div>

      <div class="field">
        <label>Reference / Transaction Number</label>
        <input name="reference_number" required placeholder="e.g. 100234857291">
      </div>

      <div class="field">
        <label>Proof of Payment (JPG, PNG, WEBP, PDF up to 25MB)</label>
        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
      </div>

      <button class="btn btn-success" type="submit" id="submitBtn" style="width:100%; padding:12px; font-weight:800; font-size:15px;">
        Submit Payment &rarr;
      </button>
    </form>
  <?php endif; ?>
</div>

<script>
if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}

let isSubmitting = false;
function handlePaymentSubmit(form) {
  if (isSubmitting) return false;
  isSubmitting = true;
  const btn = document.getElementById('submitBtn');
  if (btn) {
    btn.disabled = true;
    btn.innerText = 'Submitting Payment... Please wait';
    btn.style.opacity = '0.75';
    btn.style.cursor = 'not-allowed';
  }
  return true;
}

const qrImages = {
  gcash: <?php echo !empty($qr_by_method['gcash']) ? json_encode(media_url($qr_by_method['gcash'])) : 'null'; ?>,
  maya: <?php echo !empty($qr_by_method['maya']) ? json_encode(media_url($qr_by_method['maya'])) : 'null'; ?>,
  bank: <?php echo !empty($qr_by_method['bank']) ? json_encode(media_url($qr_by_method['bank'])) : 'null'; ?>
};

function updateQR() {
  const method = document.getElementById('methodSelect').value;
  document.getElementById('qrBox').style.display = 'none';
  document.getElementById('cardNotice').style.display = 'none';
  document.getElementById('noQrNotice').style.display = 'none';
  if (method === 'card') {
    document.getElementById('cardNotice').style.display = 'block';
  } else if (qrImages[method]) {
    document.getElementById('qrImage').src = qrImages[method];
    document.getElementById('qrBox').style.display = 'block';
  } else {
    document.getElementById('noQrNotice').style.display = 'block';
  }
}

updateQR();
const total = <?php echo (float)$due['amount']; ?>;
const half = <?php echo (float)$halfAmount; ?>;
const remaining = <?php echo (float)$remaining; ?>;

function updatePaymentAmount() {
  const selectElem = document.getElementById("paymentType");
  if (!selectElem) return;
  let option = selectElem.value;
  let amount = 0;
  if (option == "full") amount = total;
  if (option == "first_half") amount = half;
  if (option == "second_half") amount = remaining;

  document.getElementById("paymentAmount").value = "₱" + amount.toFixed(2);
  document.getElementById("paymentAmountHidden").value = amount;
}

updatePaymentAmount();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
