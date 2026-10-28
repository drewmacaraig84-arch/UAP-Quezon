<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Get current admin password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([current_user_id()]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($current, $admin['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, current_user_id()]);
        $success = 'Password changed successfully.';
    }
}

$page_title = 'Change Password • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero" style="max-width: 480px; margin: 0 auto 24px auto;">
  <div>
    <p class="eyebrow">SECURITY &amp; CREDENTIALS</p>
    <h1>Change Admin Password</h1>
    <p class="page-subtitle">Update your administrative access credentials.</p>
  </div>
</div>

<div class="card" style="max-width: 480px; margin: 0 auto;">
  <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px;">
    <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
      <?php echo icon('key', '', 18); ?>
    </div>
    <h2 style="font-size: 16px; margin: 0;">Update Password</h2>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error">
      <div style="display: flex; align-items: center; gap: 8px;">
        <?php echo icon('alert', '', 18); ?>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <div style="display: flex; align-items: center; gap: 8px;">
        <?php echo icon('check', '', 18); ?>
        <span><?php echo htmlspecialchars($success); ?></span>
      </div>
    </div>
  <?php endif; ?>

  <form method="post">
    <?php echo csrf_field(); ?>
    <div class="field">
      <label>Current Password</label>
      <input type="password" name="current_password" required placeholder="Enter current password">
    </div>
    <div class="field">
      <label>New Password</label>
      <input type="password" name="new_password" required placeholder="At least 6 characters">
    </div>
    <div class="field" style="margin-bottom: 20px;">
      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" required placeholder="Re-type new password">
    </div>
    <button class="btn" type="submit" style="width:100%; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
      <?php echo icon('key', '', 14); ?> <span>Update Password</span>
    </button>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
