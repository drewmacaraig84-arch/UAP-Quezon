<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_member();

$currentUid = current_user_id();
$fy = get_active_fiscal_year();

// Attendance standing
$attendance = get_or_create_member_attendance($pdo, $currentUid, $fy);

// Fetch upcoming & recent meetings from chapter_events
$stmt = $pdo->prepare("
    SELECT ce.*,
           (SELECT l.scanned_at FROM event_attendance_logs l WHERE l.event_key = CONCAT('ce_', ce.id) AND l.user_id = ?) as member_checkin_time
    FROM chapter_events ce
    WHERE ce.deleted_at IS NULL AND ce.is_published = 1
    ORDER BY ce.event_date ASC, ce.start_time ASC
");
$stmt->execute([$currentUid]);
$allEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$upcoming = [];
$past = [];
$today = date('Y-m-d');

foreach ($allEvents as $ev) {
    if ($ev['event_date'] >= $today && $ev['status'] !== 'completed') {
        $upcoming[] = $ev;
    } else {
        $past[] = $ev;
    }
}

$typeLabels = [
    'gmm'           => ['label' => 'GMM',           'bg' => 'rgba(245,158,11,0.15)', 'color' => '#f59e0b', 'border' => 'rgba(245,158,11,0.4)'],
    'board_meeting' => ['label' => 'BOARD MEETING', 'bg' => 'rgba(59,130,246,0.15)',  'color' => '#3b82f6', 'border' => 'rgba(59,130,246,0.4)'],
    'cpd_seminar'   => ['label' => 'CPD SEMINAR',   'bg' => 'rgba(16,185,129,0.15)', 'color' => '#10b981', 'border' => 'rgba(16,185,129,0.4)'],
    'fellowship'    => ['label' => 'FELLOWSHIP',    'bg' => 'rgba(168,85,247,0.15)', 'color' => '#a855f7', 'border' => 'rgba(168,85,247,0.4)'],
    'assembly'      => ['label' => 'ASSEMBLY',      'bg' => 'rgba(236,72,153,0.15)', 'color' => '#ec4899', 'border' => 'rgba(236,72,153,0.4)'],
    'major'         => ['label' => 'MAJOR EVENT',   'bg' => 'rgba(239,68,68,0.15)',  'color' => '#ef4444', 'border' => 'rgba(239,68,68,0.4)'],
    'special'       => ['label' => 'SPECIAL',       'bg' => 'rgba(14,165,233,0.15)', 'color' => '#0ea5e9', 'border' => 'rgba(14,165,233,0.4)'],
];

$page_title = 'Chapter Meetings & Programs • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">CHAPTER CALENDAR &bull; FISCAL YEAR <?php echo htmlspecialchars($fy); ?></p>
    <h1>Chapter Meetings &amp; Assemblies</h1>
    <p class="page-subtitle">View upcoming General Membership Meetings, technical CPD seminars, and your personal attendance status.</p>
  </div>
  <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    <a href="attendance_pass.php" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px; padding: 9px 18px; font-weight: 800; box-shadow: 0 4px 14px rgba(245,158,11,0.3);">
      <?php echo icon('qr_codes', '', 16); ?> <span>My Attendance Pass</span>
    </a>
    <a href="chapter_status.php" style="text-decoration: none;">
      <?php echo render_activity_badge($attendance, true); ?>
    </a>
  </div>
</div>

<!-- ============================================================== -->
<!-- UPCOMING CHAPTER MEETINGS                                      -->
<!-- ============================================================== -->
<div style="margin-bottom: 36px;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
    <h2 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('clock', '', 18); ?> Upcoming Chapter Meetings (<?php echo count($upcoming); ?>)
    </h2>
    <span class="muted" style="font-size: 12.5px;">Mark your calendar and present your Digital Pass at the door.</span>
  </div>

  <?php if (empty($upcoming)): ?>
    <div class="card" style="text-align: center; padding: 36px 20px; color: var(--text-secondary);">
      <div style="width: 56px; height: 56px; margin: 0 auto 12px; border-radius: 50%; background: rgba(245,158,11,0.1); color: #f59e0b; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('calendar', '', 28); ?>
      </div>
      <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0 0 4px;">No upcoming meetings scheduled right now</h3>
      <p style="font-size: 13px; margin: 0;">Stay tuned! The chapter secretariat will announce the next assembly soon.</p>
    </div>
  <?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px;">
      <?php foreach ($upcoming as $ev): 
        $tMeta = $typeLabels[$ev['event_type']] ?? ['label' => strtoupper($ev['event_type']), 'bg' => 'rgba(255,255,255,0.08)', 'color' => '#fff', 'border' => 'rgba(255,255,255,0.2)'];
        $isCheckedIn = !empty($ev['member_checkin_time']);
        $timeStr = '';
        if ($ev['start_time']) {
            $timeStr = date('h:i A', strtotime($ev['start_time']));
            if ($ev['end_time']) $timeStr .= ' &ndash; ' . date('h:i A', strtotime($ev['end_time']));
        }
      ?>
        <div class="card" style="margin: 0; padding: 22px; display: flex; flex-direction: column; border: 1.5px solid <?php echo $isCheckedIn ? 'rgba(16,185,129,0.4)' : 'rgba(245,158,11,0.3)'; ?>; position: relative;">
          
          <!-- Top Badges Row -->
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; gap: 8px;">
            <span class="badge" style="background: <?php echo $tMeta['bg']; ?>; color: <?php echo $tMeta['color']; ?>; border: 1px solid <?php echo $tMeta['border']; ?>; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 6px;">
              <?php echo $tMeta['label']; ?>
            </span>

            <?php if ($isCheckedIn): ?>
              <span class="badge" style="background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.4); font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo icon('check', '', 12); ?> CHECKED IN
              </span>
            <?php else: ?>
              <span class="badge" style="background: rgba(245,158,11,0.12); color: #f59e0b; font-size: 11px; font-weight: 700;">
                Awaiting Attendance
              </span>
            <?php endif; ?>
          </div>

          <!-- Meeting Title -->
          <h3 style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin: 0 0 10px; line-height: 1.35;">
            <?php echo htmlspecialchars($ev['title']); ?>
          </h3>

          <!-- Logistics Strip -->
          <div style="background: rgba(0,0,0,0.2); padding: 12px 14px; border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 14px; font-size: 13px; display: flex; flex-direction: column; gap: 6px;">
            <div style="display: flex; align-items: center; gap: 8px; color: #f59e0b; font-weight: 700;">
              <?php echo icon('calendar', '', 14); ?>
              <span><?php echo date('l, F j, Y', strtotime($ev['event_date'])); ?></span>
            </div>

            <?php if ($timeStr): ?>
              <div style="display: flex; align-items: center; gap: 8px; color: var(--text-secondary);">
                <?php echo icon('clock', '', 14); ?>
                <span><?php echo $timeStr; ?></span>
              </div>
            <?php endif; ?>

            <?php if ($ev['venue_name']): ?>
              <div style="display: flex; align-items: center; gap: 8px; color: var(--text-secondary);">
                📍 <span><?php echo htmlspecialchars($ev['venue_name']); ?></span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Description / Agenda -->
          <?php if ($ev['description']): ?>
            <p style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.5; margin: 0 0 16px; flex: 1;">
              <?php echo nl2br(htmlspecialchars($ev['description'])); ?>
            </p>
          <?php else: ?>
            <div style="flex: 1;"></div>
          <?php endif; ?>

          <!-- Bottom Action Bar -->
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; pt: 12px; border-top: 1px solid var(--border-color); margin-top: auto; padding-top: 14px;">
            <div style="display: flex; gap: 6px; align-items: center;">
              <?php if ((int)$ev['counts_for_standing'] === 1): ?>
                <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981; font-size: 10.5px;" title="Counts toward 7+ Active Standing">
                  ✓ Standing Credit
                </span>
              <?php endif; ?>
              <?php if ($ev['cpd_units'] > 0): ?>
                <span class="badge" style="background: rgba(59,130,246,0.12); color: #3b82f6; font-size: 10.5px;">
                  <?php echo $ev['cpd_units']; ?> CPD Pts
                </span>
              <?php endif; ?>
            </div>

            <div style="display: flex; gap: 8px;">
              <?php if ($ev['meeting_link']): ?>
                <a href="<?php echo htmlspecialchars($ev['meeting_link']); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline" style="border-color: #3b82f6; color: #3b82f6; font-size: 11.5px; padding: 6px 12px;">
                  Join Zoom &rarr;
                </a>
              <?php endif; ?>
              
              <a href="attendance_pass.php" class="btn btn-sm btn-primary" style="font-size: 11.5px; padding: 6px 12px; font-weight: 700;">
                <?php echo icon('qr_codes', '', 12); ?> Pass
              </a>
            </div>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================== -->
<!-- PAST / COMPLETED CHAPTER MEETINGS                              -->
<!-- ============================================================== -->
<?php if (!empty($past)): ?>
  <div class="card" style="padding: 22px;">
    <h2 style="font-size: 16px; font-weight: 800; color: var(--text-primary); margin: 0 0 14px;">
      Completed Meetings &amp; Past Assemblies (<?php echo count($past); ?>)
    </h2>
    <div style="overflow-x: auto;">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Meeting</th>
            <th>Type</th>
            <th>Venue / Platform</th>
            <th>Your Attendance</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($past as $pev): 
            $pCheckedIn = !empty($pev['member_checkin_time']);
          ?>
            <tr>
              <td style="font-weight: 700; color: var(--text-primary); font-size: 13px;">
                <?php echo date('M j, Y', strtotime($pev['event_date'])); ?>
              </td>
              <td>
                <strong style="font-size: 13.5px; color: var(--text-primary);"><?php echo htmlspecialchars($pev['title']); ?></strong>
              </td>
              <td>
                <span class="badge" style="font-size: 10px; background: rgba(255,255,255,0.06); color: var(--text-secondary);">
                  <?php echo strtoupper($pev['event_type']); ?>
                </span>
              </td>
              <td style="font-size: 12.5px; color: var(--text-secondary);">
                <?php echo htmlspecialchars($pev['venue_name'] ?: ($pev['location_type'] === 'zoom' ? 'Zoom Webinar' : 'Chapter Assembly')); ?>
              </td>
              <td>
                <?php if ($pCheckedIn): ?>
                  <span class="badge" style="background: rgba(16,185,129,0.15); color: #10b981; font-weight: 800; font-size: 11px;">
                    ✓ ATTENDED
                  </span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(239,68,68,0.12); color: #ef4444; font-size: 11px;">
                    Absent
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

