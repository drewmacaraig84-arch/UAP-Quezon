<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$selected_member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$editing_member_due_id = isset($_GET['edit_member_due']) ? (int)$_GET['edit_member_due'] : 0;

if ($selected_member_id > 0) {
    $member_lookup = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'member'");
    $member_lookup->execute([$selected_member_id]);
    if (!$member_lookup->fetch()) {
        $selected_member_id = 0;
    }
}

// Handle member account delete — manual cascade with transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_member') {
    require_csrf();
    $user_id = (int)$_POST['user_id'];

    try {
        $pdo->beginTransaction();

        $md_ids = $pdo->prepare("SELECT id FROM member_dues WHERE user_id = ?");
        $md_ids->execute([$user_id]);
        $md_rows = $md_ids->fetchAll(PDO::FETCH_COLUMN);

        if ($md_rows) {
            foreach ($md_rows as $md_id) {
                $pay_ids = $pdo->prepare("SELECT id FROM payments WHERE member_due_id = ?");
                $pay_ids->execute([$md_id]);
                $p_rows = $pay_ids->fetchAll(PDO::FETCH_COLUMN);
                if ($p_rows) {
                    $ph = implode(',', array_fill(0, count($p_rows), '?'));
                    $pdo->prepare("DELETE FROM receipts WHERE payment_id IN ($ph)")->execute(array_values($p_rows));
                    $pdo->prepare("DELETE FROM payments WHERE id IN ($ph)")->execute(array_values($p_rows));
                }
            }
            $phMd = implode(',', array_fill(0, count($md_rows), '?'));
            $pdo->prepare("DELETE FROM member_dues WHERE id IN ($phMd)")->execute(array_values($md_rows));
        }

        $pdo->prepare("DELETE FROM directory_applications WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM website_members WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'member'")->execute([$user_id]);
        $pdo->commit();
        if (function_exists('set_flash')) {
            set_flash('success', 'Member account and related records deleted successfully.');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('set_flash')) {
            set_flash('error', 'Failed to delete member account.');
        }
    }

    header('Location: members.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_member_due') {
    require_csrf();
    $member_due_id = (int)$_POST['member_due_id'];
    $member_id = (int)$_POST['member_id'];

    try {
        $pdo->beginTransaction();

        $pay_ids = $pdo->prepare("SELECT id FROM payments WHERE member_due_id = ?");
        $pay_ids->execute([$member_due_id]);
        $p_rows = $pay_ids->fetchAll(PDO::FETCH_COLUMN);

        if ($p_rows) {
            $ph = implode(',', array_fill(0, count($p_rows), '?'));
            $pdo->prepare("DELETE FROM receipts WHERE payment_id IN ($ph)")->execute(array_values($p_rows));
            $pdo->prepare("DELETE FROM payments WHERE id IN ($ph)")->execute(array_values($p_rows));
        }

        $pdo->prepare("DELETE FROM member_dues WHERE id = ?")->execute([$member_due_id]);
        $pdo->commit();
        if (function_exists('set_flash')) {
            set_flash('success', 'Assigned due was removed from member.');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('set_flash')) {
            set_flash('error', 'Failed to remove assigned due.');
        }
    }

    header('Location: members.php?member_id=' . $member_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_member_due') {
    require_csrf();
    $member_due_id = (int)$_POST['member_due_id'];
    $member_id = (int)$_POST['member_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? '');
    $term = trim($_POST['term'] ?? '');
    $discount_amount = (float)($_POST['discount_amount'] ?? 0);
    $other_obligations = (float)($_POST['other_obligations'] ?? 0);
    $due_period = trim($_POST['due_period'] ?? '');

    $stmt = $pdo->prepare("UPDATE member_dues SET custom_title=?, custom_description=?, custom_amount=?, custom_due_date=?, custom_term=?, discount_amount=?, other_obligations=?, due_period=? WHERE id=?");
    $stmt->execute([
        $title !== '' ? $title : null,
        $description !== '' ? $description : null,
        $amount > 0 ? $amount : null,
        $due_date !== '' ? $due_date : null,
        $term !== '' ? $term : null,
        $discount_amount,
        $other_obligations,
        $due_period !== '' ? $due_period : null,
        $member_due_id,
    ]);

    if (function_exists('set_flash')) {
        set_flash('success', "Member's assigned due was updated.");
    }

    header('Location: members.php?member_id=' . $member_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revoke_good_standing') {
    require_csrf();
    $user_id = (int)$_POST['user_id'];
    $reason = trim($_POST['reason'] ?? '');
    if ($reason === '') {
        $reason = 'Administrative hold placed by Chapter Administration.';
    }
    if (set_member_good_standing($pdo, $user_id, 'revoked', $reason)) {
        if (function_exists('set_flash')) {
            set_flash('success', 'Good standing status revoked for member.');
        }
    } else {
        if (function_exists('set_flash')) {
            set_flash('error', 'Failed to revoke good standing status.');
        }
    }
    header('Location: members.php' . ($selected_member_id ? '?member_id=' . $selected_member_id : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_good_standing') {
    require_csrf();
    $user_id = (int)$_POST['user_id'];
    if (set_member_good_standing($pdo, $user_id, 'auto', null)) {
        if (function_exists('set_flash')) {
            set_flash('success', 'Good standing status restored to automatic settlement.');
        }
    } else {
        if (function_exists('set_flash')) {
            set_flash('error', 'Failed to restore good standing status.');
        }
    }
    header('Location: members.php' . ($selected_member_id ? '?member_id=' . $selected_member_id : ''));
    exit;
}

try {
    $members = $pdo->query("
        SELECT u.id, u.name, u.id_number, u.status,
            COALESCE(u.good_standing_override, 'auto') as good_standing_override,
            u.good_standing_reason,
            u.good_standing_updated_at,
            SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN md.status = 'partial' THEN 1 ELSE 0 END) as partial_count,
            SUM(CASE WHEN md.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN md.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
            COUNT(md.id) as total_dues,
            SUM(COALESCE(md.custom_amount, d.amount)) as total_amount,
            SUM(md.total_paid) as total_paid_sum,
            SUM(COALESCE(md.custom_amount, d.amount)) - SUM(md.total_paid) as remaining_balance
        FROM users u
        LEFT JOIN member_dues md ON md.user_id = u.id
        LEFT JOIN dues d ON md.due_id = d.id
        WHERE u.role = 'member'
        GROUP BY u.id, u.name, u.id_number, u.status, u.good_standing_override, u.good_standing_reason, u.good_standing_updated_at
        ORDER BY u.name ASC
    ")->fetchAll();
} catch (Throwable $e) {
    $members = $pdo->query("
        SELECT u.id, u.name, u.id_number, u.status,
            'auto' as good_standing_override,
            NULL as good_standing_reason,
            NULL as good_standing_updated_at,
            SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN md.status = 'partial' THEN 1 ELSE 0 END) as partial_count,
            SUM(CASE WHEN md.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN md.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
            COUNT(md.id) as total_dues,
            SUM(COALESCE(md.custom_amount, d.amount)) as total_amount,
            SUM(md.total_paid) as total_paid_sum,
            SUM(COALESCE(md.custom_amount, d.amount)) - SUM(md.total_paid) as remaining_balance
        FROM users u
        LEFT JOIN member_dues md ON md.user_id = u.id
        LEFT JOIN dues d ON md.due_id = d.id
        WHERE u.role = 'member'
        GROUP BY u.id, u.name, u.id_number, u.status
        ORDER BY u.name ASC
    ")->fetchAll();
}

$selected_member = null;
$selected_member_dues = [];
$editing_member_due = null;

if ($selected_member_id) {
    $member_stmt = $pdo->prepare("SELECT id, name, id_number, status FROM users WHERE id = ? AND role = 'member'");
    $member_stmt->execute([$selected_member_id]);
    $selected_member = $member_stmt->fetch();

    if ($selected_member) {
        $dues_stmt = $pdo->prepare("
            SELECT md.id, md.user_id, md.status, md.total_paid, md.payment_type, md.installment_months,
                   md.discount_amount, md.other_obligations, md.due_period,
                   d.id as due_id, d.title as base_title, d.description as base_description, d.amount as base_amount, d.due_date as base_due_date, d.term as base_term,
                   md.custom_title, md.custom_description, md.custom_amount, md.custom_due_date, md.custom_term,
                   COALESCE(md.custom_title, d.title) AS title,
                   COALESCE(md.custom_description, d.description) AS description,
                   COALESCE(md.custom_amount, d.amount) AS amount,
                   COALESCE(md.custom_due_date, d.due_date) AS due_date,
                   COALESCE(md.custom_term, d.term) AS term
            FROM member_dues md
            JOIN dues d ON md.due_id = d.id
            WHERE md.user_id = ?
            ORDER BY COALESCE(md.custom_due_date, d.due_date) IS NULL, COALESCE(md.custom_due_date, d.due_date) ASC, d.title ASC
        ");
        $dues_stmt->execute([$selected_member_id]);
        $selected_member_dues = $dues_stmt->fetchAll();

        if ($editing_member_due_id) {
            foreach ($selected_member_dues as $due) {
                if ((int)$due['id'] === $editing_member_due_id) {
                    $editing_member_due = $due;
                    break;
                }
            }
        }
    }
}

$page_title = 'Chapter Members • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">CHAPTER ROSTER</p>
    <h1>Chapter Members Directory</h1>
    <p class="page-subtitle">Manage member profiles, inspect individual due balances, and adjust member-specific fees.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('members', '', 14); ?> <span><?php echo count($members); ?> Total Members</span>
  </div>
</div>

<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div style="display: flex; gap: 12px; flex-wrap: wrap; flex-grow: 1; max-width: 600px;">
      <div style="flex: 1; min-width: 220px; position: relative;">
        <input type="text" id="memberSearch" placeholder="Search by name or PRC ID No..." oninput="filterTable()" style="padding-left: 36px;">
        <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);">
          <?php echo icon('search', '', 16); ?>
        </div>
      </div>
      <div style="width: 200px;">
        <select id="statusFilter" onchange="filterTable()">
          <option value="all">All Members</option>
          <option value="good-standing">Good Standing</option>
          <option value="standing-revoked">Standing Revoked / Hold</option>
          <option value="fully-paid">Fully Paid</option>
          <option value="partially-paid">Partially Paid</option>
          <option value="pending-verification">Pending Verification</option>
          <option value="has-dues">Has Outstanding Dues</option>
          <option value="awaiting-approval">Awaiting Approval</option>
        </select>
      </div>
    </div>
  </div>

  <div class="table-shell">
    <table id="membersTable">
      <thead>
        <tr>
          <th>Architect Name</th>
          <th>PRC ID No.</th>
          <th>Dues Progress</th>
          <th>Total Expected</th>
          <th>Total Paid</th>
          <th>Remaining Balance</th>
          <th>Status</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($members as $m):
        $standing = get_member_standing_details($pdo, $m['id']);
        if ($m['status'] === 'pending') {
            $row_status = 'awaiting-approval';
        } elseif ($standing['is_revoked']) {
            $row_status = 'standing-revoked';
        } elseif ($m['total_dues'] > 0 && $m['paid_count'] == $m['total_dues']) {
            $row_status = 'fully-paid';
        } elseif ($m['partial_count'] > 0) {
            $row_status = 'partially-paid';
        } elseif ($m['pending_count'] > 0) {
            $row_status = 'pending-verification';
        } elseif ($m['unpaid_count'] > 0) {
            $row_status = 'has-dues';
        } else {
            $row_status = 'fully-paid';
        }
        $search_text = strtolower($m['name'] . ' ' . $m['id_number']);
      ?>
      <tr data-status="<?php echo $row_status; ?>" data-good="<?php echo $standing['is_good'] ? '1' : '0'; ?>" data-revoked="<?php echo $standing['is_revoked'] ? '1' : '0'; ?>" data-search="<?php echo htmlspecialchars($search_text); ?>">
        <td>
          <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($m['name']); ?></strong>
          <?php if ($m['status'] === 'pending'): ?>
            <span class="badge-pill badge-pending" style="font-size: 10px;">Pending</span>
          <?php elseif ($standing['is_revoked']): ?>
            <span class="badge-pill badge-unpaid" style="font-size: 10px;" title="<?php echo htmlspecialchars($standing['reason'] ?? 'Standing Revoked'); ?>">
              <?php echo icon('alert', '', 10); ?> Standing Revoked
            </span>
          <?php elseif ($standing['is_good']): ?>
            <span class="badge-pill badge-paid" style="font-size: 10px;"><?php echo icon('good_members', '', 10); ?> Good Standing</span>
          <?php endif; ?>
          <?php
            $mAtt = get_or_create_member_attendance($pdo, $m['id']);
            $ac = $mAtt['computed'];
          ?>
          <a href="attendance.php?edit_member_id=<?php echo $m['id']; ?>" style="text-decoration:none;">
            <span class="badge" style="background: <?php echo $ac['badge_bg']; ?>; color: <?php echo $ac['badge_color']; ?>; border: 1px solid <?php echo $ac['badge_border']; ?>; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px;">
              <?php echo $ac['badge_label']; ?> (<?php echo $ac['total_attended']; ?>)
            </span>
          </a>
        </td>
        <td><code><?php echo htmlspecialchars($m['id_number']); ?></code></td>
        <td>
          <span style="font-size: 13px; font-weight: 600;"><?php echo $m['paid_count']; ?>/<?php echo $m['total_dues']; ?></span>
          <span class="muted" style="font-size: 11px;"> cleared</span>
        </td>
        <td>₱<?php echo number_format($m['total_amount'] ?? 0, 2); ?></td>
        <td><strong style="color:#10b981;">₱<?php echo number_format($m['total_paid_sum'] ?? 0, 2); ?></strong></td>
        <td>
          <strong style="color:<?php echo ($m['remaining_balance'] ?? 0) > 0 ? '#ef4444' : '#10b981'; ?>;">
            ₱<?php echo number_format(max(0, $m['remaining_balance'] ?? 0), 2); ?>
          </strong>
        </td>
        <td>
          <?php if ($m['total_dues'] > 0 && $m['paid_count'] == $m['total_dues']): ?>
            <span class="badge-pill badge-paid">Fully Paid</span>
          <?php elseif ($m['partial_count'] > 0): ?>
            <span class="badge-pill badge-pending">Partially Paid</span>
          <?php elseif ($m['pending_count'] > 0): ?>
            <span class="badge-pill badge-pending">Pending Verification</span>
          <?php elseif ($m['unpaid_count'] > 0): ?>
            <span class="badge-pill badge-unpaid">Outstanding Dues</span>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </td>
        <td style="white-space: nowrap; text-align: right;">
          <?php if ($standing['is_revoked']): ?>
            <form method="post" style="display:inline-block;"
                  data-confirm="Restore good standing for <?php echo htmlspecialchars($m['name']); ?>? Dues compliance will be checked automatically."
                  data-confirm-title="Restore Good Standing"
                  data-confirm-btn="Restore Standing"
                  data-confirm-class="btn-success">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
              <input type="hidden" name="action" value="restore_good_standing">
              <button class="btn btn-sm btn-success" type="submit" style="display:inline-flex;align-items:center;gap:4px;margin-right:4px;" title="Restore Good Standing">
                <?php echo icon('check', '', 12); ?> <span>Restore</span>
              </button>
            </form>
          <?php elseif ($standing['is_good']): ?>
            <button type="button" class="btn btn-sm btn-secondary" style="color:#ef4444;border-color:rgba(239,68,68,0.3);display:inline-flex;align-items:center;gap:4px;margin-right:4px;" title="Revoke Good Standing"
                    onclick="openMemberRevokeModal(<?php echo (int)$m['id']; ?>, '<?php echo htmlspecialchars(addslashes($m['name'])); ?>', '<?php echo htmlspecialchars(addslashes($m['id_number'])); ?>')">
              <?php echo icon('alert', '', 12); ?> <span>Revoke</span>
            </button>
          <?php endif; ?>
          <a class="btn btn-sm btn-secondary" href="members.php?member_id=<?php echo $m['id']; ?>#member-dues-panel" style="display:inline-flex;align-items:center;gap:4px;margin-right:4px;">
            <?php echo icon('dues', '', 12); ?> <span>Dues</span>
          </a>
          <a class="btn btn-sm btn-secondary" href="attendance.php?edit_member_id=<?php echo $m['id']; ?>#edit-attendance-panel" style="display:inline-flex;align-items:center;gap:4px;margin-right:4px;">
            <?php echo icon('check', '', 12); ?> <span>Attendance</span>
          </a>
          <a class="btn btn-sm btn-secondary" href="account_manager.php?search=<?php echo urlencode($m['id_number']); ?>" style="display:inline-flex;align-items:center;gap:4px;margin-right:4px;">
            <?php echo icon('edit', '', 12); ?> <span>Edit</span>
          </a>
          <form method="post" class="inline" style="display:inline-block;"
                data-confirm="Delete member <?php echo htmlspecialchars($m['name']); ?>? This action cannot be undone."
                data-confirm-title="Delete Member"
                data-confirm-btn="Delete Member"
                data-confirm-class="btn-danger">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
            <input type="hidden" name="action" value="delete_member">
            <button class="btn btn-sm btn-danger" type="submit" style="display:inline-flex;align-items:center;gap:4px;">
              <?php echo icon('trash', '', 12); ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($selected_member): ?>
<div class="card" id="member-dues-panel" style="margin-top:24px;">
  <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
    <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
      <?php echo icon('dues', '', 18); ?>
    </div>
    <div>
      <h2 style="font-size: 17px; margin: 0;">Assigned Dues for <?php echo htmlspecialchars($selected_member['name']); ?> (<?php echo htmlspecialchars($selected_member['id_number']); ?>)</h2>
      <p class="muted" style="font-size: 12px; margin: 2px 0 0;">Editing or removing dues here affects only this specific member.</p>
    </div>
  </div>

  <?php if ($editing_member_due): ?>
  <form method="post" style="margin-bottom:20px; background: var(--bg-secondary); padding: 18px; border-radius: 12px; border: 1px solid var(--border-color);">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="update_member_due">
    <input type="hidden" name="member_due_id" value="<?php echo $editing_member_due['id']; ?>">
    <input type="hidden" name="member_id" value="<?php echo $selected_member['id']; ?>">
    <div class="grid-2" style="gap: 14px;">
      <div class="field"><label>Due Title</label><input name="title" required value="<?php echo htmlspecialchars($editing_member_due['title'] ?? ''); ?>"></div>
      <div class="field"><label>Due Period (e.g. FY 2026 - 2027)</label><input name="due_period" placeholder="e.g. FY 2026 - 2027" value="<?php echo htmlspecialchars($editing_member_due['due_period'] ?? ''); ?>"></div>
      <div class="field"><label>Base Due Amount (₱)</label><input type="number" step="0.01" name="amount" required value="<?php echo htmlspecialchars($editing_member_due['amount'] ?? ''); ?>"></div>
      <div class="field"><label>Discount Amount (₱)</label><input type="number" step="0.01" name="discount_amount" value="<?php echo htmlspecialchars($editing_member_due['discount_amount'] ?? '0.00'); ?>"></div>
      <div class="field"><label>Other Obligations (₱)</label><input type="number" step="0.01" name="other_obligations" value="<?php echo htmlspecialchars($editing_member_due['other_obligations'] ?? '0.00'); ?>"></div>
      <div class="field"><label>Due Date</label><input type="date" name="due_date" value="<?php echo htmlspecialchars($editing_member_due['due_date'] ?? ''); ?>"></div>
      <div class="field"><label>Term</label><input name="term" value="<?php echo htmlspecialchars($editing_member_due['term'] ?? ''); ?>"></div>
      <div class="field"><label>Description</label><input name="description" value="<?php echo htmlspecialchars($editing_member_due['description'] ?? ''); ?>"></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
      <button class="btn btn-sm" type="submit" style="display:inline-flex;align-items:center;gap:4px;">
        <?php echo icon('check', '', 13); ?> <span>Save Changes</span>
      </button>
      <a class="btn btn-sm btn-secondary" href="members.php?member_id=<?php echo $selected_member['id']; ?>">Cancel</a>
    </div>
  </form>
  <?php endif; ?>

  <?php if (empty($selected_member_dues)): ?>
    <p class="muted" style="text-align: center; padding: 24px;">No dues have been assigned to this member yet.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Due Package</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Due Date</th>
            <th>Term</th>
            <th>Payment Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($selected_member_dues as $due): ?>
        <tr>
          <td><strong style="color: var(--text-primary);"><?php echo htmlspecialchars($due['title']); ?></strong></td>
          <td><span class="muted"><?php echo htmlspecialchars($due['description'] ?? '—'); ?></span></td>
          <td><strong style="color: #10b981;">₱<?php echo number_format($due['amount'], 2); ?></strong></td>
          <td><?php echo $due['due_date'] ? date('M d, Y', strtotime($due['due_date'])) : '<span class="muted">—</span>'; ?></td>
          <td><span class="badge-pill badge-partial"><?php echo htmlspecialchars($due['term'] ?: 'Standard'); ?></span></td>
          <td>
            <?php
              $badge = $due['status'];
              $label = match($due['status']) {
                'unpaid' => 'Unpaid',
                'pending' => 'Pending Verification',
                'partial' => 'Partially Paid',
                'paid' => 'Fully Paid',
                'rejected' => 'Rejected',
                default => ucfirst($due['status'])
              };
            ?>
            <span class="badge-pill badge-<?php echo $badge === 'partial' ? 'pending' : ($badge === 'rejected' ? 'unpaid' : $badge); ?>"><?php echo $label; ?></span>
          </td>
          <td style="white-space: nowrap; text-align: right;">
            <a class="btn btn-sm btn-secondary" href="members.php?member_id=<?php echo $selected_member['id']; ?>&edit_member_due=<?php echo $due['id']; ?>#member-dues-panel" style="display:inline-flex;align-items:center;gap:4px;margin-right:4px;">
              <?php echo icon('edit', '', 12); ?> <span>Edit</span>
            </a>
            <form method="post" class="inline" style="display:inline-block;"
                  data-confirm="Remove this due from <?php echo htmlspecialchars($selected_member['name']); ?>?"
                  data-confirm-title="Remove Assigned Due"
                  data-confirm-btn="Remove"
                  data-confirm-class="btn-danger">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="delete_member_due">
              <input type="hidden" name="member_due_id" value="<?php echo $due['id']; ?>">
              <input type="hidden" name="member_id" value="<?php echo $selected_member['id']; ?>">
              <button class="btn btn-sm btn-danger" type="submit" style="display:inline-flex;align-items:center;gap:4px;">
                <?php echo icon('trash', '', 12); ?>
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
<?php endif; ?>

<!-- REVOKE GOOD STANDING MODAL -->
<div id="memberRevokeModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.78); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(8px);">
  <div style="background:var(--card-bg, #131d33); border:1px solid var(--border-color, rgba(255,255,255,0.15)); border-radius:16px; max-width:480px; width:100%; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.7); color:var(--text-primary);">
    <form method="post" style="padding:24px; margin:0;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="revoke_good_standing">
      <input type="hidden" name="user_id" id="modalMemberId" value="0">

      <div style="display:flex; align-items:center; gap:14px; margin-bottom:14px;">
        <div style="width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:rgba(239,68,68,0.15); color:#ef4444; flex-shrink:0;">
          <?php echo icon('alert', '', 22); ?>
        </div>
        <div>
          <h3 style="margin:0; font-size:17px; font-weight:700; color:#ef4444;">Revoke Good Standing Status</h3>
          <p class="muted" style="margin:2px 0 0; font-size:12px;" id="modalMemberSubtitle">Placing administrative hold on member standing</p>
        </div>
      </div>

      <div style="background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; padding: 12px; margin-bottom: 16px; font-size: 13px; line-height: 1.5; color: var(--text-secondary);">
        Revoking good standing removes this member's certification across the chapter portal, voting eligibility, and directory standing badge.
      </div>

      <div class="field" style="margin-bottom: 16px;">
        <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Reason for Revocation / Administrative Note <span class="muted" style="font-weight: normal; font-size: 11.5px;">(Optional)</span></label>
        <textarea name="reason" id="memberRevokeReasonInput" rows="3" placeholder="Enter reason (e.g. Disciplinary review, By-law non-compliance, Administrative hold)..." style="width: 100%; font-size: 13px; line-height: 1.4;"></textarea>
        
        <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px;">
          <button type="button" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 3px 8px;" onclick="setMemberRevokeReason('Disciplinary & Ethics Committee Review')">Ethics Review</button>
          <button type="button" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 3px 8px;" onclick="setMemberRevokeReason('Non-compliance with Chapter By-Laws & Resolutions')">By-Law Non-compliance</button>
          <button type="button" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 3px 8px;" onclick="setMemberRevokeReason('Administrative Hold Pending Secretariat Clearance')">Secretariat Hold</button>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top: 20px;">
        <button type="button" onclick="closeMemberRevokeModal()" class="btn btn-sm btn-secondary" style="padding:8px 16px;">Cancel</button>
        <button type="submit" class="btn btn-sm btn-danger" style="padding:8px 18px; font-weight:700; display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('alert', '', 13); ?> <span>Confirm Revocation</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openMemberRevokeModal(userId, name, prc) {
  document.getElementById('modalMemberId').value = userId;
  document.getElementById('modalMemberSubtitle').textContent = 'Placing hold on ' + name + (prc ? ' (PRC: ' + prc + ')' : '');
  document.getElementById('memberRevokeReasonInput').value = '';
  document.getElementById('memberRevokeModal').style.display = 'flex';
}

function closeMemberRevokeModal() {
  document.getElementById('memberRevokeModal').style.display = 'none';
}

function setMemberRevokeReason(text) {
  document.getElementById('memberRevokeReasonInput').value = text;
}

function filterTable() {
  const search = document.getElementById('memberSearch').value.toLowerCase();
  const status = document.getElementById('statusFilter').value;
  const rows = document.querySelectorAll('#membersTable tbody tr');
  let visible = 0;

  rows.forEach(row => {
    const matchSearch = !search || (row.dataset.search && row.dataset.search.includes(search));
    let matchStatus = true;
    if (status === 'good-standing') {
      matchStatus = row.dataset.good === '1';
    } else if (status === 'standing-revoked') {
      matchStatus = row.dataset.revoked === '1';
    } else if (status !== 'all') {
      matchStatus = row.dataset.status === status;
    }
    row.style.display = (matchSearch && matchStatus) ? '' : 'none';
    if (matchSearch && matchStatus) visible++;
  });
}

document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('memberRevokeModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) closeMemberRevokeModal();
    });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>