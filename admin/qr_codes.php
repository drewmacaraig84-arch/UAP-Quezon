<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $method = $_POST['method'] ?? '';
    $allowed_methods = ['gcash', 'maya', 'bank'];

    if (!in_array($method, $allowed_methods)) {
        $error = 'Invalid payment method.';
    } elseif (!isset($_FILES['qr_image']) || $_FILES['qr_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose a QR code image to upload.';
    } else {
        $ext = strtolower(pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['qr_image']['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
            $error = 'Only valid JPG, PNG, or WebP images are allowed.';
        } else {
            $filename = 'qr_' . $method . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $qrDir = __DIR__ . '/../uploads/qr_codes';
            $rootUploads = __DIR__ . '/../uploads';

            if (!is_dir($qrDir)) @mkdir($qrDir, 0775, true);
            if (!is_dir($rootUploads)) @mkdir($rootUploads, 0775, true);

            $destQr = $qrDir . '/' . $filename;
            $destRoot = $rootUploads . '/' . $filename;

            if (move_uploaded_file($_FILES['qr_image']['tmp_name'], $destQr)) {
                @copy($destQr, $destRoot);
                $path = 'uploads/qr_codes/' . $filename;

                $stmt = $pdo->prepare("
                    INSERT INTO qr_codes (method, image_path) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE image_path = VALUES(image_path), updated_at = NOW()
                ");
                $stmt->execute([$method, $path]);

                if (function_exists('set_flash')) {
                    set_flash('success', strtoupper($method) . ' QR code updated successfully.');
                }
                header('Location: qr_codes.php');
                exit;
            } else {
                $error = 'Failed to upload QR image.';
            }
        }
    }
}

$qrcodes = $pdo->query("SELECT * FROM qr_codes")->fetchAll();
$existing = [];
foreach ($qrcodes as $q) { $existing[$q['method']] = $q; }

$labels = [
    'gcash' => ['title' => 'GCash Merchant QR', 'desc' => 'Instant mobile wallet scan and pay for GCash users.'],
    'maya' => ['title' => 'Maya / PayMaya QR', 'desc' => 'QR Ph compatible QR code for Maya wallet and bank transfers.'],
    'bank' => ['title' => 'Direct Bank Account / QR Ph', 'desc' => 'Official chapter bank account details and deposit instructions.']
];

$page_title = 'Payment QR Codes • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">PAYMENT GATEWAYS</p>
    <h1>Payment QR Codes &amp; Channels</h1>
    <p class="page-subtitle">Configure chapter payment QR codes displayed on member payment portals.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('qr_codes', '', 14); ?> <span>Payment Channels</span>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-error">
    <div style="display:flex;align-items:center;gap:8px;">
      <?php echo icon('alert', '', 18); ?>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
  </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
  <?php foreach ($labels as $method => $info): ?>
    <div class="card" style="margin: 0; display: flex; flex-direction: column;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
          <?php echo icon('qr_codes', '', 18); ?>
        </div>
        <div>
          <h2 style="font-size: 16px; margin: 0;"><?php echo $info['title']; ?></h2>
          <p class="muted" style="font-size: 12px; margin: 2px 0 0;"><?php echo $info['desc']; ?></p>
        </div>
      </div>

      <div style="margin: 14px 0; padding: 16px; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 220px;">
        <?php 
          $qrUrl = isset($existing[$method]) ? media_url($existing[$method]['image_path']) : null;
          if ($qrUrl): 
        ?>
          <div style="background: #fff; padding: 8px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 12px;">
            <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="<?php echo $info['title']; ?>" style="max-width: 180px; max-height: 180px; display: block; object-fit: contain;" onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>/uploads/<?php echo basename($existing[$method]['image_path']); ?>';">
          </div>
          <span class="muted" style="font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px;">
            <?php echo icon('clock', '', 11); ?> Updated: <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($existing[$method]['updated_at']))); ?>
          </span>
        <?php else: ?>
          <div style="color: var(--text-secondary); text-align: center;">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(0,0,0,0.05); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 8px;">
              <?php echo icon('image', '', 20); ?>
            </div>
            <strong style="display: block; font-size: 13px; color: var(--text-primary);">No QR code uploaded</strong>
            <p class="muted" style="font-size: 12px; margin: 2px 0 0;">Upload a QR image below.</p>
          </div>
        <?php endif; ?>
      </div>

      <form method="post" enctype="multipart/form-data" style="margin-top: auto;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="method" value="<?php echo $method; ?>">
        <div class="field" style="margin-bottom: 12px;">
          <input type="file" name="qr_image" accept=".jpg,.jpeg,.png,.webp" required style="font-size: 12px;">
        </div>
        <button class="btn btn-sm" type="submit" style="width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
          <?php echo icon('upload', '', 14); ?> <span><?php echo isset($existing[$method]) ? 'Replace QR Code' : 'Upload QR Code'; ?></span>
        </button>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
