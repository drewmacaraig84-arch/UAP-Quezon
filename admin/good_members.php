<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$success = '';

// Handle POST actions: revoke, restore, grant good standing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND role = 'member'");
    $stmt->execute([$user_id]);
    $targetMember = $stmt->fetch();

    if (!$targetMember) {
        $error = 'Member account not found.';
    } elseif ($action === 'revoke_good_standing') {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            $reason = 'Administrative hold placed by Chapter Administration.';
        }
        if (set_member_good_standing($pdo, $user_id, 'revoked', $reason)) {
            $success = 'Good standing status revoked for ' . htmlspecialchars($targetMember['name']) . '.';
        } else {
            $error = 'Failed to revoke good standing status.';
        }
    } elseif ($action === 'restore_good_standing') {
        if (set_member_good_standing($pdo, $user_id, 'auto', null)) {
            $success = 'Good standing status restored to automatic dues settlement for ' . htmlspecialchars($targetMember['name']) . '.';
        } else {
            $error = 'Failed to restore good standing status.';
        }
    } elseif ($action === 'grant_good_standing') {
        $reason = trim($_POST['reason'] ?? '');
        if (set_member_good_standing($pdo, $user_id, 'granted', $reason ?: 'Administrative exemption granted.')) {
            $success = 'Good standing status explicitly granted for ' . htmlspecialchars($targetMember['name']) . '.';
        } else {
            $error = 'Failed to grant good standing status.';
        }
    }
}

