<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Auto-create table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_inquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(50) NULL,
            subject VARCHAR(255) NULL,
            message TEXT NOT NULL,
            status ENUM('unread', 'read', 'replied', 'archived') NOT NULL DEFAULT 'unread',
            admin_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_inquiries_status (status),
            INDEX idx_inquiries_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);

    if ($action === 'update_status' && $inquiry_id > 0) {
        $new_status = $_POST['new_status'] ?? 'read';
        $allowed = ['unread', 'read', 'replied', 'archived'];
        if (in_array($new_status, $allowed)) {
            $stmt = $pdo->prepare("UPDATE contact_inquiries SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $inquiry_id]);
            $success = 'Inquiry status updated to ' . ucfirst($new_status) . '.';
        }
    }

    if ($action === 'save_notes' && $inquiry_id > 0) {
        $notes = trim($_POST['admin_notes'] ?? '');
        $stmt = $pdo->prepare("UPDATE contact_inquiries SET admin_notes = ? WHERE id = ?");
        $stmt->execute([$notes, $inquiry_id]);
        $success = 'Secretariat notes saved successfully.';
    }

    if ($action === 'delete_inquiry' && $inquiry_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM contact_inquiries WHERE id = ?");
        $stmt->execute([$inquiry_id]);
        $success = 'Contact inquiry deleted successfully.';
    }

    if ($action === 'mark_all_read') {
        $pdo->query("UPDATE contact_inquiries SET status = 'read' WHERE status = 'unread'");
        $success = 'All unread inquiries marked as read.';
    }
}

