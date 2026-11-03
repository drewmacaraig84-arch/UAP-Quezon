<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulletin_helper.php';
require_member();

$currentUid = current_user_id();

// Bulletins & Pinned Notice
$pinnedNotice = get_pinned_bulletin($pdo);
$recentBulletins = get_member_bulletins($pdo, 3);

// Load member full profile & demographic details
$mUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$mUserStmt->execute([$currentUid]);
$memberUser = $mUserStmt->fetch();

$wmStmt = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ?");
$wmStmt->execute([$currentUid]);
$wmRecord = $wmStmt->fetch();

$mPhoto = ($memberUser['profile_photo'] ?? null) ?: ($wmRecord['photo_path'] ?? null);
$mAvatarUrl = $mPhoto ? BASE_URL . '/' . ltrim($mPhoto, '/') : null;
$mInitials = strtoupper(substr($memberUser['name'], 0, 1) . substr(strrchr($memberUser['name'], ' ') ?: $memberUser['name'], 1, 1));

// Calculate age dynamically
$mBirthDate = $memberUser['birth_date'] ?? null;
$mAge = null;
if (!empty($mBirthDate)) {
    try {
        $dobObj = new DateTime($mBirthDate);
        $todayObj = new DateTime();
        $mAge = $dobObj->diff($todayObj)->y;
    } catch (Exception $e) {
        $mAge = null;
    }
}

// Attendance & Chapter Activity Status (Active >= 7, Inactive <= 2)
$attendance = get_or_create_member_attendance($pdo, $currentUid);
$cActivity = $attendance['computed'];