// Fetch members with dues summary and standing columns
try {
    $allMembers = $pdo->query("SELECT u.id, u.name, u.id_number, u.status, u.profile_photo,
        COALESCE(u.good_standing_override, 'auto') AS good_standing_override,
        u.good_standing_reason,
        u.good_standing_updated_at,
        COUNT(md.id) AS total_dues,
        SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
        SUM(COALESCE(md.custom_amount, d.amount)) AS total_amount,
        SUM(md.total_paid) AS total_paid_sum,
        SUM(COALESCE(md.custom_amount, d.amount)) - SUM(md.total_paid) AS remaining_balance
        FROM users u
        LEFT JOIN member_dues md ON md.user_id = u.id
        LEFT JOIN dues d ON md.due_id = d.id
        WHERE u.role = 'member' AND u.status = 'approved'
        GROUP BY u.id, u.name, u.id_number, u.status, u.profile_photo, u.good_standing_override, u.good_standing_reason, u.good_standing_updated_at
        ORDER BY u.name ASC")->fetchAll();
} catch (Throwable $e) {
    // Fallback if columns being initialized
    $allMembers = $pdo->query("SELECT u.id, u.name, u.id_number, u.status, u.profile_photo,
        'auto' AS good_standing_override,
        NULL AS good_standing_reason,
        NULL AS good_standing_updated_at,
        COUNT(md.id) AS total_dues,
        SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
        SUM(COALESCE(md.custom_amount, d.amount)) AS total_amount,
        SUM(md.total_paid) AS total_paid_sum,
        SUM(COALESCE(md.custom_amount, d.amount)) - SUM(md.total_paid) AS remaining_balance
        FROM users u
        LEFT JOIN member_dues md ON md.user_id = u.id
        LEFT JOIN dues d ON md.due_id = d.id
        WHERE u.role = 'member' AND u.status = 'approved'
        GROUP BY u.id, u.name, u.id_number, u.status, u.profile_photo
        ORDER BY u.name ASC")->fetchAll();
}

$certifiedMembers = [];
$revokedMembers = [];
$pendingSettlementMembers = [];

foreach ($allMembers as &$m) {
    $standing = get_member_standing_details($pdo, $m['id']);
    $m['standing_details'] = $standing;

    if ($standing['is_revoked']) {
        $revokedMembers[] = $m;
    } elseif ($standing['is_good']) {
        $certifiedMembers[] = $m;
    } else {
        $pendingSettlementMembers[] = $m;
    }
}
unset($m);

$activeTab = $_GET['tab'] ?? 'certified';
if (!in_array($activeTab, ['certified', 'pending', 'revoked', 'all'], true)) {
    $activeTab = 'certified';
}

$page_title = 'Good Standing Roster & Compliance • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">COMPLIANCE &amp; CERTIFICATION</p>
    <h1>Good Standing Roster</h1>
    <p class="page-subtitle">Manage chapter good standing certifications, review compliance, or place administrative holds / revocations.</p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <div class="hero-badge" style="background: rgba(16,185,129,0.12); color: #10b981; border-color: rgba(16,185,129,0.3);">
      <?php echo icon('good_members', '', 14); ?> <span><?php echo count($certifiedMembers); ?> Certified Members</span>
    </div>
    <?php if (count($pendingSettlementMembers) > 0): ?>
      <div class="hero-badge" style="background: rgba(245,158,11,0.12); color: #f59e0b; border-color: rgba(245,158,11,0.3);">
        <?php echo icon('clock', '', 14); ?> <span><?php echo count($pendingSettlementMembers); ?> Pending Settlement</span>
      </div>
    <?php endif; ?>
    <?php if (count($revokedMembers) > 0): ?>
      <div class="hero-badge" style="background: rgba(239,68,68,0.12); color: #ef4444; border-color: rgba(239,68,68,0.3);">
        <?php echo icon('alert', '', 14); ?> <span><?php echo count($revokedMembers); ?> Revoked / On Hold</span>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-error" style="margin-bottom: 20px;">
    <div style="display:flex;align-items:center;gap:8px;">
      <?php echo icon('alert', '', 18); ?>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success" style="margin-bottom: 20px;">
    <div style="display:flex;align-items:center;gap:8px;">
      <?php echo icon('check', '', 18); ?>
      <span><?php echo htmlspecialchars($success); ?></span>
    </div>
  </div>
<?php endif; ?>

<!-- TAB CONTROLS -->
<div style="display: flex; gap: 8px; margin-bottom: 18px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
  <a href="good_members.php?tab=certified" class="btn btn-sm <?php echo $activeTab === 'certified' ? 'btn-primary' : 'btn-secondary'; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
    <?php echo icon('good_members', '', 14); ?>
    <span>Certified Good Standing</span>
    <span class="badge-pill <?php echo $activeTab === 'certified' ? 'badge-paid' : ''; ?>" style="font-size: 11px; padding: 2px 7px;"><?php echo count($certifiedMembers); ?></span>
  </a>
  <a href="good_members.php?tab=pending" class="btn btn-sm <?php echo $activeTab === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
    <?php echo icon('clock', '', 14); ?>
    <span>Pending Dues Settlement</span>
    <span class="badge-pill <?php echo count($pendingSettlementMembers) > 0 ? 'badge-pending' : ''; ?>" style="font-size: 11px; padding: 2px 7px;"><?php echo count($pendingSettlementMembers); ?></span>
  </a>
  <a href="good_members.php?tab=revoked" class="btn btn-sm <?php echo $activeTab === 'revoked' ? 'btn-primary' : 'btn-secondary'; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
    <?php echo icon('alert', '', 14); ?>
    <span>Standing Revoked / On Hold</span>
    <span class="badge-pill <?php echo count($revokedMembers) > 0 ? 'badge-unpaid' : ''; ?>" style="font-size: 11px; padding: 2px 7px;"><?php echo count($revokedMembers); ?></span>
  </a>
  <a href="good_members.php?tab=all" class="btn btn-sm <?php echo $activeTab === 'all' ? 'btn-primary' : 'btn-secondary'; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
    <?php echo icon('members', '', 14); ?>
    <span>All Approved Members</span>
    <span class="badge-pill" style="font-size: 11px; padding: 2px 7px;"><?php echo count($allMembers); ?></span>
  </a>
</div>

<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div style="flex: 1; min-width: 260px; position: relative;">
      <input type="text" id="rosterSearchInput" placeholder="Search roster by architect name or PRC number..." style="padding-left: 36px; width: 100%;">
      <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);">
        <?php echo icon('search', '', 16); ?>
      </div>
    </div>
    <a href="export_csv.php" class="btn btn-sm btn-success" style="display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('download', '', 14); ?> <span>Export Financial Dues Roster</span>
    </a>
  </div>

  <?php if ($activeTab === 'certified'): ?>
    <!-- TAB: CERTIFIED GOOD STANDING -->
    <?php if (empty($certifiedMembers)): ?>
      <div style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
        <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(245,158,11,0.1); color: var(--accent-primary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
          <?php echo icon('info', '', 28); ?>
        </div>
        <strong style="display: block; font-size: 16px; color: var(--text-primary);">No certified good standing members found</strong>
        <p class="muted" style="margin-top: 4px; font-size: 13px;">Members will appear here once dues obligations are cleared or within the active grace window.</p>
      </div>
    <?php else: ?>
      <div class="table-shell">
        <table id="rosterTable">
          <thead>
            <tr>
              <th>Member Architect</th>
              <th>PRC ID Number</th>
              <th>Dues Assigned</th>
              <th>Total Paid</th>
              <th>Standing Status</th>
              <th style="text-align: right;">Admin Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($certifiedMembers as $member): ?>
              <?php
                $avatar = !empty($member['profile_photo']) ? (str_starts_with($member['profile_photo'], 'http') ? $member['profile_photo'] : BASE_URL . '/' . ltrim($member['profile_photo'], '/')) : null;
                $initials = strtoupper(substr($member['name'] ?? 'M', 0, 1) . substr(strrchr($member['name'] ?? '', ' ') ?: ($member['name'] ?? 'M'), 1, 1));
              ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="table-avatar-wrap">
                      <?php if ($avatar): ?>
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="" class="table-avatar-img">
                      <?php else: ?>
                        <?php echo htmlspecialchars($initials); ?>
                      <?php endif; ?>
                    </div>
                    <div>
                      <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($member['name']); ?></strong>
                      <?php if (($member['standing_details']['override'] ?? '') === 'granted'): ?>
                        <div style="font-size: 11px; color: #10b981;">(Explicit Exemption)</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td><code><?php echo htmlspecialchars($member['id_number']); ?></code></td>
                <td><?php echo (int)$member['total_dues']; ?> items</td>
                <td><strong style="color: #10b981;">₱<?php echo number_format((float)($member['total_paid_sum'] ?? 0), 2); ?></strong></td>
                <td>
                  <span class="badge-pill badge-paid" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('check', '', 12); ?> Good Standing
                  </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <button type="button" class="btn btn-sm btn-secondary" style="color: #ef4444; border-color: rgba(239,68,68,0.3); display: inline-flex; align-items: center; gap: 4px;"
                          onclick="openRevokeModal(<?php echo (int)$member['id']; ?>, '<?php echo htmlspecialchars(addslashes($member['name'])); ?>', '<?php echo htmlspecialchars(addslashes($member['id_number'])); ?>')">
                    <?php echo icon('alert', '', 12); ?> <span>Revoke Standing</span>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  <?php elseif ($activeTab === 'pending'): ?>
    <!-- TAB: PENDING SETTLEMENT -->
    <?php if (empty($pendingSettlementMembers)): ?>
      <div style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
        <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(16,185,129,0.1); color: #10b981; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
          <?php echo icon('check', '', 28); ?>
        </div>
        <strong style="display: block; font-size: 16px; color: var(--text-primary);">All members have settled dues</strong>
        <p class="muted" style="margin-top: 4px; font-size: 13px;">No approved chapter members currently have overdue or pending dues obligations.</p>
      </div>
    <?php else: ?>
      <div class="table-shell">
        <table id="rosterTable">
          <thead>
            <tr>
              <th>Member Architect</th>
              <th>PRC ID Number</th>
              <th>Dues Settlement</th>
              <th>Remaining Balance</th>
              <th>Standing Status</th>
              <th style="text-align: right;">Admin Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingSettlementMembers as $member): ?>
              <?php
                $avatar = !empty($member['profile_photo']) ? (str_starts_with($member['profile_photo'], 'http') ? $member['profile_photo'] : BASE_URL . '/' . ltrim($member['profile_photo'], '/')) : null;
                $initials = strtoupper(substr($member['name'] ?? 'M', 0, 1) . substr(strrchr($member['name'] ?? '', ' ') ?: ($member['name'] ?? 'M'), 1, 1));
              ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="table-avatar-wrap">
                      <?php if ($avatar): ?>
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="" class="table-avatar-img">
                      <?php else: ?>
                        <?php echo htmlspecialchars($initials); ?>
                      <?php endif; ?>
                    </div>
                    <div>
                      <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($member['name']); ?></strong>
                    </div>
                  </div>
                </td>
                <td><code><?php echo htmlspecialchars($member['id_number']); ?></code></td>
                <td>
                  <span style="font-size: 13px; font-weight: 600;"><?php echo (int)$member['paid_count']; ?>/<?php echo (int)$member['total_dues']; ?></span>
                  <span class="muted" style="font-size: 11px;"> paid</span>
                </td>
                <td>
                  <strong style="color: #ef4444;">
                    ₱<?php echo number_format(max(0, (float)($member['remaining_balance'] ?? 0)), 2); ?>
                  </strong>
                </td>
                <td>
                  <span class="badge-pill badge-pending" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('clock', '', 11); ?> Pending Settlement
                  </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <form method="post" style="display: inline-block;"
                        data-confirm="Grant Good Standing status to <?php echo htmlspecialchars($member['name']); ?>? This grants an explicit administrative certification override."
                        data-confirm-title="Grant Good Standing"
                        data-confirm-btn="Grant Standing"
                        data-confirm-class="btn-primary">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="grant_good_standing">
                    <input type="hidden" name="user_id" value="<?php echo (int)$member['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 4px;" title="Grant administrative Good Standing exemption">
                      <?php echo icon('check', '', 12); ?> <span>Grant Standing</span>
                    </button>
                  </form>
                  <a href="payments.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 4px;" title="Verify or record dues payments">
                    <?php echo icon('payment', '', 12); ?> <span>Verify Pay</span>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  <?php elseif ($activeTab === 'revoked'): ?>
    <!-- TAB: STANDING REVOKED / ON HOLD -->
    <?php if (empty($revokedMembers)): ?>
      <div style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
        <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(16,185,129,0.1); color: #10b981; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
          <?php echo icon('check', '', 28); ?>
        </div>
        <strong style="display: block; font-size: 16px; color: var(--text-primary);">No revoked standing records</strong>
        <p class="muted" style="margin-top: 4px; font-size: 13px;">There are currently no members with manually revoked good standing or administrative holds.</p>
      </div>
    <?php else: ?>
      <div class="table-shell">
        <table id="rosterTable">
          <thead>
            <tr>
              <th>Member Architect</th>
              <th>PRC ID Number</th>
              <th>Revocation Reason / Note</th>
              <th>Date Revoked</th>
              <th style="text-align: right;">Admin Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($revokedMembers as $member): ?>
              <?php
                $avatar = !empty($member['profile_photo']) ? (str_starts_with($member['profile_photo'], 'http') ? $member['profile_photo'] : BASE_URL . '/' . ltrim($member['profile_photo'], '/')) : null;
                $initials = strtoupper(substr($member['name'] ?? 'M', 0, 1) . substr(strrchr($member['name'] ?? '', ' ') ?: ($member['name'] ?? 'M'), 1, 1));
              ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="table-avatar-wrap">
                      <?php if ($avatar): ?>
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="" class="table-avatar-img">
                      <?php else: ?>
                        <?php echo htmlspecialchars($initials); ?>
                      <?php endif; ?>
                    </div>
                    <div>
                      <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($member['name']); ?></strong>
                      <div><span class="badge-pill badge-unpaid" style="font-size: 10px;">Standing Revoked</span></div>
                    </div>
                  </div>
                </td>
                <td><code><?php echo htmlspecialchars($member['id_number']); ?></code></td>
                <td>
                  <div style="max-width: 320px; font-size: 13px; color: #ef4444; background: rgba(239,68,68,0.06); padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(239,68,68,0.18);">
                    <?php echo htmlspecialchars($member['good_standing_reason'] ?: 'Administrative hold placed by Chapter Administration.'); ?>
                  </div>
                </td>
                <td>
                  <span class="muted" style="font-size: 12px;">
                    <?php echo !empty($member['good_standing_updated_at']) ? date('M d, Y g:i A', strtotime($member['good_standing_updated_at'])) : '—'; ?>
                  </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <form method="post" style="display: inline-block;"
                        data-confirm="Restore good standing status for <?php echo htmlspecialchars($member['name']); ?>? The member will be evaluated automatically by their dues settlement."
                        data-confirm-title="Restore Good Standing"
                        data-confirm-btn="Restore Standing"
                        data-confirm-class="btn-success">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="restore_good_standing">
                    <input type="hidden" name="user_id" value="<?php echo (int)$member['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-success" style="display: inline-flex; align-items: center; gap: 4px;">
                      <?php echo icon('check', '', 12); ?> <span>Restore Standing</span>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- TAB: ALL MEMBERS -->
    <div class="table-shell">
      <table id="rosterTable">
        <thead>
          <tr>
            <th>Member Architect</th>
            <th>PRC ID Number</th>
            <th>Dues Settlement</th>
            <th>Remaining Balance</th>
            <th>Standing Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allMembers as $member): ?>
            <?php
              $standing = $member['standing_details'] ?? get_member_standing_details($pdo, $member['id']);
              $avatar = !empty($member['profile_photo']) ? (str_starts_with($member['profile_photo'], 'http') ? $member['profile_photo'] : BASE_URL . '/' . ltrim($member['profile_photo'], '/')) : null;
              $initials = strtoupper(substr($member['name'] ?? 'M', 0, 1) . substr(strrchr($member['name'] ?? '', ' ') ?: ($member['name'] ?? 'M'), 1, 1));
            ?>
            <tr>
              <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                  <div class="table-avatar-wrap">
                    <?php if ($avatar): ?>
                      <img src="<?php echo htmlspecialchars($avatar); ?>" alt="" class="table-avatar-img">
                    <?php else: ?>
                      <?php echo htmlspecialchars($initials); ?>
                    <?php endif; ?>
                  </div>
                  <div>
                    <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($member['name']); ?></strong>
                  </div>
                </div>
              </td>
              <td><code><?php echo htmlspecialchars($member['id_number']); ?></code></td>
              <td>
                <span style="font-size: 13px; font-weight: 600;"><?php echo (int)$member['paid_count']; ?>/<?php echo (int)$member['total_dues']; ?></span>
                <span class="muted" style="font-size: 11px;"> paid (₱<?php echo number_format((float)($member['total_paid_sum'] ?? 0), 2); ?>)</span>
              </td>
              <td>
                <strong style="color: <?php echo ((float)($member['remaining_balance'] ?? 0) > 0) ? '#ef4444' : '#10b981'; ?>;">
                  ₱<?php echo number_format(max(0, (float)($member['remaining_balance'] ?? 0)), 2); ?>
                </strong>
              </td>
              <td>
                <?php if ($standing['is_revoked']): ?>
                  <span class="badge-pill badge-unpaid" style="display: inline-flex; align-items: center; gap: 4px;" title="<?php echo htmlspecialchars($standing['reason'] ?? ''); ?>">
                    <?php echo icon('alert', '', 11); ?> Standing Revoked
                  </span>
                <?php elseif ($standing['is_good']): ?>
                  <span class="badge-pill badge-paid" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('check', '', 11); ?> Good Standing
                  </span>
                <?php else: ?>
                  <span class="badge-pill badge-pending" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('clock', '', 11); ?> Pending Settlement
                  </span>
                <?php endif; ?>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <?php if ($standing['is_revoked']): ?>
                  <form method="post" style="display: inline-block;"
                        data-confirm="Restore good standing status for <?php echo htmlspecialchars($member['name']); ?>?"
                        data-confirm-title="Restore Good Standing"
                        data-confirm-btn="Restore Standing"
                        data-confirm-class="btn-success">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="restore_good_standing">
                    <input type="hidden" name="user_id" value="<?php echo (int)$member['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-success" style="display: inline-flex; align-items: center; gap: 4px;">
                      <?php echo icon('check', '', 12); ?> <span>Restore</span>
                    </button>
                  </form>
                <?php elseif (!$standing['is_good']): ?>
                  <form method="post" style="display: inline-block;"
                        data-confirm="Grant Good Standing status to <?php echo htmlspecialchars($member['name']); ?>? This applies an administrative certification override."
                        data-confirm-title="Grant Good Standing"
                        data-confirm-btn="Grant Standing"
                        data-confirm-class="btn-primary">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="grant_good_standing">
                    <input type="hidden" name="user_id" value="<?php echo (int)$member['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 4px;" title="Grant administrative Good Standing exemption">
                      <?php echo icon('check', '', 12); ?> <span>Grant Standing</span>
                    </button>
                  </form>
                  <button type="button" class="btn btn-sm btn-secondary" style="color: #ef4444; border-color: rgba(239,68,68,0.3); display: inline-flex; align-items: center; gap: 4px;"
                          onclick="openRevokeModal(<?php echo (int)$member['id']; ?>, '<?php echo htmlspecialchars(addslashes($member['name'])); ?>', '<?php echo htmlspecialchars(addslashes($member['id_number'])); ?>')">
                    <?php echo icon('alert', '', 12); ?> <span>Revoke</span>
                  </button>
                <?php else: ?>
                  <button type="button" class="btn btn-sm btn-secondary" style="color: #ef4444; border-color: rgba(239,68,68,0.3); display: inline-flex; align-items: center; gap: 4px;"
                          onclick="openRevokeModal(<?php echo (int)$member['id']; ?>, '<?php echo htmlspecialchars(addslashes($member['name'])); ?>', '<?php echo htmlspecialchars(addslashes($member['id_number'])); ?>')">
                    <?php echo icon('alert', '', 12); ?> <span>Revoke</span>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- REVOKE GOOD STANDING MODAL -->