// Counts for filters and stats
$count_all = (int)$pdo->query("SELECT COUNT(*) FROM contact_inquiries")->fetchColumn();
$count_unread = (int)$pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE status = 'unread'")->fetchColumn();
$count_read = (int)$pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE status = 'read'")->fetchColumn();
$count_replied = (int)$pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE status = 'replied'")->fetchColumn();
$count_archived = (int)$pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE status = 'archived'")->fetchColumn();
$count_today = (int)$pdo->query("SELECT COUNT(*) FROM contact_inquiries WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Filter & Search handling
$filter_status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM contact_inquiries WHERE 1=1";
$params = [];

if ($filter_status !== 'all' && in_array($filter_status, ['unread', 'read', 'replied', 'archived'])) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY (status = 'unread') DESC, created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

$page_title = 'Contact Inquiries & Messages';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">COMMUNICATIONS &amp; INQUIRIES</p>
    <h1>Website Contact Inquiries</h1>
    <p class="page-subtitle">Manage and respond to messages submitted by visitors through the public website contact form.</p>
  </div>
  <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    <?php if ($count_unread > 0): ?>
      <form method="post" style="display:inline-block;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button class="btn btn-sm btn-outline" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('check', '', 14); ?> <span>Mark All as Read</span>
        </button>
      </form>
    <?php endif; ?>
    <div class="hero-badge" style="background: <?php echo $count_unread > 0 ? 'rgba(245,158,11,0.15)' : 'var(--bg-secondary)'; ?>; color: <?php echo $count_unread > 0 ? 'var(--c-gold)' : 'var(--text-secondary)'; ?>;">
      <?php echo icon('mail', '', 14); ?> <span><?php echo $count_unread; ?> Unread Inquiries</span>
    </div>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert alert-success">
    <div style="display: flex; align-items: center; gap: 8px;">
      <?php echo icon('check', '', 18); ?>
      <span><?php echo htmlspecialchars($success); ?></span>
    </div>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-error">
    <div style="display: flex; align-items: center; gap: 8px;">
      <?php echo icon('alert', '', 18); ?>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
  </div>
<?php endif; ?>

<!-- STATS OVERVIEW -->
<div class="stats-overview-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
  <div class="stat-card" style="padding: 16px; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border-color);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <span class="muted" style="font-size: 12px; font-weight: 600; text-transform: uppercase;">Total Inquiries</span>
        <h3 style="font-size: 24px; margin: 4px 0 0; color: var(--text-primary);"><?php echo number_format($count_all); ?></h3>
      </div>
      <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(59,130,246,0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('mail', '', 20); ?>
      </div>
    </div>
  </div>

  <div class="stat-card" style="padding: 16px; background: var(--bg-secondary); border-radius: 12px; border: 1px solid <?php echo $count_unread > 0 ? 'rgba(245,158,11,0.4)' : 'var(--border-color)'; ?>;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <span class="muted" style="font-size: 12px; font-weight: 600; text-transform: uppercase;">Unread / New</span>
        <h3 style="font-size: 24px; margin: 4px 0 0; color: <?php echo $count_unread > 0 ? 'var(--accent-primary, #f59e0b)' : 'var(--text-primary)'; ?>;"><?php echo number_format($count_unread); ?></h3>
      </div>
      <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(245,158,11,0.12); color: var(--accent-primary, #f59e0b); display: flex; align-items: center; justify-content: center;">
        <?php echo icon('bell', '', 20); ?>
      </div>
    </div>
  </div>

  <div class="stat-card" style="padding: 16px; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border-color);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <span class="muted" style="font-size: 12px; font-weight: 600; text-transform: uppercase;">Replied / Handled</span>
        <h3 style="font-size: 24px; margin: 4px 0 0; color: #10b981;"><?php echo number_format($count_replied); ?></h3>
      </div>
      <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(16,185,129,0.12); color: #10b981; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('check', '', 20); ?>
      </div>
    </div>
  </div>

  <div class="stat-card" style="padding: 16px; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border-color);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <span class="muted" style="font-size: 12px; font-weight: 600; text-transform: uppercase;">Received Today</span>
        <h3 style="font-size: 24px; margin: 4px 0 0; color: var(--text-primary);"><?php echo number_format($count_today); ?></h3>
      </div>
      <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(139,92,246,0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('calendar', '', 20); ?>
      </div>
    </div>
  </div>
</div>

<!-- CONTROLS & FILTER TABS -->
<div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; flex-wrap: wrap;">
  <div style="display: flex; gap: 6px; flex-wrap: wrap;">
    <a href="inquiries.php?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
       class="filter-tab-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
      All (<?php echo $count_all; ?>)
    </a>
    <a href="inquiries.php?status=unread<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
       class="filter-tab-btn <?php echo $filter_status === 'unread' ? 'active' : ''; ?>"
       style="<?php echo $count_unread > 0 ? 'color: var(--accent-primary, #f59e0b); font-weight: 700;' : ''; ?>">
      ● Unread (<?php echo $count_unread; ?>)
    </a>
    <a href="inquiries.php?status=read<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
       class="filter-tab-btn <?php echo $filter_status === 'read' ? 'active' : ''; ?>">
      Read (<?php echo $count_read; ?>)
    </a>
    <a href="inquiries.php?status=replied<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
       class="filter-tab-btn <?php echo $filter_status === 'replied' ? 'active' : ''; ?>">
      Replied (<?php echo $count_replied; ?>)
    </a>
    <a href="inquiries.php?status=archived<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
       class="filter-tab-btn <?php echo $filter_status === 'archived' ? 'active' : ''; ?>">
      Archived (<?php echo $count_archived; ?>)
    </a>
  </div>

  <form method="get" style="display: flex; gap: 8px; align-items: center;">
    <?php if ($filter_status !== 'all'): ?>
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
    <?php endif; ?>
    <div style="position: relative;">
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search sender, email, message..." style="padding: 8px 12px; font-size: 13px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); width: 240px;">
    </div>
    <button class="btn btn-sm" type="submit" style="padding: 8px 12px;">Search</button>
    <?php if (!empty($search)): ?>
      <a href="inquiries.php?status=<?php echo htmlspecialchars($filter_status); ?>" class="btn btn-sm btn-outline" style="padding: 8px 12px;">Clear</a>
    <?php endif; ?>
  </form>
</div>

<style>
.filter-tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
  font-size: 12.5px;
  text-decoration: none;
  transition: all 0.2s ease;
}
.filter-tab-btn:hover {
  background: var(--bg-tertiary, rgba(255,255,255,0.06));
  color: var(--text-primary);
}
.filter-tab-btn.active {
  background: var(--accent-primary, #f59e0b);
  color: #111827;
  font-weight: 700;
  border-color: transparent;
}
.inquiry-row.unread {
  background: rgba(245, 158, 11, 0.04);
}
.badge-status-unread {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.3);
}
.badge-status-read {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  border: 1px solid rgba(59, 130, 246, 0.3);
}
.badge-status-replied {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}
.badge-status-archived {
  background: rgba(156, 163, 175, 0.15);
  color: #9ca3af;
  border: 1px solid rgba(156, 163, 175, 0.3);
}
</style>

<!-- INQUIRIES LIST TABLE -->
<div class="card" style="padding: 0; overflow: hidden;">
  <?php if (empty($inquiries)): ?>
    <div style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
      <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(59,130,246,0.1); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
        <?php echo icon('mail', '', 28); ?>
      </div>
      <strong style="display: block; font-size: 16px; color: var(--text-primary);">No inquiries found</strong>
      <p class="muted" style="margin-top: 4px; font-size: 13px;">
        <?php echo !empty($search) ? 'No inquiries matching "' . htmlspecialchars($search) . '"' : 'There are no inquiries in this category.'; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th style="width: 220px;">Sender</th>
            <th style="min-width: 250px;">Subject &amp; Message</th>
            <th style="width: 140px;">Received</th>
            <th style="width: 110px;">Status</th>
            <th style="width: 180px; text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($inquiries as $inq): ?>
            <tr class="inquiry-row <?php echo $inq['status'] === 'unread' ? 'unread' : ''; ?>">
              <td>
                <div style="display: flex; align-items: flex-start; gap: 8px;">
                  <?php if ($inq['status'] === 'unread'): ?>
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--accent-primary, #f59e0b); margin-top: 5px; flex-shrink: 0;" title="Unread"></span>
                  <?php endif; ?>
                  <div>
                    <strong style="font-size: 13.5px; color: var(--text-primary); display: block;">
                      <?php echo htmlspecialchars($inq['name']); ?>
                    </strong>
                    <?php if (!empty($inq['email'])): ?>
                      <a href="mailto:<?php echo htmlspecialchars($inq['email']); ?>" class="muted" style="font-size: 12px; color: var(--text-secondary); text-decoration: none; display: block;">
                        <?php echo htmlspecialchars($inq['email']); ?>
                      </a>
                    <?php endif; ?>
                    <?php if (!empty($inq['phone'])): ?>
                      <a href="tel:<?php echo htmlspecialchars($inq['phone']); ?>" class="muted" style="font-size: 11.5px; color: var(--accent-primary, #f59e0b); text-decoration: none; display: block;">
                        <?php echo htmlspecialchars($inq['phone']); ?>
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <td>
                <div style="cursor: pointer;" onclick="openInquiryModal(<?php echo htmlspecialchars(json_encode($inq), ENT_QUOTES, 'UTF-8'); ?>)">
                  <strong style="font-size: 13.5px; color: var(--text-primary); display: block; margin-bottom: 2px;">
                    <?php echo htmlspecialchars($inq['subject'] ?: 'Website Inquiry'); ?>
                  </strong>
                  <p class="muted" style="font-size: 12.5px; margin: 0; line-height: 1.4; max-width: 480px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <?php echo htmlspecialchars($inq['message']); ?>
                  </p>
                  <?php if (!empty($inq['admin_notes'])): ?>
                    <div style="margin-top: 4px; font-size: 11.5px; color: var(--accent-primary, #f59e0b);">
                      <em>Notes: <?php echo htmlspecialchars(substr($inq['admin_notes'], 0, 50)) . (strlen($inq['admin_notes']) > 50 ? '...' : ''); ?></em>
                    </div>
                  <?php endif; ?>
                </div>
              </td>

              <td>
                <span style="font-size: 12px; color: var(--text-primary); display: block;">
                  <?php echo date('M d, Y', strtotime($inq['created_at'])); ?>
                </span>
                <span class="muted" style="font-size: 11px;">
                  <?php echo date('h:i A', strtotime($inq['created_at'])); ?>
                </span>
              </td>

              <td>
                <span class="badge-pill badge-status-<?php echo $inq['status']; ?>" style="font-size: 11px; padding: 3px 8px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.04em;">
                  <?php echo ucfirst($inq['status']); ?>
                </span>
              </td>

              <td style="text-align: right; white-space: nowrap;">
                <button type="button" class="btn btn-sm" onclick="openInquiryModal(<?php echo htmlspecialchars(json_encode($inq), ENT_QUOTES, 'UTF-8'); ?>)" style="padding: 4px 10px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; margin-right: 4px;">
                  <?php echo icon('document', '', 12); ?> <span>View</span>
                </button>

                <?php if (!empty($inq['email'])): ?>
                  <a href="mailto:<?php echo htmlspecialchars($inq['email']); ?>?subject=<?php echo urlencode('Re: ' . ($inq['subject'] ?: 'UAP Quezon Province Chapter Inquiry')); ?>"
                     class="btn btn-sm btn-outline"
                     title="Direct Email Reply"
                     style="padding: 4px 8px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; margin-right: 4px;">
                    <?php echo icon('mail', '', 12); ?> <span>Reply</span>
                  </a>
                <?php elseif (!empty($inq['phone'])): ?>
                  <a href="tel:<?php echo htmlspecialchars($inq['phone']); ?>"
                     class="btn btn-sm btn-outline"
                     title="Direct Phone Call"
                     style="padding: 4px 8px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; margin-right: 4px;">
                    <?php echo icon('phone', '', 12); ?> <span>Call</span>
                  </a>
                <?php endif; ?>

                <form method="post" class="inline" style="display:inline-block;"
                      data-confirm="Delete inquiry from <?php echo htmlspecialchars($inq['name']); ?>?"
                      data-confirm-title="Delete Contact Inquiry"
                      data-confirm-btn="Delete"
                      data-confirm-class="btn-danger">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="inquiry_id" value="<?php echo $inq['id']; ?>">
                  <input type="hidden" name="action" value="delete_inquiry">
                  <button class="btn btn-sm btn-danger" type="submit" style="padding: 4px 8px;" title="Delete">
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

<!-- DETAIL / REPLY MODAL -->
<div id="inquiryModal" style="display: none; position: fixed; inset: 0; z-index: 10000; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); align-items: center; justify-content: center; padding: 20px;">
  <div style="background: var(--bg-primary, #0c1222); border: 1px solid var(--border-color); border-radius: 16px; width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.6); padding: 24px; position: relative;">
    <button type="button" onclick="closeInquiryModal()" style="position: absolute; top: 16px; right: 16px; background: transparent; border: none; color: var(--text-secondary); font-size: 20px; cursor: pointer; padding: 4px 8px; border-radius: 6px;">&times;</button>

    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
      <div style="width: 42px; height: 42px; border-radius: 10px; background: rgba(245,158,11,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
        <?php echo icon('mail', '', 20); ?>
      </div>
      <div>
        <h2 id="modalSenderName" style="font-size: 17px; margin: 0; color: var(--text-primary);">Sender Name</h2>
        <span id="modalMeta" class="muted" style="font-size: 12px;">Email &bull; Date</span>
      </div>
    </div>

    <!-- SENDER INFO BOX -->
    <div style="background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color); padding: 12px 16px; margin-bottom: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
      <div>
        <span class="muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600;">Email Address</span>
        <div id="modalEmail" style="font-size: 13px; font-weight: 500; color: var(--text-primary); margin-top: 2px;"></div>
      </div>
      <div>
        <span class="muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600;">Contact Phone</span>
        <div id="modalPhone" style="font-size: 13px; font-weight: 500; color: var(--text-primary); margin-top: 2px;">None</div>
      </div>
    </div>

    <!-- SUBJECT & MESSAGE -->
    <div style="margin-bottom: 20px;">
      <span class="muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Subject</span>
      <h3 id="modalSubject" style="font-size: 15px; margin: 0 0 12px; color: var(--text-primary);">Subject Title</h3>

      <span class="muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Message Content</span>
      <div id="modalMessage" style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 10px; padding: 14px; font-size: 13.5px; line-height: 1.6; color: var(--text-primary); white-space: pre-wrap;"></div>
    </div>

    <!-- SECRETARIAT INTERNAL NOTES -->
    <form method="post" style="margin-bottom: 20px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_notes">
      <input type="hidden" name="inquiry_id" id="modalNotesInquiryId" value="">
      <div class="field" style="margin-bottom: 8px;">
        <label style="font-size: 12px;">Secretariat Internal Notes / Follow-Up Details</label>
        <textarea name="admin_notes" id="modalAdminNotes" rows="2" placeholder="e.g. Replied by Ar. Santos via email on 08/30/2026..." style="width: 100%; padding: 10px; font-size: 12.5px;"></textarea>
      </div>
      <button class="btn btn-sm" type="submit" style="font-size: 12px;">Save Notes</button>
    </form>

    <!-- STATUS CHANGER & REPLY BUTTONS -->
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 16px; flex-wrap: wrap;">
      <!-- Status Change Form -->
      <form method="post" style="display: flex; gap: 6px; align-items: center;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="inquiry_id" id="modalStatusInquiryId" value="">
        <span class="muted" style="font-size: 12px; margin-right: 4px;">Status:</span>
        <select name="new_status" id="modalStatusSelect" onchange="this.form.submit()" style="padding: 6px 10px; border-radius: 6px; font-size: 12px; background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
          <option value="unread">Unread</option>
          <option value="read">Read</option>
          <option value="replied">Replied</option>
          <option value="archived">Archived</option>
        </select>
      </form>

      <div style="display: flex; gap: 8px;">
        <a id="modalMailtoBtn" href="#" class="btn btn-sm btn-gold" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('mail', '', 14); ?> <span>Send Email Reply</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline" onclick="closeInquiryModal()">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function openInquiryModal(inq) {
  document.getElementById('modalSenderName').textContent = inq.name;
  document.getElementById('modalMeta').textContent = (inq.email || inq.phone || 'No direct contact') + ' • ' + inq.created_at;
  document.getElementById('modalEmail').textContent = inq.email || 'Not Provided';
  document.getElementById('modalPhone').textContent = inq.phone || 'Not Provided';
  document.getElementById('modalSubject').textContent = inq.subject || 'Website Inquiry';
  document.getElementById('modalMessage').textContent = inq.message;
  document.getElementById('modalAdminNotes').value = inq.admin_notes || '';
  document.getElementById('modalNotesInquiryId').value = inq.id;
  document.getElementById('modalStatusInquiryId').value = inq.id;
  document.getElementById('modalStatusSelect').value = inq.status;

  const mailBtn = document.getElementById('modalMailtoBtn');
  if (inq.email) {
    const mailtoSubject = encodeURIComponent('Re: ' + (inq.subject || 'UAP Quezon Province Chapter Inquiry'));
    const mailtoBody = encodeURIComponent('\n\n--- Original Inquiry ---\nFrom: ' + inq.name + '\nDate: ' + inq.created_at + '\n\n' + inq.message);
    mailBtn.href = 'mailto:' + inq.email + '?subject=' + mailtoSubject + '&body=' + mailtoBody;
    mailBtn.style.display = 'inline-flex';
    mailBtn.innerHTML = '<?php echo icon("mail", "", 14); ?> <span>Send Email Reply</span>';
  } else if (inq.phone) {
    mailBtn.href = 'tel:' + inq.phone;
    mailBtn.style.display = 'inline-flex';
    mailBtn.innerHTML = '<?php echo icon("phone", "", 14); ?> <span>Call ' + inq.phone + '</span>';
  } else {
    mailBtn.style.display = 'none';
  }

  const modal = document.getElementById('inquiryModal');
  modal.style.display = 'flex';

  // Automatically mark as read if it was unread
  if (inq.status === 'unread') {
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo csrf_token(); ?>');
    formData.append('action', 'update_status');
    formData.append('inquiry_id', inq.id);
    formData.append('new_status', 'read');
    fetch('inquiries.php', { method: 'POST', body: formData }).catch(()=>{});
  }
}

function closeInquiryModal() {
  document.getElementById('inquiryModal').style.display = 'none';
}

window.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeInquiryModal();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
