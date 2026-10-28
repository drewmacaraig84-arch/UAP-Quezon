<?php
require_once __DIR__ . '/../includes/auth.php';
require_member();

$currentUid = current_user_id();

// Load member info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$currentUid]);
$memberUser = $stmt->fetch();

$fy = get_active_fiscal_year();
$attendance = get_or_create_member_attendance($pdo, $currentUid, $fy);
$c = $attendance['computed'];

$page_title = 'My Chapter Status & Attendance • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">FISCAL YEAR <?php echo htmlspecialchars($fy); ?> &bull; CHAPTER COMPLIANCE</p>
    <h1>My Chapter Status &amp; Attendance</h1>
    <p class="page-subtitle">Track your General Membership Meeting attendance, major chapter conventions, and active member status.</p>
  </div>
  <div class="hero-badge" style="border: 1px solid <?php echo $c['badge_border']; ?>; background: <?php echo $c['badge_bg']; ?>; color: <?php echo $c['badge_color']; ?>;">
    <?php echo icon($c['is_active'] ? 'check' : 'alert', '', 14); ?>
    <span style="font-weight: 800; letter-spacing: 0.05em;"><?php echo htmlspecialchars($c['badge_label']); ?></span>
  </div>
</div>

<!-- ============================================================== -->
<!-- 1. TOP STATS CARDS: STATUS, TOTALS, F2F, ZOOM                  -->
<!-- ============================================================== -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
  <!-- Activity Status Card -->
  <div class="stat-card" style="border-top: 3px solid <?php echo $c['badge_color']; ?>;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Chapter Standing</span>
      <div class="stat-card-icon" style="background: <?php echo $c['badge_bg']; ?>; color: <?php echo $c['badge_color']; ?>;">
        <?php echo icon($c['is_active'] ? 'shield_check' : 'alert', '', 18); ?>
      </div>
    </div>
    <strong style="color: <?php echo $c['badge_color']; ?>; font-size: 22px;">
      <?php echo htmlspecialchars($c['badge_label']); ?>
    </strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
      <?php echo htmlspecialchars($c['description']); ?>
    </span>
  </div>

  <!-- Total Events Attended -->
  <div class="stat-card" style="border-top: 3px solid #3b82f6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Events Attended</span>
      <div class="stat-card-icon" style="background: rgba(59,130,246,0.12); color: #3b82f6;">
        <?php echo icon('check', '', 18); ?>
      </div>
    </div>
    <strong><?php echo $c['total_attended']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--text-secondary);">Events</span></strong>
    <div style="margin-top: 8px;">
      <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px;" class="muted">
        <span>Progress to Active (7 req.)</span>
        <span><?php echo min(100, round(($c['total_attended'] / 7) * 100)); ?>%</span>
      </div>
      <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden;">
        <div style="width: <?php echo min(100, round(($c['total_attended'] / 7) * 100)); ?>%; height: 100%; background: <?php echo $c['total_attended'] >= 7 ? '#10b981' : '#f59e0b'; ?>; border-radius: 999px;"></div>
      </div>
    </div>
  </div>

  <!-- Total F2F Tally -->
  <div class="stat-card" style="border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Face-to-Face (F2F)</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('user', '', 18); ?>
      </div>
    </div>
    <strong style="color: #10b981;"><?php echo $c['total_f2f']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--text-secondary);">In-Person</span></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Physical gatherings attended</span>
  </div>

  <!-- Total Zoom Tally -->
  <div class="stat-card" style="border-top: 3px solid #06b6d4;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Virtual (Zoom)</span>
      <div class="stat-card-icon" style="background: rgba(6,182,212,0.12); color: #06b6d4;">
        <?php echo icon('zap', '', 18); ?>
      </div>
    </div>
    <strong style="color: #06b6d4;"><?php echo $c['total_zoom']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--text-secondary);">Online</span></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Webinar &amp; remote sessions</span>
  </div>
</div>