// Fetch upcoming chapter meetings
$upcomingEventsStmt = $pdo->prepare("
    SELECT ce.*, 
           (SELECT scanned_at FROM event_attendance_logs WHERE user_id = ? AND event_key = CONCAT('ce_', ce.id) LIMIT 1) as attended_at
    FROM chapter_events ce
    WHERE ce.deleted_at IS NULL 
      AND ce.is_published = 1 
      AND ce.status != 'completed' 
      AND ce.status != 'cancelled'
      AND ce.event_date >= CURDATE()
    ORDER BY ce.event_date ASC, ce.start_time ASC
    LIMIT 2
");
$upcomingEventsStmt->execute([$currentUid]);
$upcomingChapterEvents = $upcomingEventsStmt->fetchAll();

// Fetch member dues
$stmt = $pdo->prepare("
    SELECT md.id as member_due_id, md.status, md.payment_type, md.installment_months,
           md.total_paid, md.discount_amount, md.other_obligations, md.due_period,
           COALESCE(md.custom_title, d.title) as title,
           COALESCE(md.custom_description, d.description) as description,
           COALESCE(md.custom_amount, d.amount) as amount,
           COALESCE(md.custom_due_date, d.due_date) as due_date,
           COALESCE(md.custom_term, d.term) as term
    FROM member_dues md
    JOIN dues d ON md.due_id = d.id
    WHERE md.user_id = ?
    ORDER BY COALESCE(md.custom_due_date, d.due_date) ASC
");
$stmt->execute([$currentUid]);
$dues = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dismiss_directory_rejection') {
    require_csrf();
    $pdo->prepare("UPDATE directory_applications SET dismissed_notification = 1 WHERE user_id = ?")->execute([$currentUid]);
    header("Location: dashboard.php");
    exit;
}

// Check for active directory rejection notification
$dirAppStmt = $pdo->prepare("SELECT * FROM directory_applications WHERE user_id = ? AND status = 'rejected' AND dismissed_notification = 0 LIMIT 1");
$dirAppStmt->execute([$currentUid]);
$rejectedDirApp = $dirAppStmt->fetch();

$page_title = 'Member Dashboard • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-hero">
  <div>
    <p class="eyebrow">OFFICIAL CHAPTER PORTAL</p>
    <h1>Architect Member Dashboard</h1>
    <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($memberUser['name']); ?>. View your certified credentials and financial standing.</p>
  </div>
  <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
    <a href="attendance_pass.php" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 7px 14px; font-weight: 800; text-decoration: none; box-shadow: 0 4px 12px rgba(245,158,11,0.25);">
      <?php echo icon('qr_codes', '', 14); ?> <span>Digital Pass (QR)</span>
    </a>
    <a href="chapter_status.php" style="text-decoration: none;">
      <?php echo render_activity_badge($attendance); ?>
    </a>
    <div class="hero-badge">
      <?php
        $standing = function_exists('get_member_standing_details') ? get_member_standing_details($pdo, $currentUid) : ['is_good' => true, 'is_revoked' => false];
        if ($standing['is_revoked']) {
            echo icon('alert', '', 14) . ' <span style="color: #ef4444;">Standing Revoked</span>';
        } elseif ($standing['is_good']) {
            echo icon('good_members', '', 14) . ' <span>Good Standing</span>';
        } else {
            echo icon('clock', '', 14) . ' <span>Pending Settlement</span>';
        }
      ?>
    </div>
  </div>
</div>

<?php if ($pinnedNotice): ?>
  <div class="card" style="margin-top: 14px; margin-bottom: 20px; padding: 18px 22px; border: 1px solid rgba(245,158,11,0.4); background: linear-gradient(135deg, rgba(245,158,11,0.08) 0%, rgba(21,128,61,0.05) 100%); border-left: 5px solid #f59e0b; border-radius: 8px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap;">
      <div style="flex: 1; min-width: 240px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
          <span class="badge" style="background: rgba(245,158,11,0.2); color: #f59e0b; border: 1px solid rgba(245,158,11,0.35); font-size: 10px; font-weight: 800; padding: 2px 8px;">PRIORITY NOTICE</span>
          <span class="badge" style="background: rgba(255,255,255,0.05); color: var(--text-secondary); font-size: 11px;"><?php echo htmlspecialchars($pinnedNotice['category']); ?></span>
          <span class="muted" style="font-size: 11.5px;"><?php echo htmlspecialchars($pinnedNotice['date_display'] ?: date('M d, Y', strtotime($pinnedNotice['date_posted']))); ?></span>
        </div>
        <h3 style="font-size: 16px; font-weight: 800; color: var(--text-primary); margin: 0 0 6px 0;">
          <?php echo htmlspecialchars($pinnedNotice['title']); ?>
        </h3>
        <?php if (!empty($pinnedNotice['summary'])): ?>
          <p style="margin: 0; font-size: 13px; color: var(--text-secondary); line-height: 1.5;">
            <?php echo htmlspecialchars($pinnedNotice['summary']); ?>
          </p>
        <?php endif; ?>
      </div>
      <div style="display: flex; gap: 8px; align-items: center;">
        <button type="button" class="btn btn-sm btn-primary" onclick='openReaderModal(<?php echo json_encode($pinnedNotice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="font-size: 11.5px; padding: 6px 14px; font-weight: 700;">
          Read Memorandum
        </button>
        <?php if (!empty($pinnedNotice['pdf_attachment'])): ?>
          <a href="<?php echo BASE_URL . '/' . htmlspecialchars($pinnedNotice['pdf_attachment']); ?>" target="_blank" class="btn btn-sm btn-secondary" style="font-size: 11.5px; padding: 6px 12px;">
            Download PDF
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($rejectedDirApp): ?>
  <?php
    $canReapply = ((int)$rejectedDirApp['reapply_allowed'] === 1) && (empty($rejectedDirApp['reapply_after']) || strtotime($rejectedDirApp['reapply_after']) <= strtotime(date('Y-m-d')));
    $isLocked = !(int)$rejectedDirApp['reapply_allowed'];
    $reapplyDate = $rejectedDirApp['reapply_after'];
  ?>
  <div class="alert" style="margin-top: 12px; background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.25); border-left: 4px solid #ef4444; border-radius: 8px; padding: 14px 16px; display: flex; align-items: flex-start; justify-content: space-between; gap: 14px;">
    <div style="display: flex; align-items: flex-start; gap: 12px; flex: 1;">
      <div style="color: #ef4444; margin-top: 2px; flex-shrink: 0;"><?php echo icon('alert', '', 20); ?></div>
      <div style="flex: 1;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
          <strong style="font-size: 14px; color: #ef4444;">Website Directory Application Update</strong>
          <?php if (!empty($rejectedDirApp['rejected_at'])): ?>
            <span class="muted" style="font-size: 11.5px;"><?php echo date('M d, Y', strtotime($rejectedDirApp['rejected_at'])); ?></span>
          <?php endif; ?>
        </div>
        <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-secondary); line-height: 1.5;">
          Your application for the Public Architect Website Directory was reviewed and declined by Chapter Administration.
        </p>
        <?php if (!empty($rejectedDirApp['notes'])): ?>
          <div style="margin-top: 8px; padding: 8px 12px; background: rgba(0,0,0,0.15); border-radius: 6px; border-left: 3px solid #ef4444;">
            <strong style="font-size: 11.5px; color: #ef4444; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Admin Remarks:</strong>
            <div style="font-size: 13px; color: var(--text-primary);"><?php echo nl2br(htmlspecialchars($rejectedDirApp['notes'])); ?></div>
          </div>
        <?php endif; ?>
        <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
          <?php if ($isLocked): ?>
            <span class="muted" style="font-size: 12px;">Re-application is currently restricted. Please consult the chapter secretariat.</span>
          <?php elseif (!empty($reapplyDate) && strtotime($reapplyDate) > strtotime(date('Y-m-d'))): ?>
            <span class="muted" style="font-size: 12px;">You will be eligible to submit a new application on or after <strong><?php echo date('F d, Y', strtotime($reapplyDate)); ?></strong>.</span>
          <?php else: ?>
            <a href="website_directory.php" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 5px; font-size: 12px; padding: 5px 12px;">
              <?php echo icon('sparkles', '', 12); ?> <span>Re-Apply for Directory</span>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <form method="post" style="margin: 0; flex-shrink: 0;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="dismiss_directory_rejection">
      <button type="submit" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 4px 8px;" title="Dismiss notification">
        <?php echo icon('x', '', 12); ?> <span>Dismiss</span>
      </button>
    </form>
  </div>
