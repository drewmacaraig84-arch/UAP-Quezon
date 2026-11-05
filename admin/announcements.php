<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulletin_helper.php';
require_admin();
ensure_bulletin_schema($pdo);

$adminId = current_user_id();
$error = '';
$success = '';

// -----------------------------------------------------------------------------
// POST HANDLERS: CREATE, EDIT, PIN, PUBLISH, DELETE
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_bulletin' || $action === 'edit_bulletin') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'General Chapter Announcement');
        $audience = trim($_POST['target_audience'] ?? 'members_only');
        $datePosted = trim($_POST['date_posted'] ?? date('Y-m-d'));
        $dateDisplay = !empty($_POST['date_display']) ? trim($_POST['date_display']) : strtoupper(date('M d, Y', strtotime($datePosted)));
        $isPinned = !empty($_POST['is_pinned']) ? 1 : 0;
        $isPublished = !empty($_POST['is_published']) ? 1 : 0;
        $summary = trim($_POST['summary'] ?? '');
        $content = trim($_POST['content'] ?? '');

        // Validation
        if (!$title) {
            $error = 'Announcement Title is required.';
        } elseif (!in_array($audience, ['members_only', 'public', 'all'], true)) {
            $error = 'Invalid target audience selected.';
        } else {
            // Process Image Poster Upload (JPG, PNG, WebP up to 12MB)
            $imagePath = null;
            $uploadDirImg = __DIR__ . '/../uploads/news/';
            if (!is_dir($uploadDirImg)) @mkdir($uploadDirImg, 0775, true);

            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                $allowedImg = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowedImg, true) && $_FILES['cover_image']['size'] <= 12 * 1024 * 1024) {
                    $fileName = 'bulletin_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadDirImg . $fileName)) {
                        @chmod($uploadDirImg . $fileName, 0666);
                        $imagePath = 'uploads/news/' . $fileName;
                    }
                }
            }

            // Process PDF Circular Upload (PDF up to 20MB)
            $pdfPath = null;
            $uploadDirPdf = __DIR__ . '/../uploads/circulars/';
            if (!is_dir($uploadDirPdf)) @mkdir($uploadDirPdf, 0775, true);

            if (isset($_FILES['pdf_circular']) && $_FILES['pdf_circular']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['pdf_circular']['name'], PATHINFO_EXTENSION));
                if ($ext === 'pdf' && $_FILES['pdf_circular']['size'] <= 20 * 1024 * 1024) {
                    $fileName = 'circular_' . time() . '_' . bin2hex(random_bytes(3)) . '.pdf';
                    if (move_uploaded_file($_FILES['pdf_circular']['tmp_name'], $uploadDirPdf . $fileName)) {
                        @chmod($uploadDirPdf . $fileName, 0666);
                        $pdfPath = 'uploads/circulars/' . $fileName;
                    }
                }
            }

            if ($action === 'create_bulletin') {
                $pinnedAt = $isPinned ? date('Y-m-d H:i:s') : null;
                $stmt = $pdo->prepare("
                    INSERT INTO news_announcements 
                    (title, summary, content, target_audience, category, is_pinned, pinned_at, date_display, date_posted, image_path, pdf_attachment, author_id, is_published, is_active, display_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, (SELECT COALESCE(MAX(display_order), 0) + 1 FROM news_announcements n2))
                ");
                $stmt->execute([
                    $title, $summary, $content, $audience, $category, $isPinned, $pinnedAt,
                    $dateDisplay, $datePosted, $imagePath, $pdfPath, $adminId, $isPublished
                ]);

                if (function_exists('audit_log')) {
                    audit_log('CREATE_ANNOUNCEMENT', 'BULLETINS', "Posted announcement: $title (Audience: $audience)", $adminId);
                }
                $success = "Announcement <strong>" . htmlspecialchars($title) . "</strong> published successfully!";
            } else {
                $bulletinId = (int)($_POST['bulletin_id'] ?? 0);
                
                // Existing files retain if not replaced
                $cur = $pdo->prepare("SELECT image_path, pdf_attachment, is_pinned FROM news_announcements WHERE id = ?");
                $cur->execute([$bulletinId]);
                $currData = $cur->fetch(PDO::FETCH_ASSOC);

                if (!$imagePath) {
                    $imagePath = !empty($_POST['remove_cover_image']) ? null : ($currData['image_path'] ?? null);
                }
                if (!$pdfPath) {
                    $pdfPath = !empty($_POST['remove_pdf_circular']) ? null : ($currData['pdf_attachment'] ?? null);
                }

                $pinnedAt = $isPinned ? (!empty($currData['is_pinned']) ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s')) : null;

                $stmt = $pdo->prepare("
                    UPDATE news_announcements SET 
                    title = ?, summary = ?, content = ?, target_audience = ?, category = ?, 
                    is_pinned = ?, pinned_at = ?, date_display = ?, date_posted = ?, 
                    image_path = ?, pdf_attachment = ?, is_published = ?
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $stmt->execute([
                    $title, $summary, $content, $audience, $category,
                    $isPinned, $pinnedAt, $dateDisplay, $datePosted,
                    $imagePath, $pdfPath, $isPublished, $bulletinId
                ]);

                if (function_exists('audit_log')) {
                    audit_log('UPDATE_ANNOUNCEMENT', 'BULLETINS', "Updated announcement #$bulletinId: $title", $adminId);
                }
                $success = "Announcement <strong>" . htmlspecialchars($title) . "</strong> updated successfully.";
            }
        }
    }

    if ($action === 'toggle_pin') {
        $bulletinId = (int)($_POST['bulletin_id'] ?? 0);
        $curPin = (int)($_POST['current_pin'] ?? 0);
        $newPin = $curPin ? 0 : 1;
        $pinnedAt = $newPin ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("UPDATE news_announcements SET is_pinned = ?, pinned_at = ? WHERE id = ?");
        $stmt->execute([$newPin, $pinnedAt, $bulletinId]);
        $success = $newPin ? "Notice pinned to top of Member Dashboard." : "Notice unpinned from Member Dashboard.";
    }

    if ($action === 'toggle_publish') {
        $bulletinId = (int)($_POST['bulletin_id'] ?? 0);
        $curPub = (int)($_POST['current_publish'] ?? 0);
        $newPub = $curPub ? 0 : 1;

        $stmt = $pdo->prepare("UPDATE news_announcements SET is_published = ? WHERE id = ?");
        $stmt->execute([$newPub, $bulletinId]);
        $success = $newPub ? "Announcement published." : "Announcement moved to draft status.";
    }

    if ($action === 'delete_bulletin') {
        $bulletinId = (int)($_POST['bulletin_id'] ?? 0);
        if ($bulletinId > 0) {
            $stmt = $pdo->prepare("UPDATE news_announcements SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
            $stmt->execute([$adminId, $bulletinId]);

            if (function_exists('audit_log')) {
                audit_log('DELETE_ANNOUNCEMENT', 'BULLETINS', "Deleted announcement #$bulletinId", $adminId);
            }
            $success = "Announcement moved to trash / soft-deleted.";
        }
    }
}

// -----------------------------------------------------------------------------
// QUERY ANNOUNCEMENTS & METRICS
// -----------------------------------------------------------------------------
$filterAudience = trim($_GET['audience'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT n.*, u.name as author_name 
    FROM news_announcements n 
    LEFT JOIN users u ON n.author_id = u.id 
    WHERE n.deleted_at IS NULL
";
$params = [];

if ($filterAudience !== '') {
    $query .= " AND n.target_audience = ?";
    $params[] = $filterAudience;
}
if ($filterCategory !== '') {
    $query .= " AND n.category = ?";
    $params[] = $filterCategory;
}
if ($search !== '') {
    $query .= " AND (n.title LIKE ? OR n.summary LIKE ? OR n.content LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}

$query .= " ORDER BY n.is_pinned DESC, COALESCE(n.pinned_at, n.created_at) DESC, n.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bulletins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM news_announcements WHERE deleted_at IS NULL")->fetchColumn();
$membersOnlyCount = (int)$pdo->query("SELECT COUNT(*) FROM news_announcements WHERE target_audience = 'members_only' AND deleted_at IS NULL")->fetchColumn();
$publicCount = (int)$pdo->query("SELECT COUNT(*) FROM news_announcements WHERE target_audience = 'public' AND deleted_at IS NULL")->fetchColumn();
$pinnedCount = (int)$pdo->query("SELECT COUNT(*) FROM news_announcements WHERE is_pinned = 1 AND deleted_at IS NULL")->fetchColumn();

$categories = get_bulletin_categories();
$page_title = 'Chapter Bulletins & Announcements • UAP Quezon';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">COMMUNICATIONS &amp; CIRCULARS</p>
    <h1>Chapter Bulletins &amp; Announcements</h1>
    <p class="page-subtitle">Publish internal member memorandums, board resolutions, and public chapter announcements.</p>
  </div>
  <div>
    <button type="button" class="btn btn-primary" onclick="openCreateModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
      <?php echo icon('plus', '', 14); ?> <span>New Announcement</span>
    </button>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger" style="margin-bottom: 20px;">
    <?php echo htmlspecialchars($error); ?>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success" style="margin-bottom: 20px;">
    <?php echo $success; ?>
  </div>
<?php endif; ?>

<!-- METRICS CARDS (1x4 BOXES) -->
<style>
.stat-grid-1x4 {
  display: grid !important;
  grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
  gap: 16px !important;
  margin-bottom: 24px !important;
}
@media (max-width: 1024px) {
  .stat-grid-1x4 {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
  }
}
@media (max-width: 580px) {
  .stat-grid-1x4 {
    grid-template-columns: 1fr !important;
  }
}
</style>

<div class="stat-grid stat-grid-1x4">
  <div class="stat-card" style="border-top: 3px solid #3b82f6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Bulletins</span>
      <div class="stat-card-icon" style="background: rgba(59,130,246,0.12); color: #3b82f6;">
        <?php echo icon('newspaper', '', 18); ?>
      </div>
    </div>
    <strong style="color: var(--text-primary); font-size: 26px;"><?php echo number_format($totalCount); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Active notices</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #f59e0b;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Members Only</span>
      <div class="stat-card-icon" style="background: rgba(245,158,11,0.12); color: #f59e0b;">
        <?php echo icon('members', '', 18); ?>
      </div>
    </div>
    <strong style="color: #f59e0b; font-size: 26px;"><?php echo number_format($membersOnlyCount); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Restricted to portal</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Public Website</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('globe', '', 18); ?>
      </div>
    </div>
    <strong style="color: #10b981; font-size: 26px;"><?php echo number_format($publicCount); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Publicly viewable</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #8b5cf6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Pinned on Dashboard</span>
      <div class="stat-card-icon" style="background: rgba(139,92,246,0.12); color: #8b5cf6;">
        <?php echo icon('award', '', 18); ?>
      </div>
    </div>
    <strong style="color: #8b5cf6; font-size: 26px;"><?php echo number_format($pinnedCount); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Top priority notices</span>
  </div>
</div>

<!-- FILTER BAR -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
  <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin: 0;">
    <div style="flex: 1; min-width: 200px;">
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search announcement title, circular content..." style="width: 100%; font-size: 13px;">
    </div>
    <div style="min-width: 170px;">
      <select name="audience" style="width: 100%; font-size: 13px;">
        <option value="">All Audiences</option>
        <option value="members_only" <?php echo $filterAudience === 'members_only' ? 'selected' : ''; ?>>Members Only</option>
        <option value="public" <?php echo $filterAudience === 'public' ? 'selected' : ''; ?>>Public Website</option>
        <option value="all" <?php echo $filterAudience === 'all' ? 'selected' : ''; ?>>All (Public &amp; Members)</option>
      </select>
    </div>
    <div style="min-width: 220px;">
      <select name="category" style="width: 100%; font-size: 13px;">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-secondary" style="font-size: 12px; padding: 7px 14px;">
      Filter
    </button>
    <?php if ($filterAudience !== '' || $filterCategory !== '' || $search !== ''): ?>
      <a href="announcements.php" class="btn btn-outline" style="font-size: 12px; padding: 7px 12px;">Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- ANNOUNCEMENTS TABLE -->
<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
    <h2 style="font-size: 16px; margin: 0; color: var(--text-primary);">Announcements &amp; Memorandums Roster</h2>
    <span class="muted" style="font-size: 12px;"><?php echo count($bulletins); ?> records</span>
  </div>

  <?php if (empty($bulletins)): ?>
    <div style="text-align: center; padding: 48px; color: var(--text-secondary);">
      <p style="margin: 0; font-size: 14px;">No announcements found matching the selected filters.</p>
      <button type="button" class="btn btn-sm btn-primary" onclick="openCreateModal()" style="margin-top: 12px;">Create Announcement</button>
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th style="width: 40px;">Pin</th>
            <th>Title &amp; Date</th>
            <th>Category</th>
            <th>Target Audience</th>
            <th>Attachments</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bulletins as $b): ?>
            <tr>
              <!-- Pin Toggle -->
              <td>
                <form method="post" style="margin: 0;">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="toggle_pin">
                  <input type="hidden" name="bulletin_id" value="<?php echo $b['id']; ?>">
                  <input type="hidden" name="current_pin" value="<?php echo $b['is_pinned']; ?>">
                  <button type="submit" class="btn btn-sm" style="background: none; border: none; padding: 4px; cursor: pointer;" title="<?php echo $b['is_pinned'] ? 'Unpin from Member Dashboard' : 'Pin to top of Member Dashboard'; ?>">
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $b['is_pinned'] ? '#f59e0b' : 'rgba(255,255,255,0.2)'; ?>;"></span>
                  </button>
                </form>
              </td>

              <!-- Title & Date -->
              <td>
                <div style="display: flex; align-items: center; gap: 8px;">
                  <strong style="color: var(--text-primary); font-size: 13.5px;"><?php echo htmlspecialchars($b['title']); ?></strong>
                  <?php if ($b['is_pinned']): ?>
                    <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); font-size: 10px; padding: 2px 6px;">PINNED</span>
                  <?php endif; ?>
                </div>
                <div class="muted" style="font-size: 11.5px; margin-top: 3px;">
                  Posted: <?php echo htmlspecialchars($b['date_display'] ?: date('M d, Y', strtotime($b['date_posted']))); ?>
                  <?php if (!empty($b['author_name'])): ?>
                    &bull; By <?php echo htmlspecialchars($b['author_name']); ?>
                  <?php endif; ?>
                </div>
              </td>

              <!-- Category -->
              <td>
                <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-primary); font-size: 11px; border: 1px solid var(--border-color); padding: 3px 8px;">
                  <?php echo htmlspecialchars($b['category']); ?>
                </span>
              </td>

              <!-- Target Audience -->
              <td>
                <?php if ($b['target_audience'] === 'members_only'): ?>
                  <span class="badge-pill" style="background: rgba(245,158,11,0.12); color: #f59e0b; border: 1px solid rgba(245,158,11,0.25); font-size: 11px;">
                    Members Only
                  </span>
                <?php elseif ($b['target_audience'] === 'public'): ?>
                  <span class="badge-pill" style="background: rgba(16,185,129,0.12); color: #10b981; border: 1px solid rgba(16,185,129,0.25); font-size: 11px;">
                    Public Website
                  </span>
                <?php else: ?>
                  <span class="badge-pill" style="background: rgba(99,102,241,0.12); color: #818cf8; border: 1px solid rgba(99,102,241,0.25); font-size: 11px;">
                    All (Public &amp; Members)
                  </span>
                <?php endif; ?>
              </td>

              <!-- Attachments -->
              <td>
                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                  <?php if (!empty($b['image_path'])): ?>
                    <a href="<?php echo BASE_URL . '/' . htmlspecialchars($b['image_path']); ?>" target="_blank" class="badge" style="background: rgba(255,255,255,0.05); text-decoration: none; color: var(--text-secondary); font-size: 11px; padding: 2px 6px;">Poster</a>
                  <?php endif; ?>
                  <?php if (!empty($b['pdf_attachment'])): ?>
                    <a href="<?php echo BASE_URL . '/' . htmlspecialchars($b['pdf_attachment']); ?>" target="_blank" class="badge" style="background: rgba(239,68,68,0.15); color: #f87171; text-decoration: none; font-size: 11px; padding: 2px 6px; border: 1px solid rgba(239,68,68,0.3);">PDF Circular</a>
                  <?php endif; ?>
                  <?php if (empty($b['image_path']) && empty($b['pdf_attachment'])): ?>
                    <span class="muted" style="font-size: 11px;">None</span>
                  <?php endif; ?>
                </div>
              </td>

              <!-- Status -->
              <td>
                <form method="post" style="margin: 0;">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="toggle_publish">
                  <input type="hidden" name="bulletin_id" value="<?php echo $b['id']; ?>">
                  <input type="hidden" name="current_publish" value="<?php echo $b['is_published']; ?>">
                  <button type="submit" class="badge-pill badge-<?php echo $b['is_published'] ? 'paid' : 'unpaid'; ?>" style="border: none; cursor: pointer; font-size: 10.5px;">
                    <?php echo $b['is_published'] ? 'Published' : 'Draft'; ?>
                  </button>
                </form>
              </td>

              <!-- Actions -->
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 6px;">
                  <button type="button" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 4px 8px;" onclick='openEditModal(<?php echo json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                    Edit
                  </button>
                  <form method="post" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_bulletin">
                    <input type="hidden" name="bulletin_id" value="<?php echo $b['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline" style="font-size: 11px; padding: 4px 8px; color: #ef4444; border-color: rgba(239,68,68,0.3);">
                      Delete
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================== -->
<!-- CREATE / EDIT ANNOUNCEMENT MODAL -->
<!-- ============================================================== -->
<div id="bulletinModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
  <div class="card" style="max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto; background: var(--card-bg); border: 1px solid var(--border-color); padding: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
      <h2 id="modalTitle" style="font-size: 18px; margin: 0; color: var(--text-primary);">New Announcement</h2>
      <button type="button" class="btn btn-sm btn-secondary" onclick="closeBulletinModal()" style="font-size: 14px; padding: 2px 8px;">&times;</button>
    </div>

    <form method="post" enctype="multipart/form-data" id="bulletinForm" style="display: flex; flex-direction: column; gap: 16px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" id="formAction" value="create_bulletin">
      <input type="hidden" name="bulletin_id" id="formBulletinId" value="">

      <!-- Title -->
      <div>
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Announcement Title *</label>
        <input type="text" name="title" id="inputTitle" required placeholder="e.g. Chapter Board Resolution #03 Series of 2026" style="width: 100%;">
      </div>

      <!-- Target Audience & Category -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Target Audience *</label>
          <select name="target_audience" id="inputAudience" required style="width: 100%;">
            <option value="members_only">Members Only (Private to Portal)</option>
            <option value="public">Public Website</option>
            <option value="all">All (Public and Members)</option>
          </select>
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Category *</label>
          <select name="category" id="inputCategory" required style="width: 100%;">
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Date & Pinning -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; align-items: center;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Date Posted</label>
          <input type="date" name="date_posted" id="inputDate" value="<?php echo date('Y-m-d'); ?>" style="width: 100%;">
        </div>
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Dashboard Priority</label>
          <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; padding-top: 6px;">
            <input type="checkbox" name="is_pinned" id="inputIsPinned" value="1">
            <span>Pin to Top of Member Dashboard</span>
          </label>
        </div>
      </div>

      <!-- Summary -->
      <div>
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Short Summary / Excerpt</label>
        <textarea name="summary" id="inputSummary" rows="2" placeholder="Brief 1-2 sentence overview shown on bulletin cards..." style="width: 100%;"></textarea>
      </div>

      <!-- Full Content -->
      <div>
        <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Complete Content / Memorandum Text</label>
        <textarea name="content" id="inputContent" rows="6" placeholder="Full announcement details, resolution text, guidelines, agenda..." style="width: 100%; font-family: inherit; font-size: 13px; line-height: 1.5;"></textarea>
      </div>

      <!-- Attachments: Cover Image & PDF Circular -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; background: rgba(0,0,0,0.15); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color);">
        <div>
          <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Cover Poster / Image</label>
          <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" style="font-size: 12px; width: 100%;">
          <div id="coverImagePreview" style="margin-top: 6px; font-size: 11px;"></div>
        </div>
        <div>
          <label style="display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Official PDF Circular</label>
          <input type="file" name="pdf_circular" accept="application/pdf" style="font-size: 12px; width: 100%;">
          <div id="pdfCircularPreview" style="margin-top: 6px; font-size: 11px;"></div>
        </div>
      </div>

      <!-- Publish Status -->
      <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 8px;">
        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
          <input type="checkbox" name="is_published" id="inputIsPublished" value="1" checked>
          <span>Publish immediately</span>
        </label>
        <div style="display: flex; gap: 10px;">
          <button type="button" class="btn btn-secondary" onclick="closeBulletinModal()">Cancel</button>
          <button type="submit" class="btn btn-primary" id="modalSubmitBtn" style="font-weight: 700;">Publish Announcement</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('bulletinForm').reset();
    document.getElementById('formAction').value = 'create_bulletin';
    document.getElementById('formBulletinId').value = '';
    document.getElementById('modalTitle').textContent = 'New Announcement';
    document.getElementById('modalSubmitBtn').textContent = 'Publish Announcement';
    document.getElementById('coverImagePreview').innerHTML = '';
    document.getElementById('pdfCircularPreview').innerHTML = '';
    document.getElementById('inputDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('inputIsPublished').checked = true;
    document.getElementById('bulletinModal').style.display = 'flex';
}

