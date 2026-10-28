<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $isSuper = is_super_admin();
    $sqlTarget = $isSuper ? "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL" : "SELECT * FROM users WHERE id = ? AND role = 'member' AND deleted_at IS NULL";
    $stmt = $pdo->prepare($sqlTarget);
    $stmt->execute([$user_id]);
    $target = $stmt->fetch();

    if (!$target) {
        $error = 'Member not found.';
    } elseif ($action === 'update_info') {
        $name = trim($_POST['name'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $prc_number = trim($_POST['prc_number'] ?? '') ?: $id_number;
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $civil_status = trim($_POST['civil_status'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        $goodStandingOverride = $_POST['good_standing_override'] ?? 'auto';
        if (!in_array($goodStandingOverride, ['auto', 'revoked', 'granted'], true)) {
            $goodStandingOverride = 'auto';
        }
        $goodStandingReason = trim($_POST['good_standing_reason'] ?? '');
        $removePhoto = !empty($_POST['remove_photo']);

        if (!$name || !$id_number) {
            $error = 'Name and PRC ID No. are required.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $check->execute([$id_number, $user_id]);
            if ($check->fetch()) {
                $error = 'That PRC ID No. is already used by another account.';
            } else {
                $photoPath = $target['profile_photo'] ?? null;

                // Handle Photo Removal
                if ($removePhoto && !empty($photoPath)) {
                    $fileOnDisk = __DIR__ . '/../' . ltrim($photoPath, '/');
                    if (file_exists($fileOnDisk) && !is_dir($fileOnDisk)) {
                        if (function_exists('soft_delete_file')) {
                            soft_delete_file($photoPath, 'users', $user_id, 'Photo removed by admin');
                        } else {
                            @unlink($fileOnDisk);
                        }
                    }
                    $photoPath = null;
                    $pdo->prepare("UPDATE website_members SET photo_path = NULL WHERE user_id = ?")->execute([$user_id]);
                }

                // Handle Photo Upload
                if (!empty($_FILES['member_photo']['name']) && $_FILES['member_photo']['error'] === UPLOAD_ERR_OK) {
                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                    $fileTmp = $_FILES['member_photo']['tmp_name'];
                    $fileSize = $_FILES['member_photo']['size'];
                    $ext = strtolower(pathinfo($_FILES['member_photo']['name'], PATHINFO_EXTENSION));

                    if (!in_array($ext, $allowedExts)) {
                        $error = 'Invalid photo format. Please upload JPG, PNG, or WebP.';
                    } elseif ($fileSize > 5 * 1024 * 1024) {
                        $error = 'Photo file size exceeds 5MB limit.';
                    } else {
                        $uploadDir = __DIR__ . '/../uploads/avatars/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $uniqueFilename = 'avatar_member_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                        $targetPath = $uploadDir . $uniqueFilename;

                        if (move_uploaded_file($fileTmp, $targetPath)) {
                            $photoPath = 'uploads/avatars/' . $uniqueFilename;
                            $pdo->prepare("UPDATE website_members SET photo_path = ? WHERE user_id = ?")->execute([$photoPath, $user_id]);
                        }
                    }
                }

                if (empty($error)) {
                    // Compute Age dynamically
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

                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, id_number = ?, prc_number = ?, email = ?, phone = ?, gender = ?, birth_date = ?, age = ?, civil_status = ?, address = ?,
                            status = ?, profile_photo = ?, good_standing_override = ?, good_standing_reason = ?, good_standing_updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name,
                        $id_number,
                        $prc_number,
                        $email ?: null,
                        $phone ?: null,
                        $gender ?: null,
                        $birth_date ?: null,
                        $computedAge,
                        $civil_status ?: null,
                        $address ?: null,
                        $status,
                        $photoPath,
                        $goodStandingOverride,
                        $goodStandingReason !== '' ? $goodStandingReason : null,
                        $user_id
                    ]);

                    // Sync name and company/link with website_members
                    $companyName = trim($_POST['company_name'] ?? '');
                    $linkUrl = trim($_POST['link_url'] ?? '');
                    $linkType = trim($_POST['link_type'] ?? 'auto');
                    if ($linkUrl !== '' && !preg_match('#^https?://#i', $linkUrl)) {
                        $linkUrl = 'https://' . ltrim($linkUrl, '/');
                    }

                    $wmCheck = $pdo->prepare("SELECT id FROM website_members WHERE user_id = ?");
                    $wmCheck->execute([$user_id]);
                    if ($wmCheck->fetch()) {
                        $pdo->prepare("UPDATE website_members SET name = ?, prc_number = ?, company_name = ?, link_url = ?, link_type = ? WHERE user_id = ?")
                            ->execute([$name, $prc_number, $companyName !== '' ? $companyName : null, $linkUrl !== '' ? $linkUrl : null, $linkType, $user_id]);
                    }

                    if ($isSuper && isset($_POST['role'])) {
                        $newRole = $_POST['role'];
                        if (in_array($newRole, ['member', 'admin', 'super_admin'], true)) {
                            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $user_id]);
                        }
                    }
                    $success = 'Account details, demographics, profile photo, and directory settings updated successfully.';
                }
            }
        }
    } elseif ($action === 'reset_password') {
        $new_password = $_POST['new_password'] ?? '';
        if (strlen($new_password) < 4) {
            $error = 'New password must be at least 4 characters.';
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            $success = 'Password reset successfully for ' . htmlspecialchars($target['name']) . '.';
        }
    } elseif ($action === 'delete_account') {
        if ($user_id === (int)$_SESSION['user_id']) {
            $error = 'You cannot delete your own account.';
        } else {
            if (!empty($target['profile_photo']) && function_exists('soft_delete_file')) {
                soft_delete_file($target['profile_photo'], 'users', $user_id, 'Account deleted');
            }
            if (function_exists('soft_delete_record')) {
                soft_delete_record('users', $user_id, 'Account deleted via manager');
            } else {
                $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?")->execute([$user_id]);
            }
            $success = 'Account for ' . htmlspecialchars($target['name']) . ' safely moved to Trash Bin.';
        }
    }
}