<div id="revokeStandingModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.78); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(8px);">
  <div style="background:var(--card-bg, #131d33); border:1px solid var(--border-color, rgba(255,255,255,0.15)); border-radius:16px; max-width:480px; width:100%; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.7); color:var(--text-primary);">
    <form method="post" style="padding:24px; margin:0;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="revoke_good_standing">
      <input type="hidden" name="user_id" id="revokeModalUserId" value="0">

      <div style="display:flex; align-items:center; gap:14px; margin-bottom:14px;">
        <div style="width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:rgba(239,68,68,0.15); color:#ef4444; flex-shrink:0;">
          <?php echo icon('alert', '', 22); ?>
        </div>
        <div>
          <h3 style="margin:0; font-size:17px; font-weight:700; color:#ef4444;">Revoke Good Standing Status</h3>
          <p class="muted" style="margin:2px 0 0; font-size:12px;" id="revokeModalSubtitle">Placing administrative hold on member standing</p>
        </div>
      </div>

      <div style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; padding: 12px; margin-bottom: 16px; font-size: 13px; line-height: 1.5; color: var(--text-secondary);">
        Revoking good standing prevents this member from voting in chapter elections, appearing on the certified public directory, and requesting official Good Standing certificates.
      </div>

      <div class="field" style="margin-bottom: 16px;">
        <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Reason for Revocation / Administrative Note <span class="muted" style="font-weight: normal; font-size: 11.5px;">(Optional)</span></label>
        <textarea name="reason" id="revokeReasonInput" rows="3" placeholder="Enter reason (e.g. Disciplinary review, Non-compliance with chapter by-laws, Documentation pending)..." style="width: 100%; font-size: 13px; line-height: 1.4;"></textarea>
        
        <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px;">
          <button type="button" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 3px 8px;" onclick="setRevokeReason('Disciplinary & Ethics Committee Review')">Ethics Review</button>
          <button type="button" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 3px 8px;" onclick="setRevokeReason('Non-compliance with Chapter By-Laws & Resolutions')">By-Law Non-compliance</button>
          <button type="button" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 3px 8px;" onclick="setRevokeReason('Administrative Hold Pending Secretariat Clearance')">Secretariat Hold</button>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top: 20px;">
        <button type="button" onclick="closeRevokeModal()" class="btn btn-sm btn-secondary" style="padding:8px 16px;">Cancel</button>
        <button type="submit" class="btn btn-sm btn-danger" style="padding:8px 18px; font-weight:700; display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('alert', '', 13); ?> <span>Confirm Revocation</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openRevokeModal(userId, name, prc) {
  document.getElementById('revokeModalUserId').value = userId;
  document.getElementById('revokeModalSubtitle').textContent = 'Placing hold on ' + name + ' (PRC: ' + prc + ')';
  document.getElementById('revokeReasonInput').value = '';
  document.getElementById('revokeStandingModal').style.display = 'flex';
}

function closeRevokeModal() {
  document.getElementById('revokeStandingModal').style.display = 'none';
}

function setRevokeReason(text) {
  document.getElementById('revokeReasonInput').value = text;
}

document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('rosterSearchInput');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('#rosterTable tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = (q === '' || text.includes(q)) ? '' : 'none';
      });
    });
  }

  const modal = document.getElementById('revokeStandingModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) closeRevokeModal();
    });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
