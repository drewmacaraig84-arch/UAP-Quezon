<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dues_service.php';
require_admin();

// Defensive column ensure: deleted_at and deleted_by on dues table
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM dues LIKE 'deleted_at'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE dues ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER term");
    }
    $colCheck2 = $pdo->query("SHOW COLUMNS FROM dues LIKE 'deleted_by'")->fetch();
    if (!$colCheck2) {
        $pdo->exec("ALTER TABLE dues ADD COLUMN deleted_by INT NULL DEFAULT NULL AFTER deleted_at");
    }
} catch (Throwable $e) {}

// Auto-purge soft-deleted dues that exceeded 7 days retention
$purgedCount = purge_expired_soft_deleted_dues($pdo);
if ($purgedCount > 0 && function_exists('set_flash')) {
    set_flash('info', "{$purgedCount} soft-deleted due package(s) reached the 7-day retention period and were permanently purged.");
}

// Handle create new due
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    require_csrf();
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $amount = (float)$_POST['amount'];
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $term = trim($_POST['term']);
    $assign_type = $_POST['assign_type'] ?? 'all';

    if ($amount < 0) {
        if (function_exists('set_flash')) {
            set_flash('error', 'Due amount cannot be negative.');
        }
        header('Location: dues.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO dues (title, description, amount, due_date, term) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $amount, $due_date, $term]);
        $due_id = $pdo->lastInsertId();

        if ($assign_type === 'all') {
            $members = $pdo->query("SELECT id FROM users WHERE role = 'member' AND status = 'approved'")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $members = array_map('intval', $_POST['specific_members'] ?? []);
        }

        if (!empty($members)) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO member_dues (user_id, due_id, status) VALUES (?, ?, 'unpaid')");
            foreach ($members as $user_id) {
                if ($user_id > 0) {
                    $stmt->execute([$user_id, $due_id]);
                }
            }
        }

        $pdo->commit();
        if (function_exists('set_flash')) {
            set_flash('success', 'Due package created and assigned to ' . count($members) . ' member(s).');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('set_flash')) {
            set_flash('error', 'Failed to create due package: ' . $e->getMessage());
        }
    }

    header('Location: dues.php');
    exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    require_csrf();
    $due_id = (int)$_POST['due_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $amount = (float)$_POST['amount'];
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $term = trim($_POST['term']);

    $stmt = $pdo->prepare("UPDATE dues SET title=?, description=?, amount=?, due_date=?, term=? WHERE id=?");
    $stmt->execute([$title, $description, $amount, $due_date, $term, $due_id]);
    
    if (function_exists('set_flash')) {
        set_flash('success', 'Due package updated successfully.');
    }
    header('Location: dues.php');
    exit;
}

// Handle soft delete (default delete action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['delete', 'soft_delete'], true)) {
    require_csrf();
    $due_id = (int)$_POST['due_id'];

    if (soft_delete_due_package($pdo, $due_id, current_user_id())) {
        if (function_exists('set_flash')) {
            set_flash('success', 'Due package moved to trash (soft-deleted). It will remain active for assigned members for 7 days before permanent deletion, unless restored or overridden with admin password.');
        }
    } else {
        if (function_exists('set_flash')) {
            set_flash('error', 'Failed to move due package to trash.');
        }
    }

    header('Location: dues.php');
    exit;
}

// Handle restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    require_csrf();
    $due_id = (int)$_POST['due_id'];

    if (restore_due_package($pdo, $due_id)) {
        if (function_exists('set_flash')) {
            set_flash('success', 'Due package restored to active packages successfully.');
        }
    } else {
        if (function_exists('set_flash')) {
            set_flash('error', 'Failed to restore due package.');
        }
    }

    header('Location: dues.php?view=active');
    exit;
}

