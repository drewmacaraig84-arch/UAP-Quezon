<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$success = '';
$expanded_user_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    $isSuper = is_super_admin();

    if ($action === 'add_member') {
        $name = trim($_POST['name'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $prc_number = trim($_POST['prc_number'] ?? '') ?: $id_number;
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $civil_status = trim($_POST['civil_status'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['approved', 'pending', 'rejected'], true) ? $_POST['status'] : 'approved';
        $role = ($isSuper && in_array($_POST['role'] ?? '', ['member', 'admin', 'super_admin'], true)) ? $_POST['role'] : 'member';
        $password = trim($_POST['password'] ?? '');
        if ($password === '') {
            $password = 'member123';
        }

        if (!$name || !$id_number) {
            $error = 'Full Legal Name and PRC ID No. / Username are required.';
        } elseif (strlen($password) < 4) {
            $error = 'Initial password must be at least 4 characters long.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
            $check->execute([$id_number]);
            if ($check->fetch()) {
                $error = "An account with PRC ID No. '" . htmlspecialchars($id_number) . "' already exists.";
            } else {
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

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        name, id_number, prc_number, email, phone, gender, birth_date, age, civil_status, address,
                        password, role, status, good_standing_override, terms_accepted, terms_accepted_at,
                        privacy_accepted, privacy_accepted_at, cookie_accepted, cookie_accepted_at, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'auto', 1, NOW(), 1, NOW(), 1, NOW(), NOW())
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
                    $hash,
                    $role,
                    $status
                ]);
                $newUserId = (int)$pdo->lastInsertId();

                if ($companyName || $specialty) {
                    try {
                        $wmStmt = $pdo->prepare("INSERT INTO website_members (user_id, name, prc_number, specialty, company_name, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                        $wmStmt->execute([$newUserId, $name, $prc_number, $specialty ?: null, $companyName ?: null]);
                    } catch (Throwable $e) {}
                }

                if (function_exists('get_or_create_member_attendance')) {
                    get_or_create_member_attendance($pdo, $newUserId);
                }

                if (function_exists('audit_log')) {
                    audit_log('MEMBER_CREATE', 'MEMBERS', "Admin created account for Ar. $name (PRC: $id_number) with status '$status'", $_SESSION['user_id']);
                }

                $expanded_user_id = $newUserId;
                $success = "Member account for Ar. " . htmlspecialchars($name) . " created successfully! Default password: " . htmlspecialchars($password);
            }
        }
    } else {
        $sqlTarget = $isSuper ? "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL" : "SELECT * FROM users WHERE id = ? AND role = 'member' AND deleted_at IS NULL";
        $stmt = $pdo->prepare($sqlTarget);
        $stmt->execute([$user_id]);
        $target = $stmt->fetch();

        if (!$target) {
            $error = 'Member account not found or already deleted.';
        } elseif ($action === 'update_info') {
            $expanded_user_id = $user_id;
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
            $expanded_user_id = $user_id;
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
}

// Search
$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT u.*, wm.photo_path as wm_photo_path, wm.role_title, wm.specialty, wm.company_name, wm.link_url, wm.link_type 
                          FROM users u 
                          LEFT JOIN website_members wm ON wm.user_id = u.id 
                          WHERE u.role = 'member' AND u.deleted_at IS NULL AND (u.name LIKE ? OR u.id_number LIKE ? OR u.prc_number LIKE ? OR u.email LIKE ? OR wm.company_name LIKE ?) 
                          ORDER BY u.name ASC");
    $like = "%$search%";
    $stmt->execute([$like, $like, $like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT u.*, wm.photo_path as wm_photo_path, wm.role_title, wm.specialty, wm.company_name, wm.link_url, wm.link_type 
                        FROM users u 
                        LEFT JOIN website_members wm ON wm.user_id = u.id 
                        WHERE u.role = 'member' AND u.deleted_at IS NULL 
                        ORDER BY u.name ASC");
}
$members = $stmt->fetchAll();

$page_title = 'Edit Member Accounts • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<style>
.member-card-header:hover {
  background: rgba(255, 255, 255, 0.035) !important;
}
.member-card.is-expanded {
  border-color: rgba(245, 158, 11, 0.45) !important;
  box-shadow: 0 8px 24px -6px rgba(0, 0, 0, 0.35);
}
.modal-grid-responsive {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 12px;
}
@media (max-width: 640px) {
  .modal-grid-responsive {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="page-hero" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
  <div>
    <p class="eyebrow">USER ADMINISTRATION</p>
    <h1>Member Account Manager</h1>
    <p class="page-subtitle">Edit member credentials, demographics, profile pictures, good standing status, or reset passwords.</p>
  </div>
  <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
    <div class="hero-badge" style="margin: 0;">
      <?php echo icon('account_manager', '', 14); ?> <span><?php echo count($members); ?> Accounts</span>
    </div>
    <button type="button" class="btn btn-primary" onclick="openAddMemberModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; padding: 9px 16px; border-radius: 10px;">
      <?php echo icon('plus', '', 16); ?> <span>Add Member</span>
    </button>
  </div>
</div>

<div class="card" style="margin-bottom: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
    <form method="get" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1; min-width: 280px;">
      <input type="text" name="search" placeholder="Search by name, PRC #, email, or firm..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 220px;">
      <button class="btn btn-sm btn-primary" type="submit"><?php echo icon('search', '', 14); ?> <span>Search</span></button>
      <?php if ($search): ?>
        <a class="btn btn-sm btn-secondary" href="account_manager.php"><?php echo icon('x', '', 14); ?> <span>Clear</span></a>
      <?php endif; ?>
    </form>
    
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
      <button type="button" class="btn btn-sm btn-secondary" onclick="expandAllMembers()" style="display: inline-flex; align-items: center; gap: 5px; font-size: 12px;" title="Expand all member detail cards">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 11 12 6 17 11"></polyline><polyline points="7 17 12 12 17 17"></polyline></svg>
        <span>Expand All</span>
      </button>
      <button type="button" class="btn btn-sm btn-secondary" onclick="collapseAllMembers()" style="display: inline-flex; align-items: center; gap: 5px; font-size: 12px;" title="Collapse all member detail cards">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 13 12 18 17 13"></polyline><polyline points="7 7 12 12 17 7"></polyline></svg>
        <span>Collapse All</span>
      </button>
      <button type="button" class="btn btn-sm btn-primary" onclick="openAddMemberModal()" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700;">
        <?php echo icon('plus', '', 13); ?> <span>Add Member</span>
      </button>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-top: 16px;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <?php echo icon('alert', '', 18); ?>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success" style="margin-top: 16px;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <?php echo icon('check', '', 18); ?>
        <span><?php echo htmlspecialchars($success); ?></span>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (empty($members)): ?>
  <div class="card" style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
    <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(0,0,0,0.06); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; color: var(--accent-primary);">
      <?php echo icon('members', '', 26); ?>
    </div>
    <strong style="display: block; font-size: 16px; color: var(--text-primary);">No member accounts found</strong>
    <p class="muted" style="margin-top: 6px; font-size: 13px;">Try searching for another name or ID number, or create a new member record directly.</p>
    <div style="margin-top: 16px;">
      <button type="button" class="btn btn-sm btn-primary" onclick="openAddMemberModal()" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('plus', '', 14); ?> <span>Add Member Now</span>
      </button>
    </div>
  </div>
<?php endif; ?>

<div style="display: flex; flex-direction: column; gap: 14px;">
  <?php foreach ($members as $m): ?>
    <?php
      $photo = ($m['profile_photo'] ?? null) ?: ($m['wm_photo_path'] ?? null);
      $photoUrl = $photo ? (str_starts_with($photo, 'http') ? $photo : BASE_URL . '/' . ltrim($photo, '/')) : null;
      $initials = strtoupper(substr($m['name'], 0, 1) . substr(strrchr($m['name'], ' ') ?: $m['name'], 1, 1));
      $isOpen = ($expanded_user_id === (int)$m['id']);
    ?>
    <div class="card member-card <?php echo $isOpen ? 'is-expanded' : ''; ?>" id="member-card-<?php echo $m['id']; ?>" style="margin: 0; padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: 14px; transition: border-color 0.2s ease, box-shadow 0.2s ease;">
      <!-- Collapsible Card Header -->
      <div class="member-card-header" onclick="toggleMemberCard(<?php echo $m['id']; ?>)" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; gap: 14px; flex-wrap: wrap; background: var(--card-bg, #131d33); transition: background-color 0.2s ease;">
        <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 240px;">
          <div class="table-avatar-wrap" style="flex-shrink: 0;">
            <?php if ($photoUrl): ?>
              <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>" class="table-avatar-img">
            <?php else: ?>
              <?php echo htmlspecialchars($initials); ?>
            <?php endif; ?>
          </div>
          <div>
            <h2 style="font-size: 15.5px; margin: 0; display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; color: var(--text-primary);">
              <span><?php echo htmlspecialchars($m['name']); ?></span>
              <span class="muted" style="font-size: 12.5px; font-weight: 500;">(PRC: <?php echo htmlspecialchars($m['prc_number'] ?: $m['id_number']); ?>)</span>
            </h2>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 3px; font-size: 12px; color: var(--text-secondary);">
              <?php if (!empty($m['specialty'])): ?>
                <span style="color: var(--accent-primary); font-weight: 600;"><?php echo htmlspecialchars($m['specialty']); ?></span>
              <?php endif; ?>
              <?php if (!empty($m['email'])): ?>
                <span>• <?php echo htmlspecialchars($m['email']); ?></span>
              <?php endif; ?>
              <?php if (!empty($m['phone'])): ?>
                <span>• <?php echo htmlspecialchars($m['phone']); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
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

          <button type="button" class="btn btn-sm btn-secondary member-toggle-btn" id="member-toggle-btn-<?php echo $m['id']; ?>" onclick="event.stopPropagation(); toggleMemberCard(<?php echo $m['id']; ?>);" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; font-weight: 600;">
            <span class="toggle-text"><?php echo $isOpen ? 'Collapse' : 'Manage'; ?></span>
            <svg class="toggle-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.25s ease; <?php echo $isOpen ? 'transform: rotate(180deg);' : ''; ?>">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </button>
        </div>
      </div>

      <!-- Collapsible Card Body -->
      <div class="member-card-body" id="member-body-<?php echo $m['id']; ?>" style="display: <?php echo $isOpen ? 'block' : 'none'; ?>; padding: 20px; border-top: 1px solid var(--border-color); background: rgba(0,0,0,0.12);">
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
    </div>
  <?php endforeach; ?>
</div>

<!-- ================= ADD MEMBER MODAL ================= -->
<div id="addMemberModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:99999; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); overflow-y:auto;">
  <div style="background:var(--card-bg, #131d33); border:1px solid var(--border-color, rgba(255,255,255,0.15)); border-radius:18px; max-width:680px; width:100%; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.7); color:var(--text-primary); margin:auto; max-height:92vh; display:flex; flex-direction:column;">
    <!-- Modal Header -->
    <div style="padding:18px 24px; border-bottom:1px solid var(--border-color); display:flex; align-items:center; justify-content:space-between; gap:12px; background:rgba(255,255,255,0.02);">
      <div style="display:flex; align-items:center; gap:12px;">
        <div style="width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:rgba(245,158,11,0.15); color:var(--accent-primary); flex-shrink:0;">
          <?php echo icon('account_manager', '', 20); ?>
        </div>
        <div>
          <h3 style="margin:0; font-size:17px; font-weight:700; color:var(--text-primary);">Add New Chapter Member</h3>
          <p class="muted" style="margin:2px 0 0; font-size:12px;">Direct administrator enrollment for registered architects</p>
        </div>
      </div>
      <button type="button" onclick="closeAddMemberModal()" style="border:none; background:transparent; cursor:pointer; color:var(--text-secondary); display:flex; align-items:center; padding:6px; border-radius:8px; transition:color 0.2s ease;">
        <?php echo icon('x', '', 18); ?>
      </button>
    </div>

    <!-- Modal Form Body -->
    <form method="post" style="padding:22px 24px; margin:0; overflow-y:auto; flex:1;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_member">

      <div class="field" style="margin-bottom:12px;">
        <label style="font-weight:600; font-size:13px;">Full Legal Name <span style="color:#ef4444;">*</span></label>
        <input type="text" name="name" id="newMemberName" placeholder="e.g. Ar. Juan Dela Cruz, uap" required style="width:100%;">
        <span class="muted" style="font-size:11.5px; display:block; margin-top:3px;">Include professional suffix (e.g. Ar. [Name], uap / fuap).</span>
      </div>

      <div class="modal-grid-responsive" style="margin-bottom:12px;">
        <div class="field">
          <label style="font-weight:600; font-size:13px;">PRC ID No. / Username <span style="color:#ef4444;">*</span></label>
          <input type="text" name="id_number" id="newMemberIdNumber" placeholder="e.g. UAPQ-2026-001 or 0089452" required style="width:100%;" oninput="syncPrcNumber(this.value)">
        </div>
        <div class="field">
          <label style="font-weight:600; font-size:13px;">PRC License #</label>
          <input type="text" name="prc_number" id="newMemberPrcNumber" placeholder="Auto-fills from ID No." style="width:100%;">
        </div>
      </div>

      <div class="modal-grid-responsive" style="margin-bottom:12px;">
        <div class="field">
          <label style="font-weight:600; font-size:13px;">Temporary Password <span style="color:#ef4444;">*</span></label>
          <input type="text" name="password" value="member123" required minlength="4" style="width:100%;">
          <span class="muted" style="font-size:11.5px; display:block; margin-top:3px;">Default: <code>member123</code>. Member can change on login.</span>
        </div>
        <div class="field">
          <label style="font-weight:600; font-size:13px;">Account Status</label>
          <select name="status" style="width:100%;">
            <option value="approved" selected>Approved (Immediate Access)</option>
            <option value="pending">Pending Review</option>
          </select>
        </div>
      </div>

      <div class="modal-grid-responsive" style="margin-bottom:12px;">
        <div class="field">
          <label style="font-weight:600; font-size:13px;">Email Address</label>
          <input type="email" name="email" placeholder="architect@example.com" style="width:100%;">
        </div>
        <div class="field">
          <label style="font-weight:600; font-size:13px;">Mobile Phone</label>
          <input type="tel" name="phone" placeholder="+63 9XX XXX XXXX" style="width:100%;">
        </div>
      </div>

      <div class="modal-grid-responsive" style="margin-bottom:12px;">
        <div class="field">
          <label style="font-weight:600; font-size:13px;">Gender</label>
          <select name="gender" style="width:100%;">
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Prefer not to say">Prefer not to say</option>
          </select>
        </div>
        <div class="field">
          <label style="font-weight:600; font-size:13px;">Civil Status</label>
          <select name="civil_status" style="width:100%;">
            <option value="">Select Civil Status</option>
            <option value="Single">Single</option>
            <option value="Married">Married</option>
            <option value="Widowed">Widowed</option>
            <option value="Separated">Separated</option>
            <option value="Divorced">Divorced</option>
          </select>
        </div>
      </div>

      <div class="modal-grid-responsive" style="margin-bottom:12px;">
        <div class="field">
          <label style="font-weight:600; font-size:13px;">Birth Date</label>
          <input type="date" name="birth_date" style="width:100%;">
        </div>
        <div class="field">
          <label style="font-weight:600; font-size:13px;">Architectural Specialty</label>
          <input type="text" name="specialty" placeholder="e.g. Residential, Heritage, Interior" style="width:100%;">
        </div>
      </div>

      <div class="field" style="margin-bottom:12px;">
        <label style="font-weight:600; font-size:13px;">Firm / Practice / Company Name</label>
        <input type="text" name="company_name" placeholder="e.g. Juan Dela Cruz Design Studio" style="width:100%;">
      </div>

      <div class="field" style="margin-bottom:18px;">
        <label style="font-weight:600; font-size:13px;">Complete Office / Residential Address</label>
        <textarea name="address" rows="2" placeholder="Lucena City, Quezon Province..." style="width:100%; resize:vertical;"></textarea>
      </div>

      <?php if (is_super_admin()): ?>
        <div class="field" style="margin-bottom:18px; background:rgba(245,158,11,0.06); padding:12px; border-radius:10px; border:1px solid rgba(245,158,11,0.2);">
          <label style="font-weight:600; font-size:13px; color:var(--accent-primary);">Account Role Assignment (Super Admin Only)</label>
          <select name="role" style="width:100%; margin-top:4px;">
            <option value="member" selected>Member (Standard Chapter Architect)</option>
            <option value="admin">Admin (Chapter Officer / Secretariat)</option>
          </select>
        </div>
      <?php endif; ?>

      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px; padding-top:14px; border-top:1px solid var(--border-color); flex-wrap:wrap;">
        <button type="button" onclick="closeAddMemberModal()" class="btn btn-sm btn-secondary" style="padding:9px 18px;">Cancel</button>
        <button type="submit" class="btn btn-sm btn-primary" style="padding:9px 20px; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
          <?php echo icon('check', '', 14); ?> <span>Enroll Member Account</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleMemberCard(id) {
  var card = document.getElementById('member-card-' + id);
  var body = document.getElementById('member-body-' + id);
  var btn = document.getElementById('member-toggle-btn-' + id);
  if (!card || !body) return;

  var isCurrentlyOpen = body.style.display !== 'none';
  if (isCurrentlyOpen) {
    body.style.display = 'none';
    card.classList.remove('is-expanded');
    if (btn) {
      btn.querySelector('.toggle-text').textContent = 'Manage';
      btn.querySelector('.toggle-chevron').style.transform = 'rotate(0deg)';
    }
  } else {
    body.style.display = 'block';
    card.classList.add('is-expanded');
    if (btn) {
      btn.querySelector('.toggle-text').textContent = 'Collapse';
      btn.querySelector('.toggle-chevron').style.transform = 'rotate(180deg)';
    }
  }
}

function expandAllMembers() {
  document.querySelectorAll('.member-card-body').forEach(function(body) {
    body.style.display = 'block';
  });
  document.querySelectorAll('.member-card').forEach(function(card) {
    card.classList.add('is-expanded');
  });
  document.querySelectorAll('.member-toggle-btn').forEach(function(btn) {
    var text = btn.querySelector('.toggle-text');
    var chevron = btn.querySelector('.toggle-chevron');
    if (text) text.textContent = 'Collapse';
    if (chevron) chevron.style.transform = 'rotate(180deg)';
  });
}

function collapseAllMembers() {
  document.querySelectorAll('.member-card-body').forEach(function(body) {
    body.style.display = 'none';
  });
  document.querySelectorAll('.member-card').forEach(function(card) {
    card.classList.remove('is-expanded');
  });
  document.querySelectorAll('.member-toggle-btn').forEach(function(btn) {
    var text = btn.querySelector('.toggle-text');
    var chevron = btn.querySelector('.toggle-chevron');
    if (text) text.textContent = 'Manage';
    if (chevron) chevron.style.transform = 'rotate(0deg)';
  });
}

function openAddMemberModal() {
  var modal = document.getElementById('addMemberModal');
  if (modal) {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(function() {
      var input = document.getElementById('newMemberName');
      if (input) input.focus();
    }, 60);
  }
}

function closeAddMemberModal() {
  var modal = document.getElementById('addMemberModal');
  if (modal) {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
}

function syncPrcNumber(val) {
  var prcInput = document.getElementById('newMemberPrcNumber');
  if (prcInput && (!prcInput.dataset.manualEdited || prcInput.value === '')) {
    prcInput.value = val;
  }
}

document.addEventListener('DOMContentLoaded', function() {
  var prcInput = document.getElementById('newMemberPrcNumber');
  if (prcInput) {
    prcInput.addEventListener('input', function() {
      this.dataset.manualEdited = 'true';
    });
  }

  var addModal = document.getElementById('addMemberModal');
  if (addModal) {
    addModal.addEventListener('click', function(e) {
      if (e.target === this) {
        closeAddMemberModal();
      }
    });
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeAddMemberModal();
    }
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