<!-- ============================================================== -->
<!-- 2. CURRENT FISCAL YEAR MONTHLY ATTENDANCE (JULY 26 - JUNE 27)  -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 24px; padding: 24px;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
    <div>
      <h2 style="font-size: 17px; font-weight: 700; color: var(--text-primary); margin: 0;">
        Current Fiscal Year Monthly Attendance
      </h2>
      <span class="muted" style="font-size: 12px;">Monthly General Membership Meetings (July 2026 &ndash; June 2027)</span>
    </div>
    <div style="display: flex; gap: 8px; font-size: 11.5px;">
      <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981; border: 1px solid rgba(16,185,129,0.3);">
        <?php echo icon('check', '', 11); ?> Face-to-Face (F2F)
      </span>
      <span class="badge" style="background: rgba(6,182,212,0.12); color: #06b6d4; border: 1px solid rgba(6,182,212,0.3);">
        <?php echo icon('zap', '', 11); ?> Zoom Meeting
      </span>
      <span class="badge" style="background: rgba(100,116,139,0.12); color: #94a3b8; border: 1px solid rgba(100,116,139,0.3);">
        Absent
      </span>
    </div>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 14px;">
    <?php
      $months = get_member_attendance_months();
      foreach ($months as $key => $meta):
        $val = strtolower($attendance['month_' . $key] ?? 'absent');
        $isPresentF2F = ($val === 'f2f');
        $isPresentZoom = ($val === 'zoom');
        $isAbsent = (!$isPresentF2F && !$isPresentZoom);
    ?>
      <div style="background: var(--bg-secondary, rgba(255,255,255,0.02)); border: 1px solid <?php echo $isPresentF2F ? 'rgba(16,185,129,0.35)' : ($isPresentZoom ? 'rgba(6,182,212,0.35)' : 'var(--border-color)'); ?>; border-radius: 10px; padding: 14px; position: relative; transition: all 0.2s ease;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <strong style="font-size: 13.5px; color: var(--text-primary);"><?php echo htmlspecialchars($meta['label']); ?></strong>
          <span class="muted" style="font-size: 11px; font-family: monospace;"><?php echo $meta['short']; ?></span>
        </div>
        <p style="font-size: 11.5px; color: var(--text-secondary); margin: 0 0 10px 0;">Monthly GMM</p>

        <?php if ($isPresentF2F): ?>
          <span class="badge" style="width: 100%; justify-content: center; background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981; font-weight: 700; font-size: 11px; padding: 5px;">
            <?php echo icon('user', '', 11); ?> Present &bull; F2F
          </span>
        <?php elseif ($isPresentZoom): ?>
          <span class="badge" style="width: 100%; justify-content: center; background: rgba(6,182,212,0.15); color: #06b6d4; border: 1px solid #06b6d4; font-weight: 700; font-size: 11px; padding: 5px;">
            <?php echo icon('zap', '', 11); ?> Present &bull; Zoom
          </span>
        <?php else: ?>
          <span class="badge" style="width: 100%; justify-content: center; background: rgba(100,116,139,0.1); color: #94a3b8; border: 1px solid rgba(100,116,139,0.25); font-size: 11px; padding: 5px;">
            Absent
          </span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ============================================================== -->
<!-- 3. MAJOR & SPECIAL EVENTS ATTENDANCE (Y/N)                     -->
<!-- ============================================================== -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;" class="events-grid">
  <!-- Major Events Attendance -->
  <div class="card" style="padding: 24px; margin: 0;">
    <h2 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('shield_check', '', 18); ?> Major Events Attendance
    </h2>

    <div style="display: flex; flex-direction: column; gap: 12px;">
      <?php
        $majorEvents = get_major_events_list();
        foreach ($majorEvents as $col => $title):
          $attended = !empty($attendance[$col]);
      ?>
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; gap: 12px;">
          <div>
            <strong style="font-size: 13px; color: var(--text-primary); display: block;"><?php echo htmlspecialchars($title); ?></strong>
            <span class="muted" style="font-size: 11px;">Annual Official Chapter Event</span>
          </div>
          <?php if ($attended): ?>
            <span class="badge-pill badge-paid" style="font-size: 12px; font-weight: 800; padding: 4px 10px;">
              <?php echo icon('check', '', 12); ?> YES
            </span>
          <?php else: ?>
            <span class="badge-pill badge-unpaid" style="font-size: 12px; font-weight: 800; padding: 4px 10px; opacity: 0.8;">
              NO
            </span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Special Events Attendance -->
  <div class="card" style="padding: 24px; margin: 0;">
    <h2 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0 0 16px 0; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('sparkles', '', 18); ?> Special Events Attendance
    </h2>

    <div style="display: flex; flex-direction: column; gap: 10px;">
      <?php
        $specialEvents = get_special_events_list();
        foreach ($specialEvents as $col => $title):
          $attended = !empty($attendance[$col]);
      ?>
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; gap: 12px;">
          <div>
            <strong style="font-size: 12.5px; color: var(--text-primary); display: block;"><?php echo htmlspecialchars($title); ?></strong>
            <span class="muted" style="font-size: 10.5px;">National / Regional Fellowship</span>
          </div>
          <?php if ($attended): ?>
            <span class="badge-pill badge-paid" style="font-size: 11.5px; font-weight: 800; padding: 3px 9px;">
              <?php echo icon('check', '', 11); ?> YES
            </span>
          <?php else: ?>
            <span class="badge-pill badge-unpaid" style="font-size: 11.5px; font-weight: 800; padding: 3px 9px; opacity: 0.8;">
              NO
            </span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- 4. CHAPTER POLICY EXPLANATION BOX                              -->
<!-- ============================================================== -->
<div class="card" style="padding: 20px; background: rgba(245,158,11,0.04); border: 1px solid rgba(245,158,11,0.25);">
  <div style="display: flex; align-items: flex-start; gap: 12px;">
    <div style="color: #f59e0b; margin-top: 2px;"><?php echo icon('shield', '', 20); ?></div>
    <div style="font-size: 13px; line-height: 1.5; color: var(--text-secondary);">
      <strong style="color: var(--text-primary); font-size: 14px; display: block; margin-bottom: 4px;">
        UAP Quezon Chapter Activity Status Guidelines:
      </strong>
      <ul style="margin: 0; padding-left: 18px;">
        <li><strong style="color: #10b981;">Active Badge:</strong> Conferred when an architect attends <strong>7 or more</strong> meetings or events across the fiscal year. Entitles the member to voting privileges, electoral rights, and chapter convention subsidy endorsements.</li>
        <li><strong style="color: #ef4444;">Inactive Badge:</strong> Applied when attendance falls to <strong>2 or fewer</strong> meetings/events. Members are encouraged to attend upcoming monthly GMMs and chapter CSR events to reactivate their status.</li>
      </ul>
    </div>
  </div>
</div>

<style>
@media (max-width: 860px) {
  .events-grid {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