// Search
$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT u.*, wm.photo_path as wm_photo_path, wm.role_title, wm.specialty, wm.company_name, wm.link_url, wm.link_type 
                          FROM users u 
                          LEFT JOIN website_members wm ON wm.user_id = u.id 
                          WHERE u.role = 'member' AND (u.name LIKE ? OR u.id_number LIKE ? OR u.prc_number LIKE ? OR u.email LIKE ? OR wm.company_name LIKE ?) 
                          ORDER BY u.name ASC");
    $like = "%$search%";
    $stmt->execute([$like, $like, $like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT u.*, wm.photo_path as wm_photo_path, wm.role_title, wm.specialty, wm.company_name, wm.link_url, wm.link_type 
                        FROM users u 
                        LEFT JOIN website_members wm ON wm.user_id = u.id 
                        WHERE u.role = 'member' 
                        ORDER BY u.name ASC");
}
$members = $stmt->fetchAll();

$page_title = 'Edit Member Accounts • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">USER ADMINISTRATION</p>
    <h1>Member Account Manager</h1>
    <p class="page-subtitle">Edit member credentials, demographics, profile pictures, good standing status, or reset passwords.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('account_manager', '', 14); ?> <span><?php echo count($members); ?> Accounts</span>
  </div>
</div>

<div class="card" style="margin-bottom: 24px;">
  <form method="get" style="display:flex; gap:10px; flex-wrap: wrap;">
    <input type="text" name="search" placeholder="Search by name, PRC #, email, or firm..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1; min-width:240px;">
    <button class="btn btn-sm btn-primary" type="submit"><?php echo icon('search', '', 14); ?> <span>Search</span></button>
    <?php if ($search): ?>
      <a class="btn btn-sm btn-secondary" href="account_manager.php"><?php echo icon('x', '', 14); ?> <span>Clear</span></a>
    <?php endif; ?>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-top:16px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <?php echo icon('alert', '', 18); ?>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success" style="margin-top:16px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <?php echo icon('check', '', 18); ?>
        <span><?php echo htmlspecialchars($success); ?></span>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (empty($members)): ?>
  <div class="card" style="text-align: center; padding: 40px 16px; color: var(--text-secondary);">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(0,0,0,0.05); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
      <?php echo icon('members', '', 24); ?>
    </div>
    <strong style="display: block; font-size: 15px; color: var(--text-primary);">No member accounts found</strong>
    <p class="muted" style="margin-top: 4px; font-size: 13px;">Try searching for another name or ID number.</p>
  </div>
<?php endif; ?>

<div style="display: flex; flex-direction: column; gap: 18px;">
  <?php foreach ($members as $m): ?>
    <?php
      $photo = ($m['profile_photo'] ?? null) ?: ($m['wm_photo_path'] ?? null);
      $photoUrl = $photo ? (str_starts_with($photo, 'http') ? $photo : BASE_URL . '/' . ltrim($photo, '/')) : null;
      $initials = strtoupper(substr($m['name'], 0, 1) . substr(strrchr($m['name'], ' ') ?: $m['name'], 1, 1));
    ?>
    <div class="card" style="margin: 0;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div class="table-avatar-wrap">
            <?php if ($photoUrl): ?>
              <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>" class="table-avatar-img">
            <?php else: ?>
              <?php echo htmlspecialchars($initials); ?>
            <?php endif; ?>
          </div>
          <div>
            <h2 style="font-size: 16px; margin: 0; display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <span><?php echo htmlspecialchars($m['name']); ?></span>
              <span class="muted" style="font-size: 13px; font-weight: 500;">(PRC: <?php echo htmlspecialchars($m['prc_number'] ?: $m['id_number']); ?>)</span>
            </h2>
            <?php if (!empty($m['specialty'])): ?>
              <div style="font-size: 12px; color: var(--accent-primary); margin-top: 2px;">
                <?php echo htmlspecialchars($m['specialty']); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
          <?php
            $standing = get_member_standing_details($pdo, $m['id']);
            if ($standing['is_revoked']) {
                echo '<span class="badge-pill badge-unpaid" style="font-size: 11px;" title="' . htmlspecialchars($standing['reason'] ?? '') . '">' . icon('alert', '', 10) . ' Standing Revoked</span>';
            } elseif ($standing['is_good']) {
                echo '<span class="badge-pill badge-paid" style="font-size: 11px;">' . icon('good_members', '', 10) . ' Good Standing</span>';
            }
          ?>
          <span class="badge-pill badge-<?php echo $m['status'] === 'approved' ? 'paid' : ($m['status'] === 'pending' ? 'pending' : 'unpaid'); ?>">
            <?php echo ucfirst($m['status']); ?>
          </span>
        </div>
      </div>

      <div class="grid-2" style="gap: 20px;">
        <!-- Edit info + demographics + status + photo -->
        <form method="post" enctype="multipart/form-data" style="background: var(--bg-secondary); padding: 18px; border-radius: 12px; border: 1px solid var(--border-color);">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
          <input type="hidden" name="action" value="update_info">
          
          <div class="field" style="margin-bottom: 10px;">
            <label>Full Legal Name <span style="color:#ef4444;">*</span></label>
            <input name="name" value="<?php echo htmlspecialchars($m['name']); ?>" required>
          </div>

          <div class="grid-2" style="gap: 10px; margin-bottom: 10px;">
            <div class="field">
              <label>PRC ID No. / Username <span style="color:#ef4444;">*</span></label>
              <input name="id_number" value="<?php echo htmlspecialchars($m['id_number']); ?>" required>
            </div>
            <div class="field">
              <label>PRC License #</label>
              <input name="prc_number" value="<?php echo htmlspecialchars($m['prc_number'] ?: $m['id_number']); ?>" placeholder="e.g. 0089452">
            </div>
          </div>

          <!-- Demographics Row -->
          <div class="grid-2" style="gap: 10px; margin-bottom: 10px;">
            <div class="field">
              <label>Gender</label>
              <select name="gender">
                <option value="">Select Gender</option>
                <option value="Male" <?php echo ($m['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($m['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                <option value="Prefer not to say" <?php echo ($m['gender'] ?? '') === 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
              </select>
            </div>
            <div class="field">
              <label>Civil Status</label>
              <select name="civil_status">
                <option value="">Select Civil Status</option>
                <option value="Single" <?php echo ($m['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                <option value="Married" <?php echo ($m['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                <option value="Widowed" <?php echo ($m['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                <option value="Separated" <?php echo ($m['civil_status'] ?? '') === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                <option value="Divorced" <?php echo ($m['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
              </select>
            </div>
          </div>

          <!-- Contact Row -->
          <div class="grid-2" style="gap: 10px; margin-bottom: 10px;">
            <div class="field">
              <label>Birth Date</label>
              <input type="date" name="birth_date" value="<?php echo htmlspecialchars($m['birth_date'] ?? ''); ?>">
            </div>
            <div class="field">
              <label>Mobile Number</label>
              <input type="tel" name="phone" value="<?php echo htmlspecialchars($m['phone'] ?? ''); ?>" placeholder="+63 9XX XXX XXXX">
            </div>
          </div>

          <div class="field" style="margin-bottom: 10px;">
            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($m['email'] ?? ''); ?>" placeholder="architect@example.com">
          </div>

          <div class="field" style="margin-bottom: 10px;">
            <label>Complete Address</label>
            <textarea name="address" rows="2" style="width:100%; resize:vertical;"><?php echo htmlspecialchars($m['address'] ?? ''); ?></textarea>
          </div>

          <div class="grid-2" style="gap: 10px; margin-bottom: 10px;">
            <div class="field">
              <label>Account Status</label>
              <select name="status">
                <option value="pending" <?php echo $m['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $m['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $m['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
              </select>
            </div>
            <div class="field">
              <label>Good Standing Mode</label>
              <select name="good_standing_override">
                <option value="auto" <?php echo ($m['good_standing_override'] ?? 'auto') === 'auto' ? 'selected' : ''; ?>>Automatic (Dues-Based)</option>
                <option value="revoked" <?php echo ($m['good_standing_override'] ?? '') === 'revoked' ? 'selected' : ''; ?>>Revoked / On Hold</option>
                <option value="granted" <?php echo ($m['good_standing_override'] ?? '') === 'granted' ? 'selected' : ''; ?>>Granted / Exemption</option>
              </select>
            </div>
          </div>

          <div class="field" style="margin-bottom: 10px;">
            <label>Standing Reason / Admin Note</label>
            <input name="good_standing_reason" value="<?php echo htmlspecialchars($m['good_standing_reason'] ?? ''); ?>" placeholder="Optional reason (e.g. Disciplinary hold, Ethics review)...">
          </div>

          <div class="grid-2" style="gap: 10px; margin-bottom: 10px;">
            <div class="field">
              <label>Company / Firm Name</label>
              <input name="company_name" value="<?php echo htmlspecialchars($m['company_name'] ?? ''); ?>" placeholder="e.g. Ting & Associates Architects">
            </div>
            <div class="field">
              <label>Website / Social Link</label>
              <input name="link_url" value="<?php echo htmlspecialchars($m['link_url'] ?? ''); ?>" placeholder="https://facebook.com/..., https://yourfirm.com">
            </div>
          </div>

          <div class="field" style="margin-bottom: 14px;">
            <label>1x1 Profile Picture / Architect Portrait</label>
            <input type="file" name="member_photo" accept="image/png, image/jpeg, image/webp" style="padding: 6px;">
            <?php if ($photoUrl): ?>
              <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #ef4444; margin-top: 6px; cursor: pointer;">
                <input type="checkbox" name="remove_photo" value="1">
                <span>Remove current photo</span>
              </label>
            <?php endif; ?>
          </div>

          <button class="btn btn-sm btn-primary" type="submit" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700;">
            <?php echo icon('check', '', 12); ?> <span>Save Member Details</span>
          </button>
        </form>

        <!-- Right Side: Reset Password & Soft Delete -->
        <div style="display: flex; flex-direction: column; gap: 14px;">
          <!-- Reset password -->
          <form method="post" style="background: var(--bg-secondary); padding: 18px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-direction: column;"
                data-confirm="Reset password for <?php echo htmlspecialchars($m['name']); ?>?"
                data-confirm-title="Reset Member Password"
                data-confirm-btn="Reset Password"
                data-confirm-class="btn-danger">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
            <input type="hidden" name="action" value="reset_password">
            
            <h3 style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 0 0 10px 0; display: flex; align-items: center; gap: 6px;">
              <?php echo icon('key', '', 14); ?> Reset Credentials
            </h3>
            <div class="field" style="margin-bottom: 14px;">
              <label>Set Temporary Password</label>
              <input type="password" name="new_password" placeholder="Min. 4 characters" required minlength="4">
            </div>
            <button class="btn btn-sm btn-secondary" type="submit" style="display: inline-flex; align-items: center; gap: 4px;">
              <?php echo icon('key', '', 12); ?> <span>Reset Password</span>
            </button>
          </form>

          <!-- Account Delete -->
          <form method="post" style="background: rgba(239,68,68,0.04); padding: 18px; border-radius: 12px; border: 1px dashed rgba(239,68,68,0.3);"
                data-confirm="Move account for <?php echo htmlspecialchars($m['name']); ?> to Trash Bin? It can be restored by Super Admin."
                data-confirm-title="Move Account to Trash"
                data-confirm-btn="Move to Trash"
                data-confirm-class="btn-danger">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
            <input type="hidden" name="action" value="delete_account">
            
            <h3 style="font-size: 14px; font-weight: 700; color: #ef4444; margin: 0 0 6px 0; display: flex; align-items: center; gap: 6px;">
              <?php echo icon('trash', '', 14); ?> Delete Account
            </h3>
            <p style="font-size: 12px; color: var(--text-secondary); margin: 0 0 12px 0;">
              Moves this member to the Super Admin Trash quarantine.
            </p>
            <button class="btn btn-sm btn-danger" type="submit" style="display: inline-flex; align-items: center; gap: 4px;">
              <?php echo icon('trash', '', 12); ?> <span>Delete Account</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
