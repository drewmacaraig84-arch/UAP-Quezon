<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$userId = current_user_id();
$error = '';
$success = '';

// Fetch current admin user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("User account not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Update Profile Details & Photo
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $idNumber = trim($_POST['id_number'] ?? '');
        $removePhoto = !empty($_POST['remove_photo']);

        if (empty($name) || empty($idNumber)) {
            $error = 'Display name and username/ID number are required.';
        } else {
            // Check username uniqueness
            $chk = $pdo->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $chk->execute([$idNumber, $userId]);
            if ($chk->fetch()) {
                $error = 'That Username / ID number is already in use by another account.';
            } else {
                $photoPath = $user['profile_photo'] ?? null;

                // Handle Photo Removal
                if ($removePhoto && !empty($photoPath)) {
                    $fileOnDisk = __DIR__ . '/../' . ltrim($photoPath, '/');
                    if (file_exists($fileOnDisk) && !is_dir($fileOnDisk)) {
                        @unlink($fileOnDisk);
                    }
                    $photoPath = null;
                }

                // Handle New Photo Upload
                if (!empty($_FILES['profile_photo']['name'])) {
                    if ($_FILES['profile_photo']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['profile_photo']['error'] === UPLOAD_ERR_FORM_SIZE) {
                        $error = 'Photo file size exceeds the server upload limit.';
                    } elseif ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
                        $error = 'Failed to upload photo (error code: ' . $_FILES['profile_photo']['error'] . '). Please try again.';
                    } else {
                        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                        $fileTmp = $_FILES['profile_photo']['tmp_name'];
                        $fileSize = $_FILES['profile_photo']['size'];
                        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));

                        if (!in_array($ext, $allowedExts)) {
                            $error = 'Invalid photo format. Please upload JPG, PNG, or WebP.';
                        } elseif ($fileSize > 25 * 1024 * 1024) {
                            $error = 'Photo file size exceeds 25MB limit.';
                        } else {
                            $uploadDir = __DIR__ . '/../uploads/avatars/';
                            if (!is_dir($uploadDir)) {
                                @mkdir($uploadDir, 0775, true);
                            }

                            $uniqueFilename = 'avatar_admin_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                            $targetPath = $uploadDir . $uniqueFilename;

                            if (move_uploaded_file($fileTmp, $targetPath)) {
                                @chmod($targetPath, 0664);
                                // Clean old photo if exists
                                if (!empty($user['profile_photo'])) {
                                    $oldFile = __DIR__ . '/../' . ltrim($user['profile_photo'], '/');
                                    if (file_exists($oldFile) && !is_dir($oldFile)) {
                                        @unlink($oldFile);
                                    }
                                }
                                $photoPath = 'uploads/avatars/' . $uniqueFilename;
                            } else {
                                $error = 'Failed to save uploaded photo to disk. Please check storage permissions.';
                            }
                        }
                    }
                }

                if (empty($error)) {
                    $upd = $pdo->prepare("UPDATE users SET name = ?, id_number = ?, profile_photo = ? WHERE id = ?");
                    $upd->execute([$name, $idNumber, $photoPath, $userId]);
                    
                    $_SESSION['name'] = $name;
                    $success = 'Profile details and photo updated successfully!';
                    
                    // Refresh user data
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                }
            }
        }
    }

    // Action 2: Change Password
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            $error = 'Current and new password are required.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error = 'Incorrect current password.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $pStmt->execute([$hash, $userId]);
            $success = 'Password changed successfully.';
        }
    }
}

$page_title = 'My Profile • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';

$avatarUrl = !empty($user['profile_photo']) ? BASE_URL . '/' . ltrim($user['profile_photo'], '/') : null;
$initials = strtoupper(substr($user['name'], 0, 1) . substr(strrchr($user['name'], ' ') ?: $user['name'], 1, 1));
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">ADMINISTRATOR ACCOUNT</p>
    <h1>My Profile</h1>
    <p class="page-subtitle">Manage your administrator credentials, personal profile picture, and account security.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('shield_check', '', 14); ?> <span>Master Administrator</span>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger" style="margin-bottom: 20px;">
    <?php echo icon('alert', '', 18); ?>
    <span><?php echo htmlspecialchars($error); ?></span>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success" style="margin-bottom: 20px;">
    <?php echo icon('check', '', 18); ?>
    <span><?php echo htmlspecialchars($success); ?></span>
  </div>
