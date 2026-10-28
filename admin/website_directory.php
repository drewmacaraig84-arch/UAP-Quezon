<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
ensure_user_profile_photo_column($pdo);

$error = '';
$success = '';

// Load default fee from site_settings
$defaultFee = '500.00';
try {
    $sStmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'website_directory_fee'");
    $sStmt->execute();
    $sRow = $sStmt->fetch();
    if ($sRow && !empty($sRow['setting_value'])) {
        $defaultFee = number_format((float)$sRow['setting_value'], 2, '.', '');
    }
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // Action: Save Default Website Directory Fee
    if ($action === 'save_default_fee') {
        $newDefault = (float)($_POST['default_fee'] ?? 0);
        if ($newDefault <= 0) {
            $error = 'Please enter a valid default fee amount greater than 0.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('website_directory_fee', ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([number_format($newDefault, 2, '.', '')]);
            $defaultFee = number_format($newDefault, 2, '.', '');
            $success = 'Default website directory advertising fee updated to ₱' . number_format($newDefault, 2);
        }
    }

    // Action 1: Set Fee & Assign Due to Member
    if ($action === 'set_directory_fee') {
        $appId = (int)($_POST['app_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $feeAmount = (float)($_POST['fee_amount'] ?? 0);
        $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+14 days'));

        if ($feeAmount <= 0) {
            $error = 'Please enter a valid advertising fee amount greater than 0.';
        } elseif ($userId > 0) {
            try {
                $pdo->beginTransaction();

                // Create due program specifically for this directory advertising fee
                $dStmt = $pdo->prepare("INSERT INTO dues (title, description, amount, due_date, term) 
                                       VALUES ('Website Directory Advertising Fee', 'Annual chapter website directory listing and portfolio showcase', ?, ?, 'Annual')");
                $dStmt->execute([$feeAmount, $dueDate]);
                $dueId = (int)$pdo->lastInsertId();

                // Assign to member in member_dues
                $mStmt = $pdo->prepare("INSERT INTO member_dues (user_id, due_id, status) VALUES (?, ?, 'unpaid')");
                $mStmt->execute([$userId, $dueId]);
                $memberDueId = (int)$pdo->lastInsertId();

                // Update application record
                $aStmt = $pdo->prepare("UPDATE directory_applications 
                                       SET status = 'fee_set', fee_amount = ?, member_due_id = ? 
                                       WHERE user_id = ?");
                $aStmt->execute([$feeAmount, $memberDueId, $userId]);

                $pdo->commit();
                $success = 'Advertising fee of ₱' . number_format($feeAmount, 2) . ' assigned to member successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to assign advertising fee: ' . $e->getMessage();
            }
        }
    }

    // Action: Update existing assigned fee amount
    if ($action === 'update_assigned_fee') {
        $appId = (int)($_POST['app_id'] ?? 0);
        $memberDueId = (int)($_POST['member_due_id'] ?? 0);
        $newAmount = (float)($_POST['new_amount'] ?? 0);

        if ($newAmount <= 0) {
            $error = 'Please enter a valid fee amount greater than 0.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE directory_applications SET fee_amount = ? WHERE id = ?")->execute([$newAmount, $appId]);
                
                if ($memberDueId > 0) {
                    $pdo->prepare("UPDATE member_dues SET custom_amount = ? WHERE id = ?")->execute([$newAmount, $memberDueId]);
                }
                $pdo->commit();
                $success = 'Updated advertising fee to ₱' . number_format($newAmount, 2);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to update fee: ' . $e->getMessage();
            }
        }
    }

    // Action 2: Directly Unlock / Mark Paid
    if ($action === 'manual_unlock') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = $pdo->prepare("UPDATE directory_applications SET status = 'paid' WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Auto-initialize website_members with profile photo if no row exists yet
            $wmCheck = $pdo->prepare("SELECT id FROM website_members WHERE user_id = ?");
            $wmCheck->execute([$userId]);
            if (!$wmCheck->fetch()) {
                $uData = $pdo->prepare("SELECT name, id_number, profile_photo FROM users WHERE id = ?");
                $uData->execute([$userId]);
                $u = $uData->fetch();
                if ($u) {
                    $pdo->prepare("INSERT INTO website_members (user_id, name, id_number, role_title, specialty, location, photo_path, is_published) 
                                   VALUES (?, ?, ?, 'Architect', 'General Practice', 'Quezon', ?, 1)")
                        ->execute([$userId, $u['name'], $u['id_number'], $u['profile_photo'] ?: null]);
                }
            } else {
                $pdo->prepare("UPDATE website_members SET is_published = 1 WHERE user_id = ?")->execute([$userId]);
            }

            // Auto-generate QR code for unlocked member
            if (file_exists(__DIR__ . '/../includes/qr_helper.php')) {
                require_once __DIR__ . '/../includes/qr_helper.php';
                $fStmt = $pdo->prepare("SELECT id FROM website_members WHERE user_id = ?");
                $fStmt->execute([$userId]);
                $wmId = (int)$fStmt->fetchColumn();
                if ($wmId > 0 && function_exists('generate_member_directory_qr')) {
                    generate_member_directory_qr($pdo, $wmId, true);
                }
            }

            $success = 'Directory feature manually unlocked for member.';
        }
    }

    // Action 3: Reject Application
    if ($action === 'reject_app') {
        $appId = (int)($_POST['app_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $reapplyTiming = $_POST['reapply_timing'] ?? 'immediate';
        $customDate = trim($_POST['reapply_custom_date'] ?? '');

        $reapplyAllowed = 1;
        $reapplyAfter = null;

        if ($reapplyTiming === 'never') {
            $reapplyAllowed = 0;
            $reapplyAfter = null;
        } elseif ($reapplyTiming === '14days') {
            $reapplyAllowed = 1;
            $reapplyAfter = date('Y-m-d', strtotime('+14 days'));
        } elseif ($reapplyTiming === '30days') {
            $reapplyAllowed = 1;
            $reapplyAfter = date('Y-m-d', strtotime('+30 days'));
        } elseif ($reapplyTiming === 'custom' && !empty($customDate)) {
            $reapplyAllowed = 1;
            $reapplyAfter = $customDate;
        } else {
            $reapplyAllowed = 1;
            $reapplyAfter = null;
        }

        if ($appId > 0) {
            try {
                $pdo->beginTransaction();

                // Check if application exists
                $aStmt = $pdo->prepare("SELECT da.*, u.name FROM directory_applications da JOIN users u ON da.user_id = u.id WHERE da.id = ?");
                $aStmt->execute([$appId]);
                $appRow = $aStmt->fetch();

                if ($appRow) {
                    // If there's an assigned unpaid member_due, delete it so the member isn't stuck with an unpaid due
                    if (!empty($appRow['member_due_id'])) {
                        $mDueId = (int)$appRow['member_due_id'];
                        $mdCheck = $pdo->prepare("SELECT due_id, status FROM member_dues WHERE id = ?");
                        $mdCheck->execute([$mDueId]);
                        $mdRow = $mdCheck->fetch();

                        if ($mdRow && $mdRow['status'] !== 'paid') {
                            $dueId = (int)$mdRow['due_id'];
                            $pdo->prepare("DELETE FROM member_dues WHERE id = ?")->execute([$mDueId]);
                            if ($dueId > 0) {
                                $pdo->prepare("DELETE FROM dues WHERE id = ? AND title = 'Website Directory Advertising Fee'")->execute([$dueId]);
                            }
                        }
                    }

                    // Update directory application
                    $upd = $pdo->prepare("UPDATE directory_applications 
                                          SET status = 'rejected', 
                                              fee_amount = NULL, 
                                              member_due_id = NULL, 
                                              notes = ?, 
                                              reapply_allowed = ?, 
                                              reapply_after = ?, 
                                              dismissed_notification = 0, 
                                              rejected_at = NOW() 
                                          WHERE id = ?");
                    $upd->execute([$notes ?: null, $reapplyAllowed, $reapplyAfter, $appId]);

                    $pdo->commit();
                    $success = 'Directory application for ' . htmlspecialchars($appRow['name']) . ' declined and removed from pending queues.';
                } else {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = 'Application record not found.';
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to reject application: ' . $e->getMessage();
            }
        }
    }

    // Action 4: Reopen Declined Application
    if ($action === 'reopen_app') {
        $appId = (int)($_POST['app_id'] ?? 0);
        if ($appId > 0) {
            try {
                $upd = $pdo->prepare("UPDATE directory_applications 
                                      SET status = 'pending_fee', 
                                          fee_amount = NULL, 
                                          member_due_id = NULL, 
                                          notes = NULL, 
                                          reapply_allowed = 1, 
                                          reapply_after = NULL, 
                                          dismissed_notification = 0, 
                                          rejected_at = NULL, 
                                          created_at = NOW() 
                                      WHERE id = ?");
                $upd->execute([$appId]);
                $success = 'Application reopened and returned to pending review.';
            } catch (Throwable $e) {
                $error = 'Failed to reopen application: ' . $e->getMessage();
            }
        }
    }
}

// 1. Fetch Pending Applications (Awaiting Fee)
$pendingApps = $pdo->query("SELECT da.*, u.name, u.id_number
    FROM directory_applications da
    JOIN users u ON da.user_id = u.id
    WHERE da.status = 'pending_fee'
    ORDER BY da.created_at DESC")->fetchAll();

// 2. Fetch Applications Awaiting Payment / Verification
$paymentApps = $pdo->query("SELECT da.*, u.name, u.id_number, md.status as payment_status, md.total_paid, md.id as member_due_id_val
    FROM directory_applications da
    JOIN users u ON da.user_id = u.id
    LEFT JOIN member_dues md ON da.member_due_id = md.id
    WHERE da.status = 'fee_set' AND (md.status != 'paid' OR md.status IS NULL)
    ORDER BY da.updated_at DESC")->fetchAll();

// 3. Fetch Active Paid Directory Members
$activeMembers = $pdo->query("SELECT COALESCE(wm.id, da.user_id) as id, wm.id as wm_id, u.id as user_id, u.name, u.id_number, wm.role_title, wm.specialty, wm.company_name, wm.link_url, wm.link_type, wm.photo_path, wm.gallery_json, wm.updated_at as profile_updated, da.id as app_id
    FROM website_members wm
    JOIN users u ON wm.user_id = u.id
    LEFT JOIN directory_applications da ON da.user_id = u.id
    WHERE wm.is_published = 1 OR da.status = 'paid'
    ORDER BY u.name ASC")->fetchAll();

// 4. Fetch Declined Applications History
$declinedApps = [];
try {
    $declinedApps = $pdo->query("SELECT da.*, u.name, u.id_number
        FROM directory_applications da
        JOIN users u ON da.user_id = u.id
        WHERE da.status = 'rejected'
        ORDER BY da.rejected_at DESC, da.updated_at DESC")->fetchAll();
} catch (Throwable $e) {
    try {
        $declinedApps = $pdo->query("SELECT da.*, u.name, u.id_number
            FROM directory_applications da
            JOIN users u ON da.user_id = u.id
            WHERE da.status = 'rejected'
            ORDER BY da.updated_at DESC")->fetchAll();
    } catch (Throwable $e2) {}
}

$page_title = 'Website Directory Manager • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">PORTAL &amp; PUBLIC PROFILES</p>
    <h1>Website Directory &amp; Showcase Manager</h1>
    <p class="page-subtitle">Set directory advertising fees, review architect showcase submissions, and manage published directory profiles.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('website_directory', '', 14); ?> <span><?php echo count($activeMembers); ?> Published</span>
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

<?php if ($success): ?>
  <div class="alert alert-success">
    <div style="display:flex;align-items:center;gap:8px;">
      <?php echo icon('check', '', 18); ?>
      <span><?php echo htmlspecialchars($success); ?></span>
    </div>
  </div>
<?php endif; ?>

<!-- DEFAULT FEE SETTINGS CARD -->
<div class="card" style="margin-bottom: 24px;">
  <form method="post" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save_default_fee">
    <div style="display: flex; align-items: center; gap: 12px;">
      <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245,158,11,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
        <?php echo icon('settings', '', 20); ?>
      </div>
      <div>
        <strong style="color:var(--text-primary); font-size:14px; display:block;">Default Directory Advertising Fee</strong>
        <span class="muted" style="font-size:12px;">Pre-filled base amount when assigning advertising dues to new applicants</span>
      </div>
    </div>
    <div style="display:flex; align-items:center; gap:8px;">
      <div style="display:flex; align-items:center; gap:4px;">
        <span style="font-weight:800; color:var(--accent-primary); font-size:15px;">₱</span>
        <input type="number" step="0.01" name="default_fee" value="<?php echo htmlspecialchars($defaultFee); ?>" required style="width:120px; padding:7px 10px; font-size:14px; font-weight:700;">
      </div>
      <button type="submit" class="btn btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('check', '', 14); ?> <span>Save Default Fee</span>
      </button>
    </div>
  </form>
</div>

<!-- ================= QUEUE 1: PENDING DIRECTORY APPLICATIONS ================= -->
<div class="card" style="margin-bottom: 24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <span style="color: #f59e0b;"><?php echo icon('clock', '', 18); ?></span>
      <h2 style="font-size:16px; margin:0;">New Directory Applications</h2>
    </div>
    <span class="badge-pill badge-pending"><?php echo count($pendingApps); ?> Pending</span>
  </div>

  <?php if (empty($pendingApps)): ?>
    <div style="text-align:center; padding:32px 12px; color:var(--text-secondary);">
      <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59,130,246,0.1); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
        <?php echo icon('check', '', 24); ?>
      </div>
      <strong style="display: block; font-size: 14px; color: var(--text-primary);">No pending directory applications!</strong>
      <p class="muted" style="font-size:12px; margin-top:4px;">When members apply to be featured on the chapter directory, they will appear here to have their fee set.</p>
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Applicant Name</th>
            <th>PRC ID No.</th>
            <th>Application Date</th>
            <th>Set Advertising Fee &amp; Due Date</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingApps as $p): ?>
            <tr>
              <td><strong style="color: var(--text-primary);"><?php echo htmlspecialchars($p['name']); ?></strong></td>
              <td><code><?php echo htmlspecialchars($p['id_number']); ?></code></td>
              <td><span class="muted" style="font-size:12px;"><?php echo date('M d, Y h:i A', strtotime($p['created_at'])); ?></span></td>
              <td>
                <form method="post" id="feeForm_<?php echo $p['id']; ?>" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;"
                      data-confirm="Assign advertising fee for <?php echo htmlspecialchars($p['name']); ?>?"
                      data-confirm-title="Assign Directory Advertising Fee"
                      data-confirm-btn="Assign Fee"
                      data-confirm-class="btn-success">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="set_directory_fee">
                  <input type="hidden" name="app_id" value="<?php echo $p['id']; ?>">
                  <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                  
                  <div style="display:flex; align-items:center; gap:4px;">
                    <span style="font-weight:700; color:var(--accent-primary);">₱</span>
                    <input type="number" step="0.01" name="fee_amount" value="<?php echo htmlspecialchars($defaultFee); ?>" required placeholder="Fee amount" style="width:100px; padding:6px 10px; font-size:13px; font-weight:700;">
                  </div>

                  <div style="display:flex; align-items:center; gap:4px;">
                    <span class="muted" style="font-size:11px;">Due:</span>
                    <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" style="padding:5px 8px; font-size:12px; width: 140px;">
                  </div>
                </form>
              </td>
              <td style="white-space:nowrap; text-align: right;">
                <div style="display:inline-flex; align-items:center; gap:6px;">
                  <button type="submit" form="feeForm_<?php echo $p['id']; ?>" class="btn btn-sm btn-success" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('check', '', 12); ?> <span>Assign Fee</span>
                  </button>
                  <button type="button" class="btn btn-sm btn-danger" style="display: inline-flex; align-items: center; gap: 4px;" onclick="openRejectModal(<?php echo (int)$p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['name'])); ?>', '<?php echo htmlspecialchars(addslashes($p['id_number'])); ?>')">
                    <?php echo icon('x', '', 12); ?> <span>Reject</span>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ================= QUEUE 2: AWAITING PAYMENT / VERIFICATION ================= -->
<div class="card" style="margin-bottom: 24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <span style="color: #3b82f6;"><?php echo icon('payments', '', 18); ?></span>
      <h2 style="font-size:16px; margin:0;">Awaiting Member Payment &amp; Verification</h2>
    </div>
    <span class="badge-pill badge-partial"><?php echo count($paymentApps); ?> Active</span>
  </div>

  <?php if (empty($paymentApps)): ?>
    <p class="muted" style="font-size:13px; text-align: center; padding: 24px;">No members currently in pending directory payment state.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>PRC ID No.</th>
            <th>Assigned Fee</th>
            <th>Payment Status</th>
            <th style="text-align: right;">Actions / Fee Adjustment</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paymentApps as $pa): ?>
            <tr>
              <td><strong style="color: var(--text-primary);"><?php echo htmlspecialchars($pa['name']); ?></strong></td>
              <td><code><?php echo htmlspecialchars($pa['id_number']); ?></code></td>
              <td>
                <strong style="color: #10b981; font-size:14px;">₱<?php echo number_format($pa['fee_amount'], 2); ?></strong>
              </td>
              <td>
                <?php if ($pa['payment_status'] === 'pending'): ?>
                  <span class="badge-pill badge-pending">Payment Submitted (Pending Verification)</span>
                <?php else: ?>
                  <span class="badge-pill badge-unpaid">Unpaid</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <div style="display:flex; align-items:center; justify-content: flex-end; gap:8px; flex-wrap:wrap;">
                  <!-- Change/Edit Fee Form -->
                  <form method="post" style="display:flex; align-items:center; gap:4px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_assigned_fee">
                    <input type="hidden" name="app_id" value="<?php echo $pa['id']; ?>">
                    <input type="hidden" name="member_due_id" value="<?php echo $pa['member_due_id_val'] ?? 0; ?>">
                    <input type="number" step="0.01" name="new_amount" value="<?php echo htmlspecialchars($pa['fee_amount']); ?>" style="width:85px; padding:4px 6px; font-size:12px; font-weight:700;">
                    <button type="submit" class="btn btn-sm btn-secondary" style="font-size:11px; padding:5px 8px;">Update</button>
                  </form>

                  <!-- Force Unlock -->
                  <form method="post" style="display:inline-block;"
                        data-confirm="Manually unlock Website Directory feature for <?php echo htmlspecialchars($pa['name']); ?>?"
                        data-confirm-title="Manual Feature Unlock"
                        data-confirm-btn="Unlock Feature"
                        data-confirm-class="btn-success">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="manual_unlock">
                    <input type="hidden" name="user_id" value="<?php echo $pa['user_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-success" style="font-size:11px; padding:5px 10px; display: inline-flex; align-items: center; gap: 4px;">
                      <?php echo icon('check', '', 11); ?> <span>Unlock</span>
                    </button>
                  </form>

                  <button type="button" class="btn btn-sm btn-danger" style="font-size:11px; padding:5px 9px; display: inline-flex; align-items: center; gap: 4px;" onclick="openRejectModal(<?php echo (int)$pa['id']; ?>, '<?php echo htmlspecialchars(addslashes($pa['name'])); ?>', '<?php echo htmlspecialchars(addslashes($pa['id_number'])); ?>')">
                    <?php echo icon('x', '', 11); ?> <span>Reject</span>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ================= QUEUE 3: ACTIVE UNLOCKED DIRECTORY MEMBERS ================= -->
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <span style="color: #10b981;"><?php echo icon('good_members', '', 18); ?></span>
      <h2 style="font-size:16px; margin:0;">Published Directory Profiles</h2>
    </div>
    <span class="badge-pill badge-paid"><?php echo count($activeMembers); ?> Published</span>
  </div>

  <?php if (empty($activeMembers)): ?>
    <p class="muted" style="font-size:13px; text-align: center; padding: 24px;">No directory members have completed verification yet.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>PRC ID No.</th>
            <th>Company / Firm</th>
            <th>Role &amp; Specialty</th>
            <th>Portfolio Showcase &amp; Link</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($activeMembers as $am): 
            $photoCount = 0;
            if (!empty($am['gallery_json'])) {
                $g = json_decode($am['gallery_json'], true);
                if (is_array($g)) $photoCount = count($g);
            } elseif (!empty($am['photo_path'])) {
                $photoCount = 1;
            }
            $linkIcon = !empty($am['link_url']) && function_exists('detect_social_link_type') 
              ? detect_social_link_type($am['link_url'], $am['link_type'] ?? 'auto') 
              : 'globe';
          ?>
            <tr>
              <td>
                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($am['name']); ?></strong>
                <?php
                  $isStandingOk = function_exists('is_good_member') ? is_good_member($pdo, (int)$am['user_id']) : true;
                ?>
                <div style="margin-top: 4px; display: flex; align-items: center; gap: 6px;">
                  <?php if ($isStandingOk): ?>
                    <span class="badge-pill badge-paid" style="font-size: 10px; padding: 2px 7px; display: inline-flex; align-items: center; gap: 4px;">
                      <?php echo icon('check', '', 10); ?> Live on Website
                    </span>
                  <?php else: ?>
                    <span class="badge-pill badge-unpaid" style="font-size: 10px; padding: 2px 7px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: inline-flex; align-items: center; gap: 4px;" title="Profile is hidden from public directory because member has overdue dues or good standing hold">
                      <?php echo icon('alert', '', 10); ?> Hidden: Standing Hold
                    </span>
                    <a href="good_members.php" style="font-size: 10.5px; color: var(--accent-primary); text-decoration: underline;" title="Manage Good Standing">
                      Manage &rarr;
                    </a>
                  <?php endif; ?>
                </div>
              </td>
              <td><code><?php echo htmlspecialchars($am['id_number']); ?></code></td>
              <td>
                <?php if (!empty($am['company_name'])): ?>
                  <span style="display:inline-flex; align-items:center; gap:5px; font-weight:600; color:var(--text-primary); font-size:13px;">
                    <?php echo icon('briefcase', '', 13); ?> <?php echo htmlspecialchars($am['company_name']); ?>
                  </span>
                <?php else: ?>
                  <span class="muted" style="font-size:12px;">None specified</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-size:13px; font-weight:600; color:var(--accent-primary);"><?php echo htmlspecialchars($am['role_title'] ?: 'Architect'); ?></div>
                <div class="muted" style="font-size:11.5px;"><?php echo htmlspecialchars($am['specialty'] ?: 'General Practice'); ?></div>
              </td>
              <td>
                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                  <span class="badge-pill badge-paid" style="font-size:11px; display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('image', '', 12); ?> <?php echo $photoCount; ?> Photo<?php echo $photoCount !== 1 ? 's' : ''; ?>
                  </span>
                  <?php if (!empty($am['link_url'])): ?>
                    <a href="<?php echo htmlspecialchars($am['link_url']); ?>" target="_blank" rel="noopener noreferrer" class="badge-pill" style="font-size:11px; text-decoration:none; background:rgba(59,130,246,0.12); color:#60a5fa; display:inline-flex; align-items:center; gap:4px;">
                      <?php echo icon($linkIcon === 'website' ? 'globe' : $linkIcon, '', 11); ?>
                      <span><?php echo ucfirst($linkIcon); ?></span>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
              <td style="text-align: right;">
                <div style="display:inline-flex; align-items:center; gap:6px; justify-content:flex-end;">
                  <a href="download_qr.php?id=<?php echo (int)$am['id']; ?>" class="btn btn-sm btn-primary" style="font-size:11px; padding:4px 9px; display:inline-flex; align-items:center; gap:4px;" title="Download Public Directory QR Code">
                    <?php echo icon('download', '', 12); ?> <span>Download QR</span>
                  </a>
                  <a href="<?php echo BASE_URL; ?>/profile/<?php echo (int)$am['id']; ?>" target="_blank" class="btn btn-sm btn-secondary" style="font-size:11px; padding:4px 9px; display:inline-flex; align-items:center; gap:4px;">
                    <?php echo icon('external_link', '', 12); ?> <span>View Profile</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ================= QUEUE 4: DECLINED APPLICATIONS HISTORY ================= -->
<div class="card" style="margin-top: 24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <span style="color: #ef4444;"><?php echo icon('x', '', 18); ?></span>
      <h2 style="font-size:16px; margin:0;">Declined Applications History</h2>
    </div>
    <span class="badge-pill badge-unpaid"><?php echo count($declinedApps); ?> Declined</span>
  </div>

  <?php if (empty($declinedApps)): ?>
    <p class="muted" style="font-size:13px; text-align: center; padding: 20px;">No applications have been declined.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>PRC ID No.</th>
            <th>Declined Date</th>
            <th>Reason / Notes</th>
            <th>Re-Application Rule</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($declinedApps as $da): ?>
            <tr>
              <td><strong style="color: var(--text-primary);"><?php echo htmlspecialchars($da['name']); ?></strong></td>
              <td><code><?php echo htmlspecialchars($da['id_number']); ?></code></td>
              <td><span class="muted" style="font-size:12px;"><?php echo $da['rejected_at'] ? date('M d, Y h:i A', strtotime($da['rejected_at'])) : date('M d, Y', strtotime($da['updated_at'])); ?></span></td>
              <td style="max-width:260px;">
                <?php if (!empty($da['notes'])): ?>
                  <span style="font-size:12.5px; color:var(--text-primary);"><?php echo htmlspecialchars($da['notes']); ?></span>
                <?php else: ?>
                  <span class="muted" style="font-size:12px; font-style:italic;">No remarks provided</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$da['reapply_allowed']): ?>
                  <span class="badge-pill badge-unpaid" style="font-size:11px;">Locked (Not Allowed)</span>
                <?php elseif (!empty($da['reapply_after']) && strtotime($da['reapply_after']) > strtotime(date('Y-m-d'))): ?>
                  <span class="badge-pill badge-pending" style="font-size:11px;">Allowed after <?php echo date('M d, Y', strtotime($da['reapply_after'])); ?></span>
                <?php else: ?>
                  <span class="badge-pill badge-paid" style="font-size:11px;">Eligible to Re-apply</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right; white-space:nowrap;">
                <form method="post" style="display:inline-block;"
                      data-confirm="Reopen directory application for <?php echo htmlspecialchars($da['name']); ?> and return to pending fee assignment?"
                      data-confirm-title="Reopen Application"
                      data-confirm-btn="Reopen"
                      data-confirm-class="btn-primary">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="reopen_app">
                  <input type="hidden" name="app_id" value="<?php echo $da['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="font-size:11px; padding:4px 8px; display:inline-flex; align-items:center; gap:4px;">
                    <?php echo icon('sparkles', '', 11); ?> <span>Reopen</span>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- REJECT DIRECTORY APPLICATION MODAL -->
<div id="rejectModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.75);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #131d33);border-radius:16px;max-width:520px;width:100%;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.6);border:1px solid var(--border-color);">
    <form method="post" action="website_directory.php">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="reject_app">
      <input type="hidden" name="app_id" id="rejectModalAppId" value="">
      
      <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);">
        <div style="display:flex;align-items:center;gap:8px;">
          <span style="color:#ef4444;"><?php echo icon('x', '', 18); ?></span>
          <h3 style="margin:0;font-size:16px;color:var(--text-primary);">Decline Directory Application</h3>
        </div>
        <button type="button" onclick="closeRejectModal()" style="border:none;background:transparent;cursor:pointer;color:var(--text-primary);display:flex;align-items:center;padding:4px;">
          <?php echo icon('x', '', 18); ?>
        </button>
      </div>

      <div style="padding:20px;">
        <p style="margin:0 0 14px 0; font-size:13px; color:var(--text-secondary);">
          You are declining the directory application for <strong id="rejectModalMemberName" style="color:var(--text-primary);"></strong> (<code id="rejectModalIdNumber"></code>).
        </p>

        <!-- Optional Reason / Remarks -->
        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">
            Rejection Reason / Notes <span class="muted" style="font-weight:normal;">(Optional &mdash; visible to member)</span>
          </label>
          <textarea name="notes" id="rejectNotes" rows="3" placeholder="e.g. Please update your profile photo, or complete chapter good-standing requirements before re-applying..." style="width:100%; padding:10px; border-radius:8px; font-size:13px; border:1px solid var(--border-color); background:var(--input-bg, rgba(0,0,0,0.2)); color:var(--text-primary); box-sizing:border-box;"></textarea>
        </div>

        <!-- Re-application Timing -->
        <div style="margin-bottom:12px;">
          <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--text-primary);">
            When can the member re-apply?
          </label>
          <select name="reapply_timing" id="reapplyTimingSelect" onchange="toggleReapplyCustomDate(this)" style="width:100%; padding:9px 12px; border-radius:8px; font-size:13px; border:1px solid var(--border-color); background:var(--card-bg, #1e293b); color:var(--text-primary); box-sizing:border-box;">
            <option value="immediate">Allow re-application immediately</option>
            <option value="14days">Allow re-application after 14 days</option>
            <option value="30days">Allow re-application after 30 days</option>
            <option value="custom">Set custom re-application date...</option>
            <option value="never">Do not allow re-application (Locked)</option>
          </select>
        </div>

        <div id="reapplyCustomDateWrap" style="display:none; margin-bottom:16px;">
          <label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:var(--text-primary);">
            Earliest Re-Application Date
          </label>
          <input type="date" name="reapply_custom_date" id="reapplyCustomDate" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" style="width:100%; padding:8px 10px; border-radius:8px; font-size:13px; border:1px solid var(--border-color); background:var(--input-bg, rgba(0,0,0,0.2)); color:var(--text-primary); box-sizing:border-box;">
        </div>

        <div style="background:rgba(239,68,68,0.06); border:1px solid rgba(239,68,68,0.2); border-radius:8px; padding:10px 12px; font-size:12px; color:var(--text-secondary);">
          <?php echo icon('alert', '', 14); ?> This will immediately remove the request from your active queue and admin notification bell. A notification banner will be shown on the member's portal.
        </div>
      </div>

      <div style="padding:14px 20px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--border-color);background:rgba(0,0,0,0.05);">
        <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
        <button type="submit" class="btn btn-danger" style="display:inline-flex;align-items:center;gap:6px;">
          <?php echo icon('x', '', 14); ?> <span>Decline Application</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openRejectModal(appId, memberName, idNumber) {
  document.getElementById('rejectModalAppId').value = appId;
  document.getElementById('rejectModalMemberName').innerText = memberName;
  document.getElementById('rejectModalIdNumber').innerText = idNumber;
  document.getElementById('rejectNotes').value = '';
  document.getElementById('reapplyTimingSelect').value = 'immediate';
  document.getElementById('reapplyCustomDateWrap').style.display = 'none';
  document.getElementById('rejectModal').style.display = 'flex';
}

function closeRejectModal() {
  document.getElementById('rejectModal').style.display = 'none';
}

function toggleReapplyCustomDate(select) {
  var wrap = document.getElementById('reapplyCustomDateWrap');
  if (select.value === 'custom') {
    wrap.style.display = 'block';
    document.getElementById('reapplyCustomDate').required = true;
  } else {
    wrap.style.display = 'none';
    document.getElementById('reapplyCustomDate').required = false;
  }
}

document.getElementById('rejectModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeRejectModal();
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