<?php endif; ?>

<!-- ============================================================== -->
<!-- 1ST SECTION: MEMBER'S DEMOGRAPHIC & CREDENTIALS DASHBOARD CARD -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 24px; padding: 24px; border: 1px solid var(--border-color); background: var(--card-bg);">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.15); color: #f59e0b; display: inline-flex; align-items: center; justify-content: center;">
        <?php echo icon('user', '', 20); ?>
      </div>
      <div>
        <h2 style="font-size: 17px; font-weight: 700; color: var(--text-primary); margin: 0;">Member Profile &amp; Demographics</h2>
        <span class="muted" style="font-size: 12px;">Certified chapter member registration records</span>
      </div>
    </div>
    <div style="display: flex; gap: 8px;">
      <a href="attendance_pass.php" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 6px 14px; font-weight: 800;">
        <?php echo icon('qr_codes', '', 13); ?> <span>My Digital Pass</span>
      </a>
      <a href="chapter_status.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 6px 14px;">
        <?php echo icon('shield_check', '', 13); ?> <span>Attendance Record</span>
      </a>
      <a href="profile.php" class="btn btn-sm btn-outline" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 6px 14px;">
        <?php echo icon('edit', '', 13); ?> <span>Update Profile</span>
      </a>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: 160px 1fr; gap: 24px; align-items: start;" class="member-hero-grid">
    <!-- 1x1 Photo Frame -->
    <div style="text-align: center;">
      <div style="width: 140px; height: 140px; margin: 0 auto; border-radius: 12px; overflow: hidden; border: 3px solid var(--accent-primary, #f59e0b); background: #0f172a; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.25);">
        <?php if ($mAvatarUrl): ?>
          <img src="<?php echo htmlspecialchars($mAvatarUrl); ?>" alt="<?php echo htmlspecialchars($memberUser['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
        <?php else: ?>
          <div style="font-size: 40px; font-weight: 800; color: #f59e0b;">
            <?php echo htmlspecialchars($mInitials); ?>
          </div>
        <?php endif; ?>
      </div>
      <span class="badge" style="display: inline-block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); padding: 3px 8px; border-radius: 4px; margin-top: 8px;">1x1 Official Photo</span>
    </div>

    <!-- Complete Member Details Grid -->
    <div>
      <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px;">
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin: 0;">
          <?php echo htmlspecialchars($memberUser['name']); ?>
        </h3>
        <span class="badge-pill badge-paid" style="font-size: 11px;">
          PRC #<?php echo htmlspecialchars($memberUser['prc_number'] ?: $memberUser['id_number']); ?>
        </span>
        <a href="chapter_status.php" style="text-decoration: none;">
          <?php echo render_activity_badge($attendance, true); ?>
        </a>
      </div>

      <!-- 2-Column Demographic Grid -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px 20px; font-size: 13px;">
        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 6px;">
          <span class="muted">PRC License #:</span>
          <strong style="color: var(--accent-primary); font-family: monospace; font-size: 13.5px;">
            <?php echo htmlspecialchars($memberUser['prc_number'] ?: $memberUser['id_number']); ?>
          </strong>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 6px;">
          <span class="muted">Gender:</span>
          <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($memberUser['gender'] ?: '—'); ?></strong>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 6px;">
          <span class="muted">Age:</span>
          <strong style="color: var(--text-primary);">
            <?php echo $mAge !== null ? "$mAge years old" : '—'; ?>
          </strong>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 6px;">
          <span class="muted">Birth Date:</span>
          <strong style="color: var(--text-primary);">
            <?php echo !empty($memberUser['birth_date']) ? date('F d, Y', strtotime($memberUser['birth_date'])) : '—'; ?>
          </strong>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 6px;">
          <span class="muted">Mobile #:</span>
          <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($memberUser['phone'] ?: '—'); ?></strong>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 6px;">
          <span class="muted">Civil Status:</span>
          <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($memberUser['civil_status'] ?: '—'); ?></strong>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 6px;">
          <span class="muted">Email Address:</span>
          <strong style="color: var(--text-primary); word-break: break-all;"><?php echo htmlspecialchars($memberUser['email'] ?: '—'); ?></strong>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 6px;">
          <span class="muted">Chapter Standing:</span>
          <a href="chapter_status.php" style="text-decoration:none;" title="View Board Resolution Compliance Breakdown">
            <?php echo render_activity_badge($attendance); ?>
          </a>
        </div>
      </div>

      <div style="margin-top: 10px; padding: 10px 14px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid rgba(255,255,255,0.06); font-size: 12.5px;">
        <span class="muted" style="display: block; font-size: 11px; margin-bottom: 2px;">Registered Address:</span>
        <strong style="color: var(--text-primary); line-height: 1.4;"><?php echo htmlspecialchars($memberUser['address'] ?: 'No physical address specified.'); ?></strong>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- CHAPTER BULLETINS & ANNOUNCEMENTS WIDGET                       -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 24px; padding: 22px; border: 1px solid var(--border-color); background: var(--card-bg);">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.15); color: #f59e0b; display: inline-flex; align-items: center; justify-content: center;">
        <?php echo icon('newspaper', '', 20); ?>
      </div>
      <div>
        <h2 style="font-size: 17px; font-weight: 700; color: var(--text-primary); margin: 0;">Chapter Bulletins &amp; Advisories</h2>
        <span class="muted" style="font-size: 12px;">Official memorandums, board resolutions, and administrative advisories</span>
      </div>
    </div>
    <div style="display: flex; gap: 8px; align-items: center;">
      <a href="announcements.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 6px 12px;">
        <span>View All Bulletins</span> <?php echo icon('arrow_right', '', 12); ?>
      </a>
    </div>
  </div>

  <?php if (empty($recentBulletins)): ?>
    <div style="text-align: center; padding: 24px 16px; background: rgba(255,255,255,0.015); border-radius: 8px; border: 1px dashed var(--border-color);">
      <div style="color: var(--text-secondary); margin-bottom: 6px;"><?php echo icon('newspaper', '', 28); ?></div>
      <div style="font-size: 13.5px; font-weight: 600; color: var(--text-primary);">No Active Bulletins</div>
      <p class="muted" style="font-size: 12px; margin: 4px 0 0 0;">There are currently no new circulars or memorandums posted by chapter administration.</p>
    </div>
  <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
      <?php foreach ($recentBulletins as $b): ?>
        <div class="card" style="padding: 16px; border: 1px solid var(--border-color); background: rgba(255,255,255,0.02); display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 6px; margin-bottom: 8px;">
              <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-primary); font-size: 10px; padding: 2px 6px; border: 1px solid var(--border-color);">
                <?php echo htmlspecialchars($b['category']); ?>
              </span>
              <?php if ($b['is_pinned']): ?>
                <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; font-size: 9.5px; padding: 2px 5px; font-weight: 700;">PINNED</span>
              <?php endif; ?>
            </div>
            <h3 style="font-size: 14.5px; font-weight: 700; color: var(--text-primary); margin: 0 0 6px 0; line-height: 1.4;">
              <?php echo htmlspecialchars($b['title']); ?>
            </h3>
            <div class="muted" style="font-size: 11px; margin-bottom: 10px;">
              <?php echo htmlspecialchars($b['date_display'] ?: date('M d, Y', strtotime($b['date_posted']))); ?>
            </div>
            <p style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.4; margin: 0 0 12px 0;">
              <?php echo htmlspecialchars($b['summary'] ?: substr(strip_tags($b['content'] ?? ''), 0, 110) . '...'); ?>
            </p>
          </div>
          <div style="border-top: 1px solid var(--border-color); padding-top: 10px; display: flex; justify-content: space-between; align-items: center;">
            <div>
              <?php if (!empty($b['pdf_attachment'])): ?>
                <span class="badge" style="background: rgba(239,68,68,0.12); color: #f87171; font-size: 9.5px; padding: 2px 5px;">PDF Attached</span>
              <?php endif; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline" onclick='openReaderModal(<?php echo json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' style="font-size: 11px; padding: 4px 10px;">
              Read &rarr;
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================== -->
<!-- UPCOMING CHAPTER MEETINGS & ASSEMBLIES WIDGET                  -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 24px; padding: 22px; border: 1px solid var(--border-color); background: var(--card-bg);">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
    <div style="display: flex; align-items: center; gap: 10px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59,130,246,0.15); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center;">
        <?php echo icon('calendar', '', 20); ?>
      </div>
      <div>
        <h2 style="font-size: 17px; font-weight: 700; color: var(--text-primary); margin: 0;">Upcoming Chapter Meetings &amp; Assemblies</h2>
        <span class="muted" style="font-size: 12px;">Next scheduled general membership meetings, board sessions, and CPD events</span>
      </div>
    </div>
    <div style="display: flex; gap: 8px; align-items: center;">
      <a href="attendance_pass.php" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 6px 12px; font-weight: 700;">
        <?php echo icon('qr_codes', '', 12); ?> <span>My QR Pass</span>
      </a>
      <a href="events.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 6px 12px;">
        <span>View All Meetings</span> <?php echo icon('arrow_right', '', 12); ?>
      </a>
    </div>
  </div>

  <?php if (empty($upcomingChapterEvents)): ?>
    <div style="text-align: center; padding: 28px 16px; background: rgba(255,255,255,0.015); border-radius: 8px; border: 1px dashed var(--border-color);">
      <div style="color: var(--text-secondary); margin-bottom: 8px;"><?php echo icon('calendar', '', 32); ?></div>
      <div style="font-size: 14px; font-weight: 600; color: var(--text-primary);">No Upcoming Meetings Scheduled</div>
      <p class="muted" style="font-size: 12px; margin: 4px 0 0 0;">Check back soon or consult chapter announcements for new schedules.</p>
    </div>
  <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
      <?php foreach ($upcomingChapterEvents as $ev): ?>
        <?php
          $eTypeLabels = [
            'gmm' => 'General Membership Meeting (GMM)',
            'board' => 'Chapter Board Meeting',
            'cpd' => 'CPD Technical Seminar',
            'fellowship' => 'Fellowship & Sports',
            'assembly' => 'National / Area Assembly',
            'other' => 'Chapter Activity'
          ];
          $typeLabel = $eTypeLabels[$ev['event_type']] ?? ucfirst($ev['event_type']);
          $formattedDate = date('D, M d, Y', strtotime($ev['event_date']));
          $timeRange = date('h:i A', strtotime($ev['start_time'])) . ' – ' . date('h:i A', strtotime($ev['end_time']));
        ?>
        <div style="border: 1px solid var(--border-color); border-radius: 10px; padding: 16px; background: rgba(255,255,255,0.02); display: flex; flex-direction: column; justify-content: space-between; gap: 12px;">
          <div>
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
              <span class="badge" style="font-size: 10.5px; background: rgba(59,130,246,0.12); color: #60a5fa; font-weight: 700;"><?php echo htmlspecialchars($typeLabel); ?></span>
              <?php if ((float)$ev['cpd_units'] > 0): ?>
                <span class="badge" style="font-size: 10.5px; background: rgba(245,158,11,0.15); color: #fbbf24; font-weight: 800;"><?php echo (float)$ev['cpd_units']; ?> CPD Units</span>
              <?php endif; ?>
            </div>

            <h3 style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin: 0 0 8px 0; line-height: 1.35;">
              <?php echo htmlspecialchars($ev['title']); ?>
            </h3>

            <div style="display: flex; flex-direction: column; gap: 4px; font-size: 12.5px; color: var(--text-secondary);">
              <div style="display: flex; align-items: center; gap: 6px;">
                <span style="color: #3b82f6;"><?php echo icon('calendar', '', 14); ?></span>
                <strong style="color: var(--text-primary);"><?php echo $formattedDate; ?></strong>
                <span class="muted">•</span>
                <span><?php echo $timeRange; ?></span>
              </div>
              <div style="display: flex; align-items: center; gap: 6px;">
                <span style="color: #10b981;"><?php echo icon('map_pin', '', 14); ?></span>
                <span><?php echo htmlspecialchars($ev['venue_name'] ?: 'To be announced'); ?></span>
                <?php 
                  $locType = $ev['location_type'] ?? ($ev['format'] ?? 'f2f');
                  if ($locType === 'hybrid'): 
                ?>
                  <span class="badge" style="font-size: 9.5px; background: rgba(168,85,247,0.15); color: #c084fc;">Hybrid</span>
                <?php elseif ($locType === 'zoom' || $locType === 'online'): ?>
                  <span class="badge" style="font-size: 9.5px; background: rgba(59,130,246,0.15); color: #60a5fa;">Online (Zoom)</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 10px; flex-wrap: wrap;">
            <?php if (!empty($ev['attended_at'])): ?>
              <span class="badge-pill badge-paid" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo icon('check', '', 12); ?> <strong>Checked In</strong> (<?php echo date('h:i A', strtotime($ev['attended_at'])); ?>)
              </span>
            <?php else: ?>
              <span class="badge-pill badge-pending" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo icon('clock', '', 12); ?> Not Yet Attended
              </span>
            <?php endif; ?>

            <div style="display: flex; gap: 6px; align-items: center;">
              <?php 
                $meetingLink = !empty($ev['meeting_link']) ? $ev['meeting_link'] : ($ev['zoom_link'] ?? '');
                if (!empty($meetingLink)): 
              ?>
                <?php if (empty($ev['attended_at'])): ?>
                  <a href="join_meeting.php?id=<?php echo (int)$ev['id']; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm" style="font-size: 11px; padding: 5px 10px; display: inline-flex; align-items: center; gap: 5px; background: #2563eb; color: #ffffff; border: none; font-weight: 700; white-space: nowrap; box-shadow: 0 2px 8px rgba(37,99,235,0.35);">
                    <?php echo icon('video', '', 12); ?> Join Zoom &amp; Check In
                  </a>
                <?php else: ?>
                  <a href="join_meeting.php?id=<?php echo (int)$ev['id']; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline" style="font-size: 11px; padding: 4px 8px; display: inline-flex; align-items: center; gap: 4px; border-color: #3b82f6; color: #3b82f6; white-space: nowrap;">
                    <?php echo icon('video', '', 11); ?> Launch Zoom &rarr;
                  </a>
                <?php endif; ?>
              <?php endif; ?>
              <a href="attendance_pass.php" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 4px 8px; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo icon('qr_codes', '', 11); ?> QR Pass
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================== -->
<!-- 2ND SECTION: MY CHAPTER DUES AND OBLIGATIONS & ACCOUNT SUMMARY -->
<!-- ============================================================== -->
<?php
  $total_amount = 0;
  $total_paid = 0;
  $total_discounts = 0;
  $total_other = 0;
  $remaining_total = 0;
  foreach ($dues as $d) {
      $bDue = round((float)$d['amount'], 2);
      $bDisc = round((float)($d['discount_amount'] ?? 0), 2);
      $bOth = round((float)($d['other_obligations'] ?? 0), 2);
      $bPaid = round((float)$d['total_paid'], 2);
      $total_amount += $bDue;
      $total_discounts += $bDisc;
      $total_other += $bOth;
      $total_paid += $bPaid;
      $netItem = max(0, round($bDue - $bDisc + $bOth, 2));
      $remaining_total += max(0, round($netItem - $bPaid, 2));
  }