// Handle permanent delete (admin password verification override)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'permanent_delete') {
    require_csrf();
    $due_id = (int)($_POST['due_id'] ?? 0);
    $adminPassword = (string)($_POST['admin_password'] ?? '');
    $fromTrash = !empty($_POST['from_trash']);

    // Verify current logged-in admin's password
    $adminId = current_user_id();
    $adminStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $adminStmt->execute([$adminId]);
    $adminHash = $adminStmt->fetchColumn();

    if (!$adminHash || !password_verify($adminPassword, $adminHash)) {
        if (function_exists('set_flash')) {
            set_flash('error', 'Authentication failed: Incorrect admin password. Permanent deletion was blocked.');
        }
    } else {
        if (purge_due_package($pdo, $due_id)) {
            if (function_exists('set_flash')) {
                set_flash('success', 'Due package permanently deleted and cascaded from all assigned members.');
            }
        } else {
            if (function_exists('set_flash')) {
                set_flash('error', 'Failed to permanently delete due package.');
            }
        }
    }

    header('Location: dues.php' . ($fromTrash ? '?view=trash' : ''));
    exit;
}

// Handle assign past due to specific member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    require_csrf();
    $due_id = (int)$_POST['due_id'];
    $user_id = (int)$_POST['user_id'];
    $pdo->prepare("INSERT IGNORE INTO member_dues (user_id, due_id, status) VALUES (?, ?, 'unpaid')")->execute([$user_id, $due_id]);
    
    if (function_exists('set_flash')) {
        set_flash('success', 'Due item assigned to member.');
    }
    header('Location: dues.php');
    exit;
}

// Load due for editing
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM dues WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

// Active and soft-deleted dues
$activeDues = $pdo->query("SELECT * FROM dues WHERE deleted_at IS NULL ORDER BY created_at DESC")->fetchAll();
$trashDues = $pdo->query("
    SELECT d.*, u.name as deleted_by_name 
    FROM dues d 
    LEFT JOIN users u ON d.deleted_by = u.id 
    WHERE d.deleted_at IS NOT NULL 
    ORDER BY d.deleted_at DESC
")->fetchAll();

$dues = $activeDues;
$all_members = $pdo->query("SELECT id, name, id_number FROM users WHERE role = 'member' AND status = 'approved' ORDER BY name ASC")->fetchAll();
$activeView = ($_GET['view'] ?? 'active') === 'trash' ? 'trash' : 'active';

$page_title = 'Dues Management • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>

<style>
.member-search-box { 
  border: 1px solid var(--border-color); 
  border-radius: 10px; 
  padding: 14px; 
  background: var(--bg-secondary); 
  color: var(--text-primary);
  margin-bottom: 14px;
}
.member-results {
  max-height: 220px;
  overflow-y: auto;
  margin-top: 10px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--field-bg);
}
.member-row {
  display: grid;
  grid-template-columns: 32px 1fr;
  align-items: center;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border-color);
  cursor: pointer;
  transition: background 0.15s ease;
  color: var(--text-primary);
}
.member-row:last-child {
  border-bottom: none;
}
.member-row:hover {
  background: var(--hover-row-bg);
}
.member-row input[type="checkbox"] {
  justify-self: center;
  cursor: pointer;
  accent-color: var(--accent-primary);
}
.selected-members { 
  margin-top: 10px; 
}
.selected-tag { 
  display: inline-flex; 
  align-items: center;
  gap: 4px;
  background: var(--accent-primary); 
  color: #0f172a; 
  font-weight: 700;
  border-radius: 999px; 
  padding: 3px 10px; 
  font-size: 11.5px; 
  margin: 3px; 
}
.btn-toggle-view {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  background: transparent;
  border: 1px solid transparent;
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}
.btn-toggle-view:hover {
  color: var(--text-primary);
  background: rgba(255, 255, 255, 0.05);
}
.btn-toggle-view.active {
  background: var(--button-primary, #f59e0b);
  color: #0f172a;
  border-color: transparent;
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25);
}
.badge-count-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 1px 7px;
  border-radius: 999px;
  background: rgba(239, 68, 68, 0.2);
  color: #ef4444;
  font-size: 11px;
  font-weight: 700;
  margin-left: 4px;
}
.btn-toggle-view.active .badge-count-pill {
  background: #0f172a;
  color: #f59e0b;
}
</style>

