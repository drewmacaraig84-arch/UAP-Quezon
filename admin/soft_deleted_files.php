<?php
/**
 * UAP Quezon - Super Admin Soft-Deleted Files & Records (Trash Bin)
 */
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

$page_title = 'Trash Bin & Soft-Deleted Files • UAP Quezon';
$active_tab = 'soft_deleted_files';

$message = '';
$msg_type = 'success';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // File Actions
    if ($action === 'restore_file') {
        $fileId = (int)($_POST['file_id'] ?? 0);
        $res = restore_soft_deleted_file($fileId);
        $message = $res['message'];
        $msg_type = $res['success'] ? 'success' : 'danger';
    } elseif ($action === 'purge_file') {
        $fileId = (int)($_POST['file_id'] ?? 0);
        $res = permanently_purge_file($fileId);
        $message = $res['message'];
        $msg_type = $res['success'] ? 'success' : 'danger';
    } elseif ($action === 'empty_file_trash') {
        $files = $pdo->query("SELECT id FROM soft_deleted_files WHERE is_restored = 0")->fetchAll(PDO::FETCH_COLUMN);
        $purged = 0;
        foreach ($files as $fId) {
            $r = permanently_purge_file($fId);
            if ($r['success']) $purged++;
        }
        $message = "Permanently wiped $purged quarantined files from storage.";
    }

    // Record Actions
    elseif ($action === 'restore_record') {
        $table = $_POST['table'] ?? '';
        $recId = (int)($_POST['record_id'] ?? 0);
        if (restore_record($table, $recId)) {
            $message = "Record in $table (ID: $recId) successfully restored.";
        } else {
            $message = "Failed to restore record.";
            $msg_type = 'danger';
        }
    } elseif ($action === 'purge_record') {
        $table = $_POST['table'] ?? '';
        $recId = (int)($_POST['record_id'] ?? 0);
        if (permanently_purge_record($table, $recId)) {
            $message = "Record in $table (ID: $recId) permanently wiped.";
        } else {
            $message = "Failed to purge record.";
            $msg_type = 'danger';
        }
    }
}

// Current active view tab: 'files' or 'records'
$viewTab = $_GET['tab'] ?? 'files';

