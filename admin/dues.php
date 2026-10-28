<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    require_csrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $term = trim($_POST['term'] ?? '');
    $assign_type = $_POST['assign_type'] ?? 'all';
    $specific_members = $_POST['specific_members'] ?? [];

    if ($title === '' || $amount <= 0) {
        if (function_exists('set_flash')) {
            set_flash('error', 'Please provide a valid title and amount.');
        }
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO dues (title, description, amount, due_date, term) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $amount, $due_date, $term]);
            $due_id = $pdo->lastInsertId();

            $ins = $pdo->prepare("INSERT IGNORE INTO member_dues (user_id, due_id, status) VALUES (?, ?, 'unpaid')");

            if ($assign_type === 'all') {
                $members = $pdo->query("SELECT id FROM users WHERE role = 'member' AND status = 'approved'")->fetchAll();
                foreach ($members as $m) {
                    $ins->execute([$m['id'], $due_id]);
                }
            } else {
                foreach ($specific_members as $uid) {
                    $ins->execute([(int)$uid, $due_id]);
                }
            }

            $pdo->commit();
            if (function_exists('set_flash')) {
                set_flash('success', 'Due package created and assigned successfully.');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (function_exists('set_flash')) {
                set_flash('error', 'Failed to create due package.');
            }
        }
    }

    header('Location: dues.php');
    exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    require_csrf();
    $due_id = (int)$_POST['due_id'];
    $stmt = $pdo->prepare("UPDATE dues SET title=?, description=?, amount=?, due_date=?, term=? WHERE id=?");
    $stmt->execute([trim($_POST['title']), trim($_POST['description']), (float)$_POST['amount'], !empty($_POST['due_date']) ? $_POST['due_date'] : null, trim($_POST['term']), $due_id]);
    
    if (function_exists('set_flash')) {
        set_flash('success', 'Due item updated.');
    }
    header('Location: dues.php');
    exit;
}

// Handle delete — manual cascade with transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_csrf();
    $due_id = (int)$_POST['due_id'];

    try {
        $pdo->beginTransaction();

        $md_ids = $pdo->prepare("SELECT id FROM member_dues WHERE due_id = ?");
        $md_ids->execute([$due_id]);
        $md_rows = $md_ids->fetchAll(PDO::FETCH_COLUMN);

        if ($md_rows) {
            foreach ($md_rows as $md_id) {
                $pay_ids = $pdo->prepare("SELECT id FROM payments WHERE member_due_id = ?");
                $pay_ids->execute([$md_id]);
                $p_rows = $pay_ids->fetchAll(PDO::FETCH_COLUMN);
                if ($p_rows) {
                    $in = implode(',', array_map('intval', $p_rows));
                    $pdo->exec("DELETE FROM receipts WHERE payment_id IN ($in)");
                    $pdo->exec("DELETE FROM payments WHERE id IN ($in)");
                }
            }
            $in = implode(',', array_map('intval', $md_rows));
            $pdo->exec("DELETE FROM member_dues WHERE id IN ($in)");
        }
        $pdo->prepare("DELETE FROM dues WHERE id=?")->execute([$due_id]);
        $pdo->commit();
        if (function_exists('set_flash')) {
            set_flash('success', 'Due package deleted and removed from all assigned members.');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('set_flash')) {
            set_flash('error', 'Failed to delete due package.');
        }
    }

    header('Location: dues.php');
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

$dues = $pdo->query("SELECT * FROM dues ORDER BY created_at DESC")->fetchAll();
$all_members = $pdo->query("SELECT id, name, id_number FROM users WHERE role = 'member' AND status = 'approved' ORDER BY name ASC")->fetchAll();

$page_title = 'Dues Management • UAP Quezon Chapter';
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
</style>

<div class="page-hero">
  <div>
    <p class="eyebrow">ORGANIZATION DUES</p>
    <h1>Dues Management &amp; Assignment</h1>
    <p class="page-subtitle">Create chapter dues packages, assign fees to members, and configure payment options.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('dues', '', 14); ?> <span><?php echo count($dues); ?> Configured Dues</span>
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
        <p class="muted" style="font-size: 12px; margin: 2px 0 0;">Add an existing due item to a specific member.</p>
      </div>
    </div>

    <?php if (empty($dues) || empty($all_members)): ?>
      <p class="muted" style="text-align:center;padding:24px;">Need at least one configured due and one approved member.</p>
    <?php else: ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="assign">
        <div class="field">
          <label>Select Due Item</label>
          <select name="due_id" required>
            <option value="">Choose a due...</option>
            <?php foreach ($dues as $d): ?>
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

<!-- Existing dues table -->
<div class="card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
    <h2 style="font-size: 17px; margin: 0;">Configured Dues Packages</h2>
    <span class="muted" style="font-size: 13px;"><?php echo count($dues); ?> total</span>
  </div>

  <?php if (empty($dues)): ?>
    <div style="text-align: center; padding: 40px 16px; color: var(--text-secondary);">
      <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(245,158,11,0.1); color: var(--accent-primary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
        <?php echo icon('dues', '', 24); ?>
      </div>
      <strong style="display: block; font-size: 15px; color: var(--text-primary);">No dues configured yet</strong>
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
          <?php foreach ($dues as $d): ?>
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
                <form method="post" class="inline" style="display:inline-block;"
                      data-confirm="Delete '<?php echo htmlspecialchars($d['title']); ?>'? This will permanently remove it from all members who have it assigned."
                      data-confirm-title="Delete Due Package"
                      data-confirm-btn="Delete"
                      data-confirm-class="btn-danger">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="due_id" value="<?php echo $d['id']; ?>">
                  <button class="btn btn-sm btn-danger" type="submit" style="display:inline-flex;align-items:center;gap:4px;">
                    <?php echo icon('trash', '', 12); ?> <span>Delete</span>
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