function openEditModal(data) {
    document.getElementById('formAction').value = 'edit_bulletin';
    document.getElementById('formBulletinId').value = data.id;
    document.getElementById('modalTitle').textContent = 'Edit Announcement #' + data.id;
    document.getElementById('modalSubmitBtn').textContent = 'Save Changes';

    document.getElementById('inputTitle').value = data.title || '';
    document.getElementById('inputAudience').value = data.target_audience || 'members_only';
    document.getElementById('inputCategory').value = data.category || 'General Chapter Announcement';
    document.getElementById('inputDate').value = data.date_posted || (data.created_at ? data.created_at.split(' ')[0] : '');
    document.getElementById('inputIsPinned').checked = parseInt(data.is_pinned) === 1;
    document.getElementById('inputIsPublished').checked = parseInt(data.is_published) === 1;
    document.getElementById('inputSummary').value = data.summary || '';
    document.getElementById('inputContent').value = data.content || '';

    // Image preview
    const imgDiv = document.getElementById('coverImagePreview');
    if (data.image_path) {
        imgDiv.innerHTML = '<span class="muted">Current: ' + data.image_path + '</span><br><label style="font-size:11px;color:#ef4444;"><input type="checkbox" name="remove_cover_image" value="1"> Remove current poster</label>';
    } else {
        imgDiv.innerHTML = '';
    }

    // PDF preview
    const pdfDiv = document.getElementById('pdfCircularPreview');
    if (data.pdf_attachment) {
        pdfDiv.innerHTML = '<span class="muted">Current: ' + data.pdf_attachment + '</span><br><label style="font-size:11px;color:#ef4444;"><input type="checkbox" name="remove_pdf_circular" value="1"> Remove PDF attachment</label>';
    } else {
        pdfDiv.innerHTML = '';
    }

    document.getElementById('bulletinModal').style.display = 'flex';
}

function closeBulletinModal() {
    document.getElementById('bulletinModal').style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('bulletinModal');
    if (e.target === modal) {
        closeBulletinModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