?>
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;" id="dues-section">
  <div class="stat-card" style="border-top: 3px solid #3b82f6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Payable Dues</span>
      <div class="stat-card-icon" style="background: rgba(59,130,246,0.12); color: #3b82f6;">
        <?php echo icon('dues', '', 18); ?>
      </div>
    </div>
    <strong>₱<?php echo number_format($total_amount, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Assigned chapter obligations</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Settled</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('check', '', 18); ?>
      </div>
    </div>
    <strong style="color: #10b981;">₱<?php echo number_format($total_paid, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Verified collections</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid <?php echo $remaining_total > 0 ? '#ef4444' : '#10b981'; ?>;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Overall Balance Due</span>
      <div class="stat-card-icon" style="background: <?php echo $remaining_total > 0 ? 'rgba(239,68,68,0.12)' : 'rgba(16,185,129,0.12)'; ?>; color: <?php echo $remaining_total > 0 ? '#ef4444' : '#10b981'; ?>;">
        <?php echo icon('wallet', '', 18); ?>
      </div>
    </div>
    <strong style="color: <?php echo $remaining_total > 0 ? '#ef4444' : '#10b981'; ?>;">₱<?php echo number_format($remaining_total, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Remaining payable required</span>
  </div>
</div>

<div class="card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
    <div>
      <h2 style="font-size: 17px; margin: 0; color: var(--text-primary);">My Account Statement &amp; Assigned Dues</h2>
      <span class="muted" style="font-size: 12px;">Fiscal Year chapter dues, other obligations, and settlement status</span>
    </div>
    <div style="display: flex; gap: 8px; align-items: center;">
      <a href="dues.php" class="btn btn-secondary" style="font-size: 12px; padding: 5px 12px;">
        <?php echo icon('dues', '', 14); ?> Open Dues Page &rarr;
      </a>
      <span class="muted" style="font-size: 12px;"><?php echo count($dues); ?> items</span>
    </div>
  </div>

  <?php if (empty($dues)): ?>
    <p class="muted" style="text-align: center; padding: 32px;">No dues have been assigned to your account yet.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Due Period / Package</th>
            <th>Total Amount</th>
            <th>Paid</th>
            <th>Balance</th>
            <th>Due Date</th>
            <th>Status</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dues as $d):
            $remaining = round((float)$d['amount'] - (float)$d['total_paid'], 2);
          ?>
            <tr>
              <td>
                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($d['title']); ?></strong>
                <?php if (!empty($d['term'])): ?>
                  <span class="badge" style="font-size: 10px; background: rgba(245,158,11,0.1); color: #f59e0b; margin-left: 6px;"><?php echo htmlspecialchars($d['term']); ?></span>
                <?php endif; ?>
                <?php if ($d['description']): ?><br><span class="muted" style="font-size: 11.5px;"><?php echo htmlspecialchars($d['description']); ?></span><?php endif; ?>
                <?php if ($d['payment_type'] === 'partial' && $d['installment_months']): ?>
                  <br><span class="badge-pill badge-partial" style="font-size: 10px; margin-top: 2px;"><?php echo $d['installment_months']; ?>-month installment</span>
                <?php endif; ?>
              </td>
              <td>₱<?php echo number_format($d['amount'], 2); ?></td>
              <td><strong style="color:#10b981;">₱<?php echo number_format($d['total_paid'], 2); ?></strong></td>
              <td>
                <strong style="color:<?php echo $remaining > 0 ? '#ef4444' : '#10b981'; ?>;">
                  ₱<?php echo number_format(max(0, $remaining), 2); ?>
                </strong>
              </td>
              <td><?php echo $d['due_date'] ? date('M d, Y', strtotime($d['due_date'])) : '<span class="muted">—</span>'; ?></td>
              <td>
                <?php
                  $badge = $d['status'];
                  $label = match($d['status']) {
                    'unpaid' => 'Unpaid',
                    'pending' => 'Pending Verification',
                    'partial' => 'Partially Paid',
                    'paid' => 'Fully Paid',
                    'rejected' => 'Rejected',
                    default => ucfirst($d['status'])
                  };
                ?>
                <span class="badge-pill badge-<?php echo $badge === 'partial' ? 'pending' : ($badge === 'rejected' ? 'unpaid' : $badge); ?>">
                  <?php echo $label; ?>
                </span>
              </td>
              <td style="text-align: right;">
                <?php if (in_array($d['status'], ['unpaid','rejected','partial'])): ?>
                  <a class="btn btn-sm btn-primary" href="pay.php?member_due_id=<?php echo $d['member_due_id']; ?>" style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700;">
                    <?php echo icon('payments', '', 12); ?> <span><?php echo $d['status'] === 'partial' ? 'Pay Next' : 'Pay Now'; ?></span>
                  </a>
                <?php elseif ($d['status'] === 'pending'): ?>
                  <span class="muted" style="font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('clock', '', 12); ?> Awaiting review
                  </span>
                <?php else: ?>
                  <span class="badge-pill badge-paid" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('check', '', 11); ?> Settled
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<style>
@media (max-width: 768px) {
  .member-hero-grid {
    grid-template-columns: 1fr !important;
  }
}
</style>

<!-- BULLETIN READER MODAL -->
<div id="readerModal" class="modal-backdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center; padding: 20px;">
  <div class="card" style="max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; background: var(--card-bg); border: 1px solid var(--border-color); padding: 28px; box-shadow: 0 12px 48px rgba(0,0,0,0.6);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
      <div>
        <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 6px;">
          <span class="badge" id="modalCategoryBadge" style="background: rgba(255,255,255,0.06); color: var(--text-primary); font-size: 11px; border: 1px solid var(--border-color); padding: 3px 8px;"></span>
          <span class="muted" id="modalDatePosted" style="font-size: 12px;"></span>
        </div>
        <h2 id="modalTitle" style="font-size: 19px; font-weight: 800; margin: 0; color: var(--text-primary); line-height: 1.3;"></h2>
      </div>
      <button type="button" class="btn btn-sm btn-secondary" onclick="closeReaderModal()" style="font-size: 14px; padding: 2px 8px;">&times;</button>
    </div>

    <div id="modalBody">
      <div id="modalPosterContainer" style="display: none; margin-bottom: 20px; text-align: center;">
        <img id="modalPosterImg" src="" alt="Announcement Poster" style="max-width: 100%; max-height: 380px; border-radius: 8px; border: 1px solid var(--border-color); object-fit: contain;">
      </div>

      <div id="modalContentText" style="font-size: 14px; line-height: 1.7; color: var(--text-primary); white-space: pre-line; margin-bottom: 24px;"></div>

      <div id="modalPdfContainer" style="display: none; padding: 14px 18px; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px solid var(--border-color); align-items: center; justify-content: space-between; gap: 12px;">
        <div>
          <strong style="color: var(--text-primary); font-size: 13.5px; display: block;">Official Document Attached</strong>
          <span class="muted" style="font-size: 12px;">Download and review the certified chapter issuance in PDF format.</span>
        </div>
        <a id="modalPdfLink" href="" target="_blank" class="btn btn-primary" style="font-size: 12px; padding: 8px 16px; font-weight: 700; white-space: nowrap;">
          Download PDF Circular
        </a>
      </div>
    </div>

    <div style="border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 24px; text-align: right;">
      <button type="button" class="btn btn-secondary" onclick="closeReaderModal()">Close</button>
    </div>
  </div>
</div>

<script>
function openReaderModal(item) {
    document.getElementById('modalTitle').textContent = item.title || '';
    document.getElementById('modalCategoryBadge').textContent = item.category || 'General';
    document.getElementById('modalDatePosted').textContent = 'Posted: ' + (item.date_display || item.date_posted || '');

    const content = item.content || item.summary || 'No further details provided.';
    document.getElementById('modalContentText').textContent = content;

    const posterContainer = document.getElementById('modalPosterContainer');
    const posterImg = document.getElementById('modalPosterImg');
    if (item.image_path) {
        posterImg.src = '<?php echo BASE_URL; ?>/' + item.image_path.replace(/^\/+/, '');
        posterContainer.style.display = 'block';
    } else {
        posterContainer.style.display = 'none';
    }

    const pdfContainer = document.getElementById('modalPdfContainer');
    const pdfLink = document.getElementById('modalPdfLink');
    if (item.pdf_attachment) {
        pdfLink.href = '<?php echo BASE_URL; ?>/' + item.pdf_attachment.replace(/^\/+/, '');
        pdfContainer.style.display = 'flex';
    } else {
        pdfContainer.style.display = 'none';
    }

    document.getElementById('readerModal').style.display = 'flex';
}

function closeReaderModal() {
    document.getElementById('readerModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('readerModal');
    if (e.target === modal) {
        closeReaderModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
