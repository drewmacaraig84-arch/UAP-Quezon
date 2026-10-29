<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qr_helper.php';
require_member();

$userId = current_user_id();
$error = '';
$success = '';

// Fetch current member user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die("User account not found.");
}

// Fetch member directory record if exists
$wmStmt = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ?");
$wmStmt->execute([$userId]);
$wmRecord = $wmStmt->fetch();

$isGoodStanding = is_good_member($pdo, $userId);
$attendance = get_or_create_member_attendance($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: Update Profile Details & Photo
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $prc_number = trim($_POST['prc_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $civil_status = trim($_POST['civil_status'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $removePhoto = !empty($_POST['remove_photo']);

        if (empty($name)) {
            $error = 'Full Legal Name is required.';
        } else {
            $photoPath = $user['profile_photo'] ?? null;

            // Handle Photo Removal
            if ($removePhoto && !empty($photoPath)) {
                $fileOnDisk = __DIR__ . '/../' . ltrim($photoPath, '/');
                if (file_exists($fileOnDisk) && !is_dir($fileOnDisk)) {
                    @unlink($fileOnDisk);
                }
                $photoPath = null;

                // Sync removal with website_members if photo matches
                if ($wmRecord && $wmRecord['photo_path'] === $user['profile_photo']) {
                    $pdo->prepare("UPDATE website_members SET photo_path = NULL WHERE user_id = ?")->execute([$userId]);
                }
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

                        $uniqueFilename = 'avatar_member_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
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

                            // Sync portrait with website_members if record exists
                            if ($wmRecord) {
                                $pdo->prepare("UPDATE website_members SET photo_path = ? WHERE user_id = ?")->execute([$photoPath, $userId]);
                            }
                        } else {
                            $error = 'Failed to save uploaded photo to disk.';
                        }
                    }
                }
            }

            if (empty($error)) {
                // Calculate age dynamically
                $computedAge = null;
                if (!empty($birth_date)) {
                    try {
                        $dob = new DateTime($birth_date);
                        $today = new DateTime();
                        $computedAge = $dob->diff($today)->y;
                    } catch (Exception $e) {
                        $computedAge = null;
                    }
                }

                $upd = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, prc_number = ?, email = ?, phone = ?, gender = ?, birth_date = ?, age = ?, civil_status = ?, address = ?, profile_photo = ? 
                    WHERE id = ?
                ");
                $upd->execute([
                    $name,
                    $prc_number ?: null,
                    $email ?: null,
                    $phone ?: null,
                    $gender ?: null,
                    $birth_date ?: null,
                    $computedAge,
                    $civil_status ?: null,
                    $address ?: null,
                    $photoPath,
                    $userId
                ]);

                // Also sync name and prc_number with website_members
                if ($wmRecord) {
                    $pdo->prepare("UPDATE website_members SET name = ?, prc_number = COALESCE(?, prc_number) WHERE user_id = ?")->execute([$name, $prc_number ?: null, $userId]);
                }

                $_SESSION['name'] = $name;
                $success = 'Your profile details and credentials have been updated successfully!';

                // Refresh user and directory records
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                $wmStmt->execute([$userId]);
                $wmRecord = $wmStmt->fetch();
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

// Compute dynamic age for display
$userBirthDate = $user['birth_date'] ?? null;
$userAge = null;
if (!empty($userBirthDate)) {
    try {
        $dobObj = new DateTime($userBirthDate);
        $todayObj = new DateTime();
        $userAge = $dobObj->diff($todayObj)->y;
    } catch (Exception $e) {
        $userAge = null;
    }
}

$page_title = 'My Profile • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';

$currentPhoto = ($user['profile_photo'] ?? null) ?: ($wmRecord['photo_path'] ?? null);
$avatarUrl = $currentPhoto ? BASE_URL . '/' . ltrim($currentPhoto, '/') : null;
$initials = strtoupper(substr($user['name'], 0, 1) . substr(strrchr($user['name'], ' ') ?: $user['name'], 1, 1));
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">CHAPTER MEMBER PORTAL</p>
    <h1>My Profile</h1>
    <p class="page-subtitle">Manage your personal profile picture, chapter identity, demographic credentials, and account settings.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('members', '', 14); ?> <span>PRC #<?php echo htmlspecialchars($user['prc_number'] ?: $user['id_number']); ?></span>
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

<div class="profile-grid-layout" style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: start;">
  <!-- LEFT: 1x1 AVATAR & COMPLETE MEMBER BIO CARD -->
  <div class="profile-avatar-card" style="background: var(--card-bg, #1e293b); border: 1px solid var(--border-color, #334155); border-radius: 12px; padding: 24px; text-align: center;">
    <div class="profile-avatar-large-wrap" style="width: 140px; height: 140px; margin: 0 auto 16px; border-radius: 14px; overflow: hidden; border: 3px solid var(--accent-primary, #f59e0b); background: #0f172a; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
      <?php if ($avatarUrl): ?>
        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" class="profile-avatar-large-img" id="avatarPreviewImg" style="width: 100%; height: 100%; object-fit: cover;">
      <?php else: ?>
        <div class="profile-avatar-large-placeholder" id="avatarPreviewPlaceholder" style="font-size: 38px; font-weight: 800; color: #f59e0b;">
          <?php echo htmlspecialchars($initials); ?>
        </div>
      <?php endif; ?>
    </div>

    <span class="badge" style="display: inline-block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); padding: 3px 8px; border-radius: 4px; margin-bottom: 8px;">1x1 Official Portrait</span>

    <h3 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0 0 4px 0;">
      <?php echo htmlspecialchars($user['name']); ?>
    </h3>
    <p style="font-size: 13px; color: var(--accent-primary, #f59e0b); font-weight: 600; margin: 0 0 12px 0;">
      <?php echo !empty($wmRecord['role_title']) ? htmlspecialchars($wmRecord['role_title']) : 'Registered Architect'; ?>
    </p>

    <!-- Status Badges -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; margin-bottom: 18px;">
      <?php
        $standing = function_exists('get_member_standing_details') ? get_member_standing_details($pdo, $userId) : ['is_good' => $isGoodStanding, 'is_revoked' => false];
        if ($standing['is_revoked']):
      ?>
        <span class="badge" style="background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid #ef4444;" title="<?php echo htmlspecialchars($standing['reason'] ?? 'Standing Revoked'); ?>">
          <?php echo icon('alert', '', 12); ?> Standing Revoked
        </span>
      <?php elseif ($standing['is_good']): ?>
        <span class="badge" style="background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981;">
          <?php echo icon('shield_check', '', 12); ?> Good Standing
        </span>
      <?php else: ?>
        <span class="badge" style="background: rgba(245,158,11,0.15); color: var(--accent-primary); border: 1px solid var(--accent-primary);">
          <?php echo icon('clock', '', 12); ?> Dues Pending
        </span>
      <?php endif; ?>

      <a href="chapter_status.php" style="text-decoration:none;">
        <?php echo render_activity_badge($attendance); ?>
      </a>

      <?php if (!empty($wmRecord['is_published'])): ?>
        <span class="badge" style="background: rgba(59,130,246,0.15); color: #3b82f6; border: 1px solid #3b82f6;">
          <?php echo icon('website_directory', '', 12); ?> Directory Published
        </span>
      <?php endif; ?>
    </div>

    <!-- Complete Member Details List -->
    <div style="width: 100%; border-top: 1px solid var(--border-color); padding-top: 16px; font-size: 12.5px; color: var(--text-secondary); text-align: left; display: flex; flex-direction: column; gap: 9px;">
      <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.06); padding-bottom: 6px;">
        <span>PRC License #:</span>
        <strong style="color: var(--accent-primary);"><?php echo htmlspecialchars($user['prc_number'] ?: $user['id_number']); ?></strong>
      </div>
      <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.06); padding-bottom: 6px;">
        <span>Gender:</span>
        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($user['gender'] ?: '—'); ?></strong>
      </div>
      <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.06); padding-bottom: 6px;">
        <span>Age:</span>
        <strong style="color: var(--text-primary);"><?php echo $userAge !== null ? $userAge . ' yrs' : '—'; ?></strong>
      </div>
      <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.06); padding-bottom: 6px;">
        <span>Birth Date:</span>
        <strong style="color: var(--text-primary);"><?php echo !empty($user['birth_date']) ? date('M d, Y', strtotime($user['birth_date'])) : '—'; ?></strong>
      </div>
      <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.06); padding-bottom: 6px;">
        <span>Mobile #:</span>
        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($user['phone'] ?: '—'); ?></strong>
      </div>
      <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255,255,255,0.06); padding-bottom: 6px;">
        <span>Civil Status:</span>
        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($user['civil_status'] ?: '—'); ?></strong>
      </div>
      <div style="display: flex; flex-direction: column; gap: 2px; border-bottom: 1px dashed rgba(255,255,255,0.06); padding-bottom: 6px;">
        <span>Email Address:</span>
        <strong style="color: var(--text-primary); word-break: break-all; font-size: 12px;"><?php echo htmlspecialchars($user['email'] ?: '—'); ?></strong>
      </div>
      <div style="display: flex; flex-direction: column; gap: 2px; border-bottom: 1px dashed rgba(255,255,255,0.06); padding-bottom: 6px;">
        <span>Address:</span>
        <strong style="color: var(--text-primary); font-size: 12px; line-height: 1.35;"><?php echo htmlspecialchars($user['address'] ?: '—'); ?></strong>
      </div>
      <div style="display: flex; justify-content: space-between; padding-top: 4px;">
        <span>Account Status:</span>
        <strong style="color: #10b981; text-transform: uppercase;"><?php echo htmlspecialchars($user['status']); ?></strong>
      </div>
    </div>

    <!-- Quick Link to Website Directory Profile -->
    <div style="width: 100%; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border-color);">
      <a href="<?php echo BASE_URL; ?>/member/website_directory.php" class="btn btn-outline btn-sm" style="width: 100%; justify-content: center; font-size: 12px; margin-bottom: 12px;">
        <?php echo icon('website_directory', '', 14); ?> Manage Directory Showcase
      </a>

      <?php if ($wmRecord && function_exists('has_unlocked_website_directory') && has_unlocked_website_directory($pdo, $userId)): 
        $qrRelative = generate_member_directory_qr($pdo, (int)$wmRecord['id'], true);
        $isRevoked = !empty($standing['is_revoked']) || !$isGoodStanding;
      ?>
        <!-- Public Directory QR Code Section -->
        <div style="padding: 14px; background: <?php echo $isRevoked ? 'rgba(239,68,68,0.04)' : 'rgba(245,158,11,0.06)'; ?>; border: 1px dashed <?php echo $isRevoked ? 'rgba(239,68,68,0.35)' : 'rgba(245,158,11,0.35)'; ?>; border-radius: 10px; text-align: center; transition: all 0.3s ease;">
          <div style="display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; font-weight: 700; color: <?php echo $isRevoked ? '#ef4444' : 'var(--accent-primary, #f5b800)'; ?>; margin-bottom: 8px;">
            <?php echo icon('qr_codes', '', 14); ?>
            <span>Digital Directory QR Code</span>
          </div>

          <?php if ($qrRelative && file_exists(__DIR__ . '/../' . ltrim($qrRelative, '/'))): ?>
            <div style="position: relative; width: 110px; height: 110px; margin: 0 auto 10px; background: #ffffff; padding: 6px; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.35); overflow: hidden;">
              <img src="<?php echo BASE_URL . '/' . ltrim($qrRelative, '/'); ?>" alt="My Directory QR Code" style="width: 100%; height: 100%; object-fit: contain; <?php echo $isRevoked ? 'filter: blur(6px) grayscale(80%); opacity: 0.35;' : ''; ?> transition: filter 0.3s ease, opacity 0.3s ease;">
            </div>
          <?php endif; ?>

          <div style="display: flex; flex-direction: column; gap: 6px;">
            <?php if (!$isRevoked): ?>
              <a href="download_qr.php" class="btn btn-primary btn-sm" style="width: 100%; justify-content: center; font-size: 11.5px; font-weight: 700; padding: 7px 10px;">
                <?php echo icon('download', '', 13); ?> Download QR Code
              </a>
              <a href="<?php echo BASE_URL . '/profile/' . $wmRecord['id']; ?>" target="_blank" class="btn btn-secondary btn-sm" style="width: 100%; justify-content: center; font-size: 11px; padding: 5px 10px;">
                <?php echo icon('external_link', '', 12); ?> View Public Profile
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT: EDIT PROFILE FORM & CREDENTIALS -->
  <div style="display: flex; flex-direction: column; gap: 24px;">
    <!-- Profile Details & Portrait Form -->
    <div class="card" style="padding: 24px;">
      <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 18px; display: flex; align-items: center; gap: 8px;">
        <?php echo icon('user', '', 18); ?> Edit Profile Details &amp; Demographic Credentials
      </h3>

      <form method="post" enctype="multipart/form-data" action="">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_profile">

        <!-- 1x1 Photo Upload -->
        <div class="form-group" style="margin-bottom: 20px; padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">
            1x1 Profile Picture / Architect Portrait
          </label>
          <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
            <input type="file" name="profile_photo" id="memberPhotoInput" accept="image/png, image/jpeg, image/webp" class="form-control" style="flex: 1; min-width: 220px;" onchange="previewAvatar(this)">
            <?php if ($avatarUrl): ?>
              <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #ef4444; cursor: pointer;">
                <input type="checkbox" name="remove_photo" value="1">
                <span>Remove current photo</span>
              </label>
            <?php endif; ?>
          </div>
          <small class="form-text" style="color: var(--text-secondary); margin-top: 6px; display: block;">
            Recommended: Square (1x1 ratio) high-resolution image in JPG, PNG, or WebP format. Syncs directly with public architect directory.
          </small>
        </div>

        <!-- Row 1: Name & PRC Number -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">Full Legal Name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required placeholder="e.g. Ar. Juan Dela Cruz, uap">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">PRC License #</label>
            <input type="text" name="prc_number" class="form-control" value="<?php echo htmlspecialchars($user['prc_number'] ?: $user['id_number']); ?>" placeholder="e.g. 0089452">
            <small style="font-size: 11px; color: var(--text-secondary);">Registered PRC License / ID number.</small>
          </div>
        </div>

        <!-- Row 2: Gender & Civil Status -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">Gender</label>
            <select name="gender" class="form-control">
              <option value="">Select Gender</option>
              <option value="Male" <?php echo ($user['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
              <option value="Female" <?php echo ($user['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
              <option value="Prefer not to say" <?php echo ($user['gender'] ?? '') === 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">Civil Status</label>
            <select name="civil_status" class="form-control">
              <option value="">Select Civil Status</option>
              <option value="Single" <?php echo ($user['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
              <option value="Married" <?php echo ($user['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
              <option value="Widowed" <?php echo ($user['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
              <option value="Separated" <?php echo ($user['civil_status'] ?? '') === 'Separated' ? 'selected' : ''; ?>>Separated</option>
              <option value="Divorced" <?php echo ($user['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
            </select>
          </div>
        </div>

        <!-- Row 3: Birth Date (with live Age calculation) & Mobile # -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <label class="form-label" style="font-weight: 600; margin: 0;">Birth Date</label>
              <span id="liveAgeBadge" style="font-size: 11.5px; font-weight: 700; color: #f59e0b; background: rgba(245,158,11,0.12); padding: 2px 8px; border-radius: 4px;">
                <?php echo $userAge !== null ? "Age: $userAge yrs" : 'Age: —'; ?>
              </span>
            </div>
            <input type="date" name="birth_date" id="birthDateInput" class="form-control" value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>" onchange="updateAgePreview(this.value)">
          </div>
          <div class="form-group">
            <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">Mobile #</label>
            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="e.g. +63 917 123 4567">
          </div>
        </div>

        <!-- Row 4: Email Address -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">Email Address</label>
          <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="architect@example.com">
        </div>

        <!-- Row 5: Complete Address -->
        <div class="form-group" style="margin-bottom: 22px;">
          <label class="form-label" style="font-weight: 600; margin-bottom: 6px; display: block;">Complete Address</label>
          <textarea name="address" rows="2" class="form-control" placeholder="House/Unit #, Street, Barangay, City/Municipality, Province"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
          <small style="font-size: 11px; color: var(--text-secondary);">Residential or official architectural studio address.</small>
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
          <?php echo icon('check', '', 16); ?> Save Profile Details
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
          <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required>
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
      wrap.innerHTML = '<img src="' + e.target.result + '" alt="Avatar Preview" class="profile-avatar-large-img" style="width: 100%; height: 100%; object-fit: cover;">';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function updateAgePreview(birthDateStr) {
  const badge = document.getElementById('liveAgeBadge');
  if (!birthDateStr) {
    badge.textContent = 'Age: —';
    return;
  }
  const dob = new Date(birthDateStr);
  const diff = Date.now() - dob.getTime();
  const ageDate = new Date(diff);
  const calculatedAge = Math.abs(ageDate.getUTCFullYear() - 1970);
  if (!isNaN(calculatedAge)) {
    badge.textContent = 'Age: ' + calculatedAge + ' yrs';
  } else {
    badge.textContent = 'Age: —';
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