// Fetch Quarantined Files
$filesStmt = $pdo->query("
    SELECT sdf.*, u.name as deleted_by_name 
    FROM soft_deleted_files sdf
    LEFT JOIN users u ON sdf.deleted_by = u.id
    ORDER BY sdf.id DESC
");
$deletedFiles = $filesStmt->fetchAll();

// Calculate Trash Metrics
$activeTrashFilesCount = 0;
$activeTrashBytes = 0;
$restoredFilesCount = 0;

foreach ($deletedFiles as $df) {
    if (!$df['is_restored']) {
        $activeTrashFilesCount++;
        $activeTrashBytes += (int)$df['file_size'];
    } else {
        $restoredFilesCount++;
    }
}

// Fetch Soft-Deleted Records across tables
$deletedUsers = $pdo->query("SELECT id, name, id_number, role, email, deleted_at FROM users WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll();
$deletedDues = $pdo->query("SELECT id, title, amount, term, deleted_at FROM dues WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll();
$deletedPayments = $pdo->query("SELECT id, amount_paid, method, reference_number, deleted_at FROM payments WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll();
$deletedNews = $pdo->query("SELECT id, title, date_display, deleted_at FROM news_announcements WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll();
$deletedSponsors = $pdo->query("SELECT id, name, tier, deleted_at FROM sponsors WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll();

$totalDeletedRecords = count($deletedUsers) + count($deletedDues) + count($deletedPayments) + count($deletedNews) + count($deletedSponsors);

include __DIR__ . '/../includes/header.php';
?>

<div class="content-wrapper">
  <!-- TOP HEADER -->
  <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px;">
    <div>
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(229,138,19,0.15); color: #fbbf24; border: 1px solid rgba(229,138,19,0.3); padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;">
          <?php echo icon('archive', '', 13); ?> Quarantine &amp; Recovery Hub
        </span>
        <span style="font-size: 13px; color: var(--text-secondary);">Super Admin Data Protection</span>
      </div>
      <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.02em;">Soft-Deleted Files &amp; Records</h1>
      <p style="margin: 4px 0 0; color: var(--text-secondary); font-size: 14px;">Quarantine vault for safety. Recover accidentally deleted member photos, payment proofs, or database entries.</p>
    </div>

    <!-- Actions -->
    <div style="display: flex; gap: 10px; align-items: center;">
      <?php if ($activeTrashFilesCount > 0): ?>
        <form method="POST" style="margin: 0;" onsubmit="return confirm('WARNING: This will permanently delete all quarantined files from disk. Proceed?');">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="empty_file_trash">
          <button type="submit" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; border-color: rgba(239,68,68,0.4); color: #f87171;">
            <?php echo icon('trash', '', 14); ?> Empty File Trash (<?php echo $activeTrashFilesCount; ?>)
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type === 'danger' ? 'danger' : 'success'; ?>" style="margin-bottom: 20px; border-radius: 12px;">
      <?php echo icon($msg_type === 'danger' ? 'alert' : 'check', '', 16); ?>
      <span><?php echo htmlspecialchars($message); ?></span>
    </div>
  <?php endif; ?>

  <!-- METRIC SUMMARY CARDS -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Quarantined Files</span>
      <div style="font-size: 32px; font-weight: 800; color: #fbbf24; margin-top: 6px;">
        <?php echo $activeTrashFilesCount; ?>
      </div>
      <span style="font-size: 12px; color: var(--text-secondary);"><?php echo round($activeTrashBytes / 1024 / 1024, 2); ?> MB quarantined in disk</span>
    </div>

    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Soft-Deleted DB Records</span>
      <div style="font-size: 32px; font-weight: 800; color: #60a5fa; margin-top: 6px;">
        <?php echo $totalDeletedRecords; ?>
      </div>
      <span style="font-size: 12px; color: var(--text-secondary);">Restorable database entities</span>
    </div>

    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Restored Lifetime Items</span>
      <div style="font-size: 32px; font-weight: 800; color: #22c55e; margin-top: 6px;">
        <?php echo $restoredFilesCount; ?>
      </div>
      <span style="font-size: 12px; color: var(--text-secondary);">Successfully recovered files</span>
    </div>

    <div class="card" style="padding: 18px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
      <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary);">Quarantine Policy</span>
      <div style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-top: 14px;">
        Non-Destructive Safe
      </div>
      <span style="font-size: 12px; color: #22c55e;">One-click restoration ready</span>
    </div>
  </div>

  <!-- NAVIGATION TABS -->
  <div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--card-border); padding-bottom: 12px;">
    <a href="?tab=files" class="btn <?php echo $viewTab === 'files' ? 'btn-primary' : 'btn-secondary'; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('image', '', 14); ?> Quarantined Files (<?php echo $activeTrashFilesCount; ?>)
    </a>
    <a href="?tab=records" class="btn <?php echo $viewTab === 'records' ? 'btn-primary' : 'btn-secondary'; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('database', '', 14); ?> Soft-Deleted Records (<?php echo $totalDeletedRecords; ?>)
    </a>
  </div>

  <!-- TAB 1: QUARANTINED FILES -->
  <?php if ($viewTab === 'files'): ?>
    <?php if (empty($deletedFiles)): ?>
      <div class="card" style="padding: 48px 24px; text-align: center; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
        <div style="width: 54px; height: 54px; border-radius: 50%; background: rgba(34,197,94,0.15); color: #22c55e; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px;">
          <?php echo icon('check', '', 28); ?>
        </div>
        <h3 style="margin: 0; font-size: 18px; font-weight: 700;">Quarantine Trash Bin is Clean!</h3>
        <p style="margin: 6px auto 0; max-width: 440px; color: var(--text-secondary); font-size: 14px;">
          No files have been soft-deleted. When admins or members delete photos or proofs, they will safely appear here for recovery.
        </p>
      </div>
    <?php else: ?>
      <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
        <div style="overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
              <tr style="border-bottom: 2px solid var(--card-border); background: var(--bg-secondary);">
                <th style="padding: 12px 14px; text-align: left;">File Name / Preview</th>
                <th style="padding: 12px 14px; text-align: left;">Original Location</th>
                <th style="padding: 12px 14px; text-align: left;">Source &amp; Reason</th>
                <th style="padding: 12px 14px; text-align: right;">Size</th>
                <th style="padding: 12px 14px; text-align: left;">Deleted By / Date</th>
                <th style="padding: 12px 14px; text-align: center;">Status</th>
                <th style="padding: 12px 14px; text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($deletedFiles as $file): ?>
                <?php
                  $isImage = str_starts_with($file['file_type'] ?? '', 'image/');
                  $previewUrl = BASE_URL . '/' . ltrim($file['quarantine_path'], '/');
                ?>
                <tr style="border-bottom: 1px solid var(--card-border);">
                  <td style="padding: 12px 14px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                      <?php if ($isImage && file_exists(dirname(__DIR__) . '/' . $file['quarantine_path'])): ?>
                        <img src="<?php echo $previewUrl; ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--card-border);" alt="Thumb">
                      <?php else: ?>
                        <div style="width: 40px; height: 40px; border-radius: 6px; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; color: var(--text-secondary);">
                          <?php echo icon('file', '', 18); ?>
                        </div>
                      <?php endif; ?>
                      <div>
                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($file['file_name']); ?></div>
                        <div style="font-size: 11px; color: var(--text-secondary); font-family: monospace;"><?php echo htmlspecialchars($file['file_type']); ?></div>
                      </div>
                    </div>
                  </td>
                  <td style="padding: 12px 14px; font-family: monospace; font-size: 12px; color: var(--text-secondary);">
                    <?php echo htmlspecialchars($file['original_path']); ?>
                  </td>
                  <td style="padding: 12px 14px;">
                    <div style="font-weight: 500;"><?php echo htmlspecialchars($file['source_table'] ?: 'Direct File'); ?><?php echo $file['source_record_id'] ? ' #' . $file['source_record_id'] : ''; ?></div>
                    <?php if ($file['reason']): ?>
                      <div style="font-size: 11px; color: var(--text-secondary);"><em><?php echo htmlspecialchars($file['reason']); ?></em></div>
                    <?php endif; ?>
                  </td>
                  <td style="padding: 12px 14px; text-align: right; font-weight: 600;">
                    <?php echo round($file['file_size'] / 1024, 1); ?> KB
                  </td>
                  <td style="padding: 12px 14px;">
                    <div><?php echo htmlspecialchars($file['deleted_by_name'] ?: 'System'); ?></div>
                    <div style="font-size: 11px; color: var(--text-secondary);"><?php echo date('M d, Y h:i A', strtotime($file['deleted_at'])); ?></div>
                  </td>
                  <td style="padding: 12px 14px; text-align: center;">
                    <?php if ($file['is_restored']): ?>
                      <span style="display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background: rgba(34,197,94,0.15); color: #22c55e;">
                        Restored
                      </span>
                    <?php else: ?>
                      <span style="display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background: rgba(245,158,11,0.15); color: #fbbf24;">
                        Quarantined
                      </span>
                    <?php endif; ?>
                  </td>
                  <td style="padding: 12px 14px; text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                      <?php if (!$file['is_restored']): ?>
                        <form method="POST" style="margin: 0;">
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="action" value="restore_file">
                          <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                          <button type="submit" class="btn btn-sm btn-primary" style="background: #15803d; border-color: #15803d; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                            <?php echo icon('refresh_cw', '', 12); ?> Restore
                          </button>
                        </form>
                      <?php endif; ?>
                      <form method="POST" style="margin: 0;" onsubmit="return confirm('Permanently wipe this file from storage? This cannot be undone.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="purge_file">
                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 11px; color: #ef4444; border-color: rgba(239,68,68,0.3);">
                          <?php echo icon('trash', '', 12); ?> Purge
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  <!-- TAB 2: SOFT-DELETED DATABASE RECORDS -->
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 20px;">
      <!-- USERS -->
      <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
        <h3 style="margin: 0 0 14px; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
          <?php echo icon('members', '', 16); ?> Soft-Deleted User Accounts (<?php echo count($deletedUsers); ?>)
        </h3>
        <?php if (empty($deletedUsers)): ?>
          <p style="margin: 0; color: var(--text-secondary); font-size: 13px;">No deleted user accounts in trash.</p>
        <?php else: ?>
          <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
              <tr style="border-bottom: 2px solid var(--card-border); background: var(--bg-secondary);">
                <th style="padding: 10px; text-align: left;">Name</th>
                <th style="padding: 10px; text-align: left;">ID / PRC</th>
                <th style="padding: 10px; text-align: left;">Role</th>
                <th style="padding: 10px; text-align: left;">Deleted Date</th>
                <th style="padding: 10px; text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($deletedUsers as $u): ?>
                <tr style="border-bottom: 1px solid var(--card-border);">
                  <td style="padding: 10px; font-weight: 600;"><?php echo htmlspecialchars($u['name']); ?></td>
                  <td style="padding: 10px; font-family: monospace;"><?php echo htmlspecialchars($u['id_number']); ?></td>
                  <td style="padding: 10px; text-transform: uppercase; font-size: 11px;"><?php echo htmlspecialchars($u['role']); ?></td>
                  <td style="padding: 10px; color: var(--text-secondary);"><?php echo date('M d, Y h:i A', strtotime($u['deleted_at'])); ?></td>
                  <td style="padding: 10px; text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                      <form method="POST" style="margin: 0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="restore_record">
                        <input type="hidden" name="table" value="users">
                        <input type="hidden" name="record_id" value="<?php echo $u['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-primary" style="background: #15803d; border-color: #15803d; font-size: 11px;">Restore</button>
                      </form>
                      <form method="POST" style="margin: 0;" onsubmit="return confirm('Permanently wipe this user record from database?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="purge_record">
                        <input type="hidden" name="table" value="users">
                        <input type="hidden" name="record_id" value="<?php echo $u['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 11px; color: #ef4444;">Purge</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- DUES PACKAGES -->
      <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
        <h3 style="margin: 0 0 14px; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
          <?php echo icon('dues', '', 16); ?> Soft-Deleted Dues Packages (<?php echo count($deletedDues); ?>)
        </h3>
        <?php if (empty($deletedDues)): ?>
          <p style="margin: 0; color: var(--text-secondary); font-size: 13px;">No dues packages in trash.</p>
        <?php else: ?>
          <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
              <tr style="border-bottom: 2px solid var(--card-border); background: var(--bg-secondary);">
                <th style="padding: 10px; text-align: left;">Title</th>
                <th style="padding: 10px; text-align: right;">Amount</th>
                <th style="padding: 10px; text-align: left;">Term</th>
                <th style="padding: 10px; text-align: left;">Deleted Date</th>
                <th style="padding: 10px; text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($deletedDues as $d): ?>
                <tr style="border-bottom: 1px solid var(--card-border);">
                  <td style="padding: 10px; font-weight: 600;"><?php echo htmlspecialchars($d['title']); ?></td>
                  <td style="padding: 10px; text-align: right; font-weight: 600;">₱<?php echo number_format($d['amount'], 2); ?></td>
                  <td style="padding: 10px;"><?php echo htmlspecialchars($d['term'] ?: '—'); ?></td>
                  <td style="padding: 10px; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($d['deleted_at'])); ?></td>
                  <td style="padding: 10px; text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                      <form method="POST" style="margin: 0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="restore_record">
                        <input type="hidden" name="table" value="dues">
                        <input type="hidden" name="record_id" value="<?php echo $d['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-primary" style="background: #15803d; border-color: #15803d; font-size: 11px;">Restore</button>
                      </form>
                      <form method="POST" style="margin: 0;" onsubmit="return confirm('Permanently wipe this due package?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="purge_record">
                        <input type="hidden" name="table" value="dues">
                        <input type="hidden" name="record_id" value="<?php echo $d['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 11px; color: #ef4444;">Purge</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- NEWS & ANNOUNCEMENTS -->
      <div class="card" style="padding: 20px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px;">
        <h3 style="margin: 0 0 14px; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
          <?php echo icon('newspaper', '', 16); ?> Soft-Deleted News &amp; Announcements (<?php echo count($deletedNews); ?>)
        </h3>
        <?php if (empty($deletedNews)): ?>
          <p style="margin: 0; color: var(--text-secondary); font-size: 13px;">No news items in trash.</p>
        <?php else: ?>
          <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
              <tr style="border-bottom: 2px solid var(--card-border); background: var(--bg-secondary);">
                <th style="padding: 10px; text-align: left;">Title</th>
                <th style="padding: 10px; text-align: left;">Display Date</th>
                <th style="padding: 10px; text-align: left;">Deleted Date</th>
                <th style="padding: 10px; text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($deletedNews as $n): ?>
                <tr style="border-bottom: 1px solid var(--card-border);">
                  <td style="padding: 10px; font-weight: 600;"><?php echo htmlspecialchars($n['title']); ?></td>
                  <td style="padding: 10px;"><?php echo htmlspecialchars($n['date_display'] ?: '—'); ?></td>
                  <td style="padding: 10px; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($n['deleted_at'])); ?></td>
                  <td style="padding: 10px; text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                      <form method="POST" style="margin: 0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="restore_record">
                        <input type="hidden" name="table" value="news_announcements">
                        <input type="hidden" name="record_id" value="<?php echo $n['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-primary" style="background: #15803d; border-color: #15803d; font-size: 11px;">Restore</button>
                      </form>
                      <form method="POST" style="margin: 0;" onsubmit="return confirm('Permanently wipe this announcement?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="purge_record">
                        <input type="hidden" name="table" value="news_announcements">
                        <input type="hidden" name="record_id" value="<?php echo $n['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 11px; color: #ef4444;">Purge</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