<?php endif; ?>

<div class="profile-grid-layout">
  <!-- LEFT: AVATAR & ACCOUNT SUMMARY -->
  <div class="profile-avatar-card">
    <div class="profile-avatar-large-wrap">
      <?php if ($avatarUrl): ?>
        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" class="profile-avatar-large-img" id="avatarPreviewImg">
      <?php else: ?>
        <div class="profile-avatar-large-placeholder" id="avatarPreviewPlaceholder">
          <?php echo htmlspecialchars($initials); ?>
        </div>
      <?php endif; ?>
    </div>

    <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
      <?php echo htmlspecialchars($user['name']); ?>
    </h3>
    <span class="badge" style="background: rgba(245,158,11,0.15); color: var(--accent-primary); border: 1px solid var(--accent-primary); margin-bottom: 16px;">
      ADMINISTRATOR
    </span>

    <div style="width: 100%; border-top: 1px solid var(--border-color); padding-top: 16px; font-size: 12px; color: var(--text-secondary); text-align: left;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
        <span>Username:</span>
        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($user['id_number']); ?></strong>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
        <span>Account Role:</span>
        <strong style="color: var(--text-primary);">Super Admin</strong>
      </div>
      <div style="display: flex; justify-content: space-between;">
        <span>Member Since:</span>
        <strong style="color: var(--text-primary);"><?php echo date('M Y', strtotime($user['created_at'])); ?></strong>
      </div>
    </div>
  </div>

  <!-- RIGHT: EDIT PROFILE FORM & PASSWORD CHANGE -->
  <div style="display: flex; flex-direction: column; gap: 24px;">
    <!-- Profile Info Form -->
    <div class="card" style="padding: 24px;">
      <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 18px; display: flex; align-items: center; gap: 8px;">
        <?php echo icon('user', '', 18); ?> Profile Details &amp; Picture
      </h3>

      <form method="post" enctype="multipart/form-data" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="form-group" style="margin-bottom: 20px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">
            Profile Picture (Avatar)
          </label>
          <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/png, image/jpeg, image/webp" class="form-control" style="flex: 1; min-width: 220px;" onchange="previewAvatar(this)">
            <?php if ($avatarUrl): ?>
              <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #ef4444; cursor: pointer;">
                <input type="checkbox" name="remove_photo" value="1">
                <span>Remove current photo</span>
              </label>
            <?php endif; ?>
          </div>
          <small class="form-text" style="color: var(--text-secondary); margin-top: 4px; display: block;">
            Supported formats: JPG, PNG, WebP (Max 5MB). Photo displays in the topbar and admin management.
          </small>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">Display Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">Username / ID Number</label>
            <input type="text" name="id_number" class="form-control" value="<?php echo htmlspecialchars($user['id_number']); ?>" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">
          <?php echo icon('check', '', 16); ?> Save Profile Changes
        </button>
      </form>
    </div>

    <!-- Password Change Form -->
    <div class="card" style="padding: 24px;">
      <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 18px; display: flex; align-items: center; gap: 8px;">
        <?php echo icon('key', '', 18); ?> Change Password
      </h3>

      <form method="post" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="change_password">

        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">Current Password</label>
          <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" minlength="6" required>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Re-type new password" minlength="6" required>
          </div>
        </div>

        <button type="submit" class="btn btn-secondary" style="padding: 10px 22px;">
          <?php echo icon('key', '', 16); ?> Update Password
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const wrap = document.querySelector('.profile-avatar-large-wrap');
      wrap.innerHTML = '<img src="' + e.target.result + '" alt="Avatar Preview" class="profile-avatar-large-img">';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