<div class="page-hero">
  <div>
    <p class="eyebrow">ORGANIZATION DUES</p>
    <h1>Dues Management &amp; Assignment</h1>
    <p class="page-subtitle">Create chapter dues packages, assign fees to members, and configure payment options.</p>
  </div>
  <div class="hero-badge" style="display: flex; gap: 8px; flex-wrap: wrap;">
    <span style="display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('dues', '', 14); ?> <span><?php echo count($activeDues); ?> Active Packages</span>
    </span>
    <?php if (!empty($trashDues)): ?>
      <button type="button" onclick="switchDuesView('trash')" style="background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); color: #f59e0b; border-radius: 999px; padding: 2px 10px; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
        <?php echo icon('trash', '', 12); ?> <span><?php echo count($trashDues); ?> in Trash (7d grace)</span>
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2" style="margin-bottom: 24px;">
  <!-- Create / Edit form -->
  <div class="card" style="margin: 0;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
        <?php echo icon($editing ? 'edit' : 'plus', '', 18); ?>
      </div>
      <h2 style="font-size: 17px; margin: 0;"><?php echo $editing ? 'Edit Due Package' : 'Create New Due Package'; ?></h2>
    </div>

    <?php if ($editing): ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="due_id" value="<?php echo $editing['id']; ?>">
        <div class="field"><label>Due Title</label><input name="title" required value="<?php echo htmlspecialchars($editing['title']); ?>"></div>
        <div class="field"><label>Description</label><input name="description" value="<?php echo htmlspecialchars($editing['description']); ?>"></div>
        <div class="grid-2" style="gap: 12px; margin-bottom: 0;">
          <div class="field"><label>Amount (₱)</label><input type="number" step="0.01" name="amount" required value="<?php echo $editing['amount']; ?>"></div>
          <div class="field"><label>Due Date</label><input type="date" name="due_date" value="<?php echo htmlspecialchars($editing['due_date'] ?? ''); ?>"></div>
        </div>
        <div class="field"><label>Term / Period</label><input name="term" value="<?php echo htmlspecialchars($editing['term']); ?>" placeholder="e.g. FY 2026-2027"></div>
        <div style="display: flex; gap: 8px; margin-top: 18px;">
          <button class="btn btn-sm" type="submit" style="flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <?php echo icon('check', '', 14); ?> <span>Save Changes</span>
          </button>
          <a href="dues.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; justify-content: center;">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <form method="post" id="createForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="field"><label>Due Title</label><input name="title" required placeholder="e.g. Annual Chapter Membership Fee"></div>
        <div class="field"><label>Description</label><input name="description" placeholder="Brief description of the due item"></div>
        <div class="grid-2" style="gap: 12px; margin-bottom: 0;">
          <div class="field"><label>Amount (₱)</label><input type="number" step="0.01" name="amount" required placeholder="500.00"></div>
          <div class="field"><label>Due Date</label><input type="date" name="due_date"></div>
        </div>
        <div class="field"><label>Term / Period</label><input name="term" placeholder="e.g. FY 2026-2027"></div>

        <div class="field">
          <label>Assign To</label>
          <select name="assign_type" id="assignType" onchange="toggleMemberSearch()">
            <option value="all">All Approved Chapter Members</option>
            <option value="specific">Specific Members Only</option>
          </select>
        </div>

        <div id="memberSearchBox" class="member-search-box" style="display:none;">
          <label style="font-weight:700;font-size:12.5px;">Select Target Members</label>
          <input type="text" id="memberSearchInput" placeholder="Type name or PRC ID..." oninput="filterMembers()" style="margin-top:6px;">
          <div class="member-results" id="memberResults">
            <?php foreach ($all_members as $m): ?>
              <label class="member-row">
                <input type="checkbox" name="specific_members[]" value="<?php echo $m['id']; ?>"
                       onchange="updateSelected(this, '<?php echo htmlspecialchars(addslashes($m['name'])); ?>', '<?php echo htmlspecialchars($m['id_number']); ?>')">
                <div>
                  <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($m['name']); ?></div>
                  <div class="muted" style="font-size:11.5px;"><?php echo htmlspecialchars($m['id_number']); ?></div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="selected-members">
            <span class="muted" style="font-size:12px;display:block;margin-bottom:4px;">Selected members:</span>
            <div id="selectedTags"><span class="muted" style="font-size:12px;">None selected yet.</span></div>
          </div>
        </div>

        <button class="btn btn-sm" type="submit" style="width:100%;margin-top:14px;display:inline-flex;align-items:center;justify-content:center;gap:6px;">
          <?php echo icon('plus', '', 14); ?> <span>Create Due Package</span>
        </button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Assign past due to specific member -->
  <div class="card" style="margin: 0;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59,130,246,0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('user', '', 18); ?>
      </div>
      <div>
        <h2 style="font-size: 17px; margin: 0;">Assign Due to Individual Member</h2>
        <p class="muted" style="font-size: 12px; margin: 2px 0 0;">Add an existing active due item to a specific member.</p>
      </div>
    </div>

    <?php if (empty($activeDues) || empty($all_members)): ?>
      <p class="muted" style="text-align:center;padding:24px;">Need at least one configured active due and one approved member.</p>
    <?php else: ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="assign">
        <div class="field">
          <label>Select Due Item</label>
          <select name="due_id" required>
            <option value="">Choose an active due...</option>
            <?php foreach ($activeDues as $d): ?>
              <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['title']); ?> — ₱<?php echo number_format($d['amount'], 2); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Select Member</label>
          <select name="user_id" required>
            <option value="">Choose a member...</option>
            <?php foreach ($all_members as $m): ?>
              <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?> (<?php echo htmlspecialchars($m['id_number']); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-sm btn-secondary" type="submit" style="width:100%;margin-top:14px;display:inline-flex;align-items:center;justify-content:center;gap:6px;">
          <?php echo icon('check', '', 14); ?> <span>Assign Due to Member</span>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Configured Dues Packages Card with Active and Trash Tabs -->
<div class="card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h2 style="font-size: 18px; margin: 0; font-weight: 700;">Configured Dues Packages</h2>
      <p class="muted" style="margin: 3px 0 0; font-size: 12.5px;">Manage active dues packages and recoverable trash items.</p>
    </div>

    <!-- Tab View Switcher -->
    <div style="display: flex; background: var(--bg-secondary, rgba(255,255,255,0.05)); border: 1px solid var(--border-color); border-radius: 10px; padding: 4px; gap: 4px;">
      <button type="button" id="btnActiveView" onclick="switchDuesView('active')" class="btn-toggle-view <?php echo $activeView === 'active' ? 'active' : ''; ?>">
        <?php echo icon('dues', '', 14); ?>
        <span>Active Packages (<?php echo count($activeDues); ?>)</span>
      </button>
      <button type="button" id="btnTrashView" onclick="switchDuesView('trash')" class="btn-toggle-view <?php echo $activeView === 'trash' ? 'active' : ''; ?>">
        <?php echo icon('trash', '', 14); ?>
        <span>Trash Bin (<?php echo count($trashDues); ?>)</span>
        <?php if (count($trashDues) > 0): ?>
          <span class="badge-count-pill"><?php echo count($trashDues); ?></span>
        <?php endif; ?>
      </button>
    </div>
  </div>

  <!-- VIEW 1: ACTIVE DUES PACKAGES -->
  <div id="activeDuesView" style="display: <?php echo $activeView === 'active' ? 'block' : 'none'; ?>;">
    <?php if (empty($activeDues)): ?>
      <div style="text-align: center; padding: 40px 16px; color: var(--text-secondary);">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(245,158,11,0.1); color: var(--accent-primary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
          <?php echo icon('dues', '', 24); ?>
        </div>
        <strong style="display: block; font-size: 15px; color: var(--text-primary);">No active dues packages</strong>
        <p class="muted" style="margin-top: 4px; font-size: 13px;">Create your first chapter due package using the form above.</p>
      </div>
    <?php else: ?>
      <div class="table-shell">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Due Date</th>
              <th>Term</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($activeDues as $d): ?>
              <tr>
                <td><strong style="color: var(--text-primary);"><?php echo htmlspecialchars($d['title']); ?></strong></td>
                <td><span class="muted"><?php echo htmlspecialchars($d['description'] ?? '—'); ?></span></td>
                <td><strong style="color: #10b981;">₱<?php echo number_format($d['amount'], 2); ?></strong></td>
                <td><?php echo $d['due_date'] ? date('M d, Y', strtotime($d['due_date'])) : '<span class="muted">—</span>'; ?></td>
                <td><span class="badge-pill badge-partial"><?php echo htmlspecialchars($d['term'] ?: 'Standard'); ?></span></td>
                <td style="white-space: nowrap; text-align: right;">
                  <a class="btn btn-sm btn-secondary" href="dues.php?edit=<?php echo $d['id']; ?>" style="display:inline-flex;align-items:center;gap:4px;margin-right:4px;">
                    <?php echo icon('edit', '', 12); ?> <span>Edit</span>
                  </a>
                  
                  <!-- Soft Delete Button (Default Delete) -->
                  <form method="post" class="inline" style="display:inline-block;"
                        data-confirm="Move '<?php echo htmlspecialchars($d['title']); ?>' to Trash? It will remain intact for assigned members for 7 days before permanent deletion unless restored or overridden with admin password."
                        data-confirm-title="Move to Trash (Soft Delete)"
                        data-confirm-btn="Move to Trash"
                        data-confirm-class="btn-danger">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="soft_delete">
                    <input type="hidden" name="due_id" value="<?php echo $d['id']; ?>">
                    <button class="btn btn-sm btn-danger" type="submit" style="display:inline-flex;align-items:center;gap:4px;" title="Soft delete (7-day recovery period)">
                      <?php echo icon('trash', '', 12); ?> <span>Delete</span>
                    </button>
                  </form>

                  <!-- Immediate Permanent Deletion Override with Password -->
                  <button type="button" class="btn btn-sm" onclick="openPermanentDeleteModal(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['title'])); ?>', false)"
                          title="Override 7-day wait: Permanently delete now using admin password"
                          style="display:inline-flex;align-items:center;gap:4px;background:rgba(239,68,68,0.1);color:#f87171;border:1px solid rgba(239,68,68,0.25);font-size:11.5px;padding:5px 9px;margin-left:4px;">
                    <?php echo icon('lock', '', 11); ?> <span>Force Delete</span>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- VIEW 2: TRASH BIN / SOFT-DELETED PACKAGES -->
  <div id="trashDuesView" style="display: <?php echo $activeView === 'trash' ? 'block' : 'none'; ?>;">
    <!-- Informational Grace Period Banner -->
    <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 10px; padding: 14px 16px; margin-bottom: 18px; display: flex; align-items: flex-start; gap: 12px;">
      <div style="color: #f59e0b; flex-shrink: 0; margin-top: 2px;">
        <?php echo icon('info', '', 20); ?>
      </div>
      <div style="font-size: 13px; line-height: 1.5; color: var(--text-secondary);">
        <strong style="color: #f59e0b; display: block; margin-bottom: 2px;">7-Day Recovery Grace Period Active</strong>
        Soft-deleted dues packages are safely queued here for <b>7 days</b> before automatic permanent deletion.
        During this period, <b>existing members are NOT yet affected</b> (assigned members can still view their balance, receipts, and submit dues).
        You can <b>Restore</b> any package to active status, or <b>Permanently Delete</b> immediately using your admin password.
      </div>
    </div>

    <?php if (empty($trashDues)): ?>
      <div style="text-align: center; padding: 40px 16px; color: var(--text-secondary);">
        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(16,185,129,0.1); color: #10b981; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
          <?php echo icon('check', '', 24); ?>
        </div>
        <strong style="display: block; font-size: 15px; color: var(--text-primary);">Trash bin is empty</strong>
        <p class="muted" style="margin-top: 4px; font-size: 13px;">No dues packages are currently in the soft-deleted state.</p>
      </div>
    <?php else: ?>
      <div class="table-shell">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Amount</th>
              <th>Term</th>
              <th>Soft-Deleted On</th>
              <th>Purge Countdown</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($trashDues as $d): ?>
              <?php
                $delTs = strtotime($d['deleted_at']);
                $purgeTs = $delTs + (7 * 86400);
                $diff = $purgeTs - time();
                if ($diff > 0) {
                    $days = floor($diff / 86400);
                    $hrs = floor(($diff % 86400) / 3600);
                    $countdownStr = "Auto-purges in {$days}d {$hrs}h (" . date('M d', $purgeTs) . ")";
                    $badgeStyle = "background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);";
                } else {
                    $countdownStr = "Scheduled for immediate auto-purge";
                    $badgeStyle = "background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);";
                }
              ?>
              <tr>
                <td>
                  <strong style="color: var(--text-primary); display: block;"><?php echo htmlspecialchars($d['title']); ?></strong>
                  <span class="muted" style="font-size: 12px;"><?php echo htmlspecialchars($d['description'] ?? '—'); ?></span>
                </td>
                <td><strong style="color: #10b981;">₱<?php echo number_format($d['amount'], 2); ?></strong></td>
                <td><span class="badge-pill badge-partial"><?php echo htmlspecialchars($d['term'] ?: 'Standard'); ?></span></td>
                <td>
                  <span style="font-size: 12.5px; color: var(--text-secondary); display: block;">
                    <?php echo date('M d, Y h:i A', $delTs); ?>
                  </span>
                  <?php if (!empty($d['deleted_by_name'])): ?>
                    <span class="muted" style="font-size: 11px;">By: <?php echo htmlspecialchars($d['deleted_by_name']); ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge-pill" style="<?php echo $badgeStyle; ?> font-size: 11.5px; padding: 4px 10px; border-radius: 20px; font-weight: 600;">
                    <?php echo icon('clock', '', 12); ?> <?php echo $countdownStr; ?>
                  </span>
                </td>
                <td style="white-space: nowrap; text-align: right;">
                  <!-- Restore Form -->
                  <form method="post" class="inline" style="display:inline-block; margin-right: 6px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="due_id" value="<?php echo $d['id']; ?>">
                    <button class="btn btn-sm btn-primary" type="submit" style="display:inline-flex; align-items:center; gap:4px; background:#16a34a; border-color:#16a34a;">
                      <?php echo icon('check', '', 12); ?> <span>Restore</span>
                    </button>
                  </form>

                  <!-- Permanent Delete Button with Password Override -->
                  <button type="button" class="btn btn-sm btn-danger" onclick="openPermanentDeleteModal(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['title'])); ?>', true)" style="display:inline-flex; align-items:center; gap:4px;">
                    <?php echo icon('trash', '', 12); ?> <span>Purge with Password</span>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ======================================================== -->
<!-- PERMANENT DELETION WITH ADMIN PASSWORD CONFIRMATION MODAL -->
<!-- ======================================================== -->
<div id="permanentDeleteModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #18243a); border:1px solid #ef4444; border-radius:14px; max-width:490px; width:100%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6); color:var(--text-primary); padding:24px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
      <div style="width:42px; height:42px; border-radius:10px; background:rgba(239,68,68,0.15); color:#ef4444; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
        <?php echo icon('alert', '', 22); ?>
      </div>
      <div>
        <h3 style="margin:0; font-size:17px; font-weight:700; color:#ef4444;">Permanent Deletion Override</h3>
        <p class="muted" style="margin:2px 0 0; font-size:12px;">Admin password verification required.</p>
      </div>
    </div>

    <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.25); border-radius:8px; padding:12px; margin-bottom:18px; font-size:12.5px; line-height:1.5;">
      <strong>Irreversible Action:</strong> You are authorizing the permanent deletion of <b id="permDeleteDueTitle" style="color:#ffffff;">this due package</b>.
      This will immediately wipe the package and cascade all associated member dues records, payment submissions, and receipts from the system.
    </div>

    <form method="post" id="permDeleteForm">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="permanent_delete">
      <input type="hidden" name="due_id" id="permDeleteDueId" value="">
      <input type="hidden" name="from_trash" id="permDeleteFromTrash" value="0">
      
      <div class="field" style="margin-bottom:18px;">
        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">
          Confirm Your Admin Password <span style="color:#ef4444;">*</span>
        </label>
        <div style="position:relative;">
          <input type="password" name="admin_password" id="permDeletePassword" required placeholder="Enter your admin account password" style="width:100%; padding:10px 40px 10px 12px; border:1px solid var(--border-color); border-radius:8px; background:var(--field-bg); color:var(--text-primary); font-size:13px;">
          <button type="button" onclick="const p = document.getElementById('permDeletePassword'); p.type = p.type === 'password' ? 'text' : 'password';" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:transparent; border:none; color:var(--text-secondary); cursor:pointer; padding:4px;">
            👁️
          </button>
        </div>
        <span class="muted" style="display:block; font-size:11.5px; margin-top:4px;">Enter your login password to override the 7-day grace period.</span>
      </div>

      <div style="display:flex; gap:10px; justify-content:flex-end;">
        <button type="button" class="btn btn-sm btn-secondary" onclick="closePermanentDeleteModal()">Cancel</button>
        <button type="submit" class="btn btn-sm btn-danger" style="display:inline-flex; align-items:center; gap:6px;">
          <?php echo icon('trash', '', 14); ?> <span>Permanently Delete Now</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleMemberSearch() {
  document.getElementById('memberSearchBox').style.display =
    document.getElementById('assignType').value === 'specific' ? 'block' : 'none';
}

function filterMembers() {
  const q = document.getElementById('memberSearchInput').value.toLowerCase();
  document.querySelectorAll('#memberResults label').forEach(label => {
    label.style.display = label.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

const selectedMap = {};
function updateSelected(checkbox, name, idNum) {
  if (checkbox.checked) {
    selectedMap[checkbox.value] = name + ' (' + idNum + ')';
  } else {
    delete selectedMap[checkbox.value];
  }
  const container = document.getElementById('selectedTags');
  const keys = Object.keys(selectedMap);
  if (keys.length === 0) {
    container.innerHTML = '<span class="muted" style="font-size:12px;">None selected yet.</span>';
  } else {
    container.innerHTML = keys.map(k => `<span class="selected-tag">${selectedMap[k]}</span>`).join('');
  }
}

function switchDuesView(view) {
  const activeView = document.getElementById('activeDuesView');
  const trashView = document.getElementById('trashDuesView');
  const btnActive = document.getElementById('btnActiveView');
  const btnTrash = document.getElementById('btnTrashView');

  if (view === 'trash') {
    if (activeView) activeView.style.display = 'none';
    if (trashView) trashView.style.display = 'block';
    if (btnActive) btnActive.classList.remove('active');
    if (btnTrash) btnTrash.classList.add('active');
  } else {
    if (activeView) activeView.style.display = 'block';
    if (trashView) trashView.style.display = 'none';
    if (btnActive) btnActive.classList.add('active');
    if (btnTrash) btnTrash.classList.remove('active');
  }

  if (window.history && window.history.replaceState) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.history.replaceState(null, '', url);
  }
}

function openPermanentDeleteModal(dueId, dueTitle, fromTrash) {
  document.getElementById('permDeleteDueId').value = dueId;
  document.getElementById('permDeleteFromTrash').value = fromTrash ? '1' : '0';
  document.getElementById('permDeleteDueTitle').textContent = '"' + dueTitle + '"';
  document.getElementById('permanentDeleteModal').style.display = 'flex';
  const passInput = document.getElementById('permDeletePassword');
  if (passInput) {
    passInput.value = '';
    setTimeout(() => passInput.focus(), 60);
  }
}

function closePermanentDeleteModal() {
  document.getElementById('permanentDeleteModal').style.display = 'none';
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closePermanentDeleteModal();
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
