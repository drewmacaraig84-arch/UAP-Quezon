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

// Load custom scheduled chapter meetings attended by this member
$attendedMeetings = [];
try {
    $attendedMeetingsStmt = $pdo->prepare("
        SELECT ce.id, ce.title, ce.event_type, ce.event_date, ce.start_time, ce.venue_name, ce.cpd_units, l.scanned_at, l.attendance_mode
        FROM event_attendance_logs l
        JOIN chapter_events ce ON l.event_key = CONCAT('ce_', ce.id)
        WHERE l.user_id = ? AND l.fiscal_year = ? AND ce.deleted_at IS NULL
        ORDER BY ce.event_date DESC
    ");
    $attendedMeetingsStmt->execute([$currentUid, $fy]);
    $attendedMeetings = $attendedMeetingsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$page_title = 'My Chapter Status & Attendance • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">FISCAL YEAR <?php echo htmlspecialchars($fy); ?> &bull; OFFICIAL CHAPTER BOARD RESOLUTION</p>
    <h1>My Chapter Status &amp; Attendance Schemes</h1>
    <p class="page-subtitle">Track your official compliance under the Face-to-Face Scheme, Hybrid Scheme, and Special Event Point Substitutions.</p>
  </div>
  <div class="hero-badge" style="border: 1px solid <?php echo $c['badge_border']; ?>; background: <?php echo $c['badge_bg']; ?>; color: <?php echo $c['badge_color']; ?>;">
    <?php echo icon($c['is_active'] ? 'check' : ($c['is_probationary'] ? 'clock' : 'alert'), '', 14); ?>
    <span style="font-weight: 800; letter-spacing: 0.05em;"><?php echo htmlspecialchars($c['badge_label']); ?></span>
  </div>
</div>

<!-- ============================================================== -->
<!-- 1. OFFICIAL COMPLIANCE STATUS HERO CARD                       -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 24px; padding: 22px 24px; border-left: 5px solid <?php echo $c['badge_color']; ?>; background: var(--card-bg);">
  <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
    <div>
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
        <span class="badge" style="background: <?php echo $c['badge_bg']; ?>; color: <?php echo $c['badge_color']; ?>; border: 1px solid <?php echo $c['badge_border']; ?>; font-weight: 800; font-size: 11px;">
          <?php echo htmlspecialchars($c['badge_label']); ?>
        </span>
        <strong style="color: var(--text-primary); font-size: 16px;">
          <?php echo htmlspecialchars($c['scheme_label']); ?>
        </strong>
      </div>
      <p style="margin: 0; font-size: 13.5px; color: var(--text-secondary); line-height: 1.5;">
        <?php echo htmlspecialchars($c['qualification_note']); ?>
      </p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
      <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); font-weight: 700; font-size: 12px; padding: 6px 12px;">
        Special Points: <?php echo number_format($c['special_points'], 1); ?> pts
      </span>
      <a href="#points-system" class="btn btn-sm btn-outline" style="font-size: 12px;">View Reso Points</a>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- 2. TOP STATS CARDS: SPECIAL PTS, F2F GMM, ZOOM GMM, MAJOR      -->
<!-- ============================================================== -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 24px;">
  <!-- Special Event Points Card -->
  <div class="stat-card" style="border-top: 3px solid #f59e0b;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Special Event Points</span>
      <div class="stat-card-icon" style="background: rgba(245,158,11,0.12); color: #f59e0b;">
        <?php echo icon('sparkles', '', 18); ?>
      </div>
    </div>
    <strong style="color: #f59e0b; font-size: 24px;">
      <?php echo number_format($c['special_points'], 1); ?> <span style="font-size: 14px; font-weight: 500;">pts</span>
    </strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
      <?php if ($c['points_used'] > 0): ?>
        <span style="color: #10b981; font-weight: 600;"><?php echo number_format($c['points_used'], 1); ?> pts used</span> for substitutions
      <?php else: ?>
        Available for deficiency compensation
      <?php endif; ?>
    </span>
  </div>

  <!-- F2F GMMs Attended -->
  <div class="stat-card" style="border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">In-Person (F2F) GMMs</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('user', '', 18); ?>
      </div>
    </div>
    <strong style="color: #10b981; font-size: 24px;">
      <?php echo $c['total_gmm_f2f']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--text-secondary);">GMMs</span>
    </strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
      Req: 7 (F2F Scheme) &bull; 4 (Hybrid)
    </span>
  </div>

  <!-- Virtual (Zoom) GMMs Attended -->
  <div class="stat-card" style="border-top: 3px solid #06b6d4;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Virtual (Zoom) GMMs</span>
      <div class="stat-card-icon" style="background: rgba(6,182,212,0.12); color: #06b6d4;">
        <?php echo icon('zap', '', 18); ?>
      </div>
    </div>
    <strong style="color: #06b6d4; font-size: 24px;">
      <?php echo $c['total_gmm_zoom']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--text-secondary);">Online</span>
    </strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
      Req: 6 under Hybrid Scheme
    </span>
  </div>

  <!-- Major Chapter Events Attended -->
  <div class="stat-card" style="border-top: 3px solid #8b5cf6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Major Chapter Events</span>
      <div class="stat-card-icon" style="background: rgba(139,92,246,0.12); color: #8b5cf6;">
        <?php echo icon('shield_check', '', 18); ?>
      </div>
    </div>
    <strong style="color: #8b5cf6; font-size: 24px;">
      <?php echo $c['major_count']; ?> <span style="font-size: 14px; font-weight: 500; color: var(--text-secondary);">/ 2 Required</span>
    </strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
      Induction, Anniversary, Christmas
    </span>
  </div>
</div>

<!-- ============================================================== -->
<!-- 3. SIDE-BY-SIDE SCHEME PROGRESS: SECTION 2 VS SECTION 3        -->
<!-- ============================================================== -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;" class="schemes-grid">
  <!-- Scheme 1: Regular Face-to-Face Scheme (Section 2) -->
  <?php 
    $f2f = $c['f2f_scheme'];
    $f2fPctGmm = min(100, round(($f2f['gmm_f2f'] / $f2f['gmm_req']) * 100));
    $f2fPctMajor = min(100, round(($f2f['major_events'] / $f2f['major_req']) * 100));
    $isF2FActiveScheme = ($c['scheme_qualified'] === 'f2f_scheme' || $c['scheme_qualified'] === 'f2f_compensated');
  ?>
  <div class="card" style="padding: 24px; margin: 0; position: relative; <?php echo $isF2FActiveScheme ? 'border: 2px solid #10b981; box-shadow: 0 4px 20px rgba(16,185,129,0.12);' : ''; ?>">
    <?php if ($isF2FActiveScheme): ?>
      <span class="badge" style="position: absolute; top: 16px; right: 16px; background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981; font-weight: 800; font-size: 10.5px;">
        QUALIFIED SCHEME
      </span>
    <?php endif; ?>

    <span class="muted" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #10b981;">
      Board Resolution Section 2
    </span>
    <h3 style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin: 4px 0 10px 0;">
      Regular Face-to-Face (F2F) Scheme
    </h3>
    <p style="font-size: 12.5px; color: var(--text-secondary); margin-bottom: 18px; line-height: 1.45;">
      Requires at least <strong>7 in-person GMMs</strong> and at least <strong>2 Major Chapter Events</strong>.
    </p>

    <!-- F2F GMMs Progress -->
    <div style="margin-bottom: 14px;">
      <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px;">
        <span style="font-weight: 600; color: var(--text-primary);">In-Person GMMs</span>
        <strong style="color: #10b981;"><?php echo $f2f['gmm_f2f']; ?> of <?php echo $f2f['gmm_req']; ?> Attended</strong>
      </div>
      <div style="width: 100%; height: 7px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden;">
        <div style="width: <?php echo $f2fPctGmm; ?>%; height: 100%; background: <?php echo $f2fPctGmm >= 100 ? '#10b981' : '#f59e0b'; ?>; border-radius: 999px;"></div>
      </div>
    </div>

    <!-- Major Events Progress -->
    <div style="margin-bottom: 18px;">
      <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px;">
        <span style="font-weight: 600; color: var(--text-primary);">Major Chapter Events</span>
        <strong style="color: #8b5cf6;"><?php echo $f2f['major_events']; ?> of <?php echo $f2f['major_req']; ?> Attended</strong>
      </div>
      <div style="width: 100%; height: 7px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden;">
        <div style="width: <?php echo $f2fPctMajor; ?>%; height: 100%; background: <?php echo $f2fPctMajor >= 100 ? '#8b5cf6' : '#f59e0b'; ?>; border-radius: 999px;"></div>
      </div>
    </div>

    <!-- Scheme Status Pill -->
    <div style="padding: 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px;">
      <?php if ($f2f['is_direct']): ?>
        <div style="color: #10b981; font-weight: 700; font-size: 12.5px; display: flex; align-items: center; gap: 6px;">
          <?php echo icon('check', '', 14); ?> Direct Compliance Achieved
        </div>
      <?php elseif ($f2f['is_compensated']): ?>
        <div style="color: #10b981; font-weight: 700; font-size: 12.5px; display: flex; align-items: center; gap: 6px;">
          <?php echo icon('check', '', 14); ?> Compensated via <?php echo $f2f['points_needed']; ?> Special Points
        </div>
      <?php else: ?>
        <div style="font-size: 12px; color: var(--text-secondary);">
          Deficiency: Needs <strong><?php echo $f2f['def_gmm']; ?> GMMs</strong> &bull; <strong><?php echo $f2f['def_major']; ?> Major</strong> (or <strong><?php echo $f2f['points_needed']; ?> Special Points</strong> to substitute).
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Scheme 2: Hybrid Attendance Scheme (Section 3) -->
  <?php 
    $hybrid = $c['hybrid_scheme'];
    $hPctF2F = min(100, round(($hybrid['gmm_f2f'] / $hybrid['f2f_req']) * 100));
    $hPctZoom = min(100, round(($hybrid['gmm_zoom'] / $hybrid['zoom_req']) * 100));
    $hPctMajor = min(100, round(($hybrid['major_events'] / $hybrid['major_req']) * 100));
    $isHybridActiveScheme = ($c['scheme_qualified'] === 'hybrid_scheme' || $c['scheme_qualified'] === 'hybrid_compensated');
  ?>
  <div class="card" style="padding: 24px; margin: 0; position: relative; <?php echo $isHybridActiveScheme ? 'border: 2px solid #06b6d4; box-shadow: 0 4px 20px rgba(6,182,212,0.12);' : ''; ?>">
    <?php if ($isHybridActiveScheme): ?>
      <span class="badge" style="position: absolute; top: 16px; right: 16px; background: rgba(6,182,212,0.15); color: #06b6d4; border: 1px solid #06b6d4; font-weight: 800; font-size: 10.5px;">
        QUALIFIED SCHEME
      </span>
    <?php endif; ?>

    <span class="muted" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #06b6d4;">
      Board Resolution Section 3
    </span>
    <h3 style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin: 4px 0 10px 0;">
      Hybrid Attendance Scheme
    </h3>
    <p style="font-size: 12.5px; color: var(--text-secondary); margin-bottom: 18px; line-height: 1.45;">
      Requires at least <strong>4 F2F GMMs</strong>, at least <strong>6 Zoom GMMs</strong>, and at least <strong>2 Major Events</strong>.
    </p>

    <!-- F2F Progress -->
    <div style="margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
        <span style="font-weight: 600; color: var(--text-primary);">In-Person GMMs</span>
        <strong style="color: #10b981;"><?php echo $hybrid['gmm_f2f']; ?> of <?php echo $hybrid['f2f_req']; ?></strong>
      </div>
      <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden;">
        <div style="width: <?php echo $hPctF2F; ?>%; height: 100%; background: <?php echo $hPctF2F >= 100 ? '#10b981' : '#f59e0b'; ?>; border-radius: 999px;"></div>
      </div>
    </div>

    <!-- Zoom Progress -->
    <div style="margin-bottom: 12px;">
      <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
        <span style="font-weight: 600; color: var(--text-primary);">Virtual (Zoom) GMMs</span>
        <strong style="color: #06b6d4;"><?php echo $hybrid['gmm_zoom']; ?> of <?php echo $hybrid['zoom_req']; ?></strong>
      </div>
      <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden;">
        <div style="width: <?php echo $hPctZoom; ?>%; height: 100%; background: <?php echo $hPctZoom >= 100 ? '#06b6d4' : '#f59e0b'; ?>; border-radius: 999px;"></div>
      </div>
    </div>

    <!-- Major Events Progress -->
    <div style="margin-bottom: 18px;">
      <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
        <span style="font-weight: 600; color: var(--text-primary);">Major Chapter Events</span>
        <strong style="color: #8b5cf6;"><?php echo $hybrid['major_events']; ?> of <?php echo $hybrid['major_req']; ?></strong>
      </div>
      <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden;">
        <div style="width: <?php echo $hPctMajor; ?>%; height: 100%; background: <?php echo $hPctMajor >= 100 ? '#8b5cf6' : '#f59e0b'; ?>; border-radius: 999px;"></div>
      </div>
    </div>

    <!-- Scheme Status Pill -->
    <div style="padding: 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px;">
      <?php if ($hybrid['is_direct']): ?>
        <div style="color: #10b981; font-weight: 700; font-size: 12.5px; display: flex; align-items: center; gap: 6px;">
          <?php echo icon('check', '', 14); ?> Direct Compliance Achieved
        </div>
      <?php elseif ($hybrid['is_compensated']): ?>
        <div style="color: #10b981; font-weight: 700; font-size: 12.5px; display: flex; align-items: center; gap: 6px;">
          <?php echo icon('check', '', 14); ?> Compensated via <?php echo $hybrid['points_needed']; ?> Special Points
        </div>
      <?php else: ?>
        <div style="font-size: 12px; color: var(--text-secondary);">
          Deficiency: Needs <strong><?php echo $hybrid['def_gmm']; ?> GMMs</strong> &bull; <strong><?php echo $hybrid['def_major']; ?> Major</strong> (or <strong><?php echo $hybrid['points_needed']; ?> Special Points</strong> to substitute).
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- 4. SECTION 4 & 5: SPECIAL EVENT POINTS & DEFICIENCY ENGINE     -->
<!-- ============================================================== -->
<div class="card" id="points-system" style="margin-bottom: 24px; padding: 24px;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
    <div>
      <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; margin-bottom: 4px; font-weight: 800; font-size: 10.5px;">
        BOARD RESOLUTION SECTION 4 &amp; 5
      </span>
      <h2 style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin: 0;">
        Special Events &amp; Deficiency Point Substitution System
      </h2>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
      <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981; border: 1px solid rgba(16,185,129,0.25); font-weight: 700;">
        5 Points = 1 Missed GMM
      </span>
      <span class="badge" style="background: rgba(139,92,246,0.12); color: #8b5cf6; border: 1px solid rgba(139,92,246,0.25); font-weight: 700;">
        7 Points = 1 Missed Major Event
      </span>
    </div>
  </div>

  <!-- Resolution Activities Table -->
  <div class="table-shell" style="margin-bottom: 20px;">
    <table>
      <thead>
        <tr>
          <th>Eligible Special Activity (Sec. 5)</th>
          <th>Category</th>
          <th style="text-align: center;">Official Point Value</th>
          <th style="text-align: center;">Attended?</th>
          <th style="text-align: right;">Points Credited</th>
        </tr>
      </thead>
      <tbody>
        <?php 
          $breakdown = $c['special_breakdown'];
          foreach ($breakdown as $col => $item):
            $m = $item['meta'];
            $att = $item['attended'];
            $pts = $item['points'];
        ?>
          <tr>
            <td>
              <strong style="color: var(--text-primary); font-size: 13px;">
                <?php echo htmlspecialchars($m['title']); ?>
              </strong>
            </td>
            <td>
              <span class="muted" style="font-size: 11.5px;"><?php echo htmlspecialchars($m['category']); ?></span>
            </td>
            <td style="text-align: center;">
              <span class="badge" style="background: rgba(245,158,11,0.12); color: #f59e0b; font-weight: 800; font-size: 11.5px;">
                <?php echo number_format($m['points'], 1); ?> pts
              </span>
            </td>
            <td style="text-align: center;">
              <?php if ($att): ?>
                <span class="badge-pill badge-paid" style="font-size: 11px; font-weight: 800;">
                  <?php echo icon('check', '', 11); ?> YES
                </span>
              <?php else: ?>
                <span class="badge-pill badge-unpaid" style="font-size: 11px; font-weight: 800; opacity: 0.7;">
                  NO
                </span>
              <?php endif; ?>
            </td>
            <td style="text-align: right;">
              <strong style="color: <?php echo $att ? '#10b981' : 'var(--text-secondary)'; ?>; font-size: 13px;">
                <?php echo $att ? '+' . number_format($pts, 1) : '0.0'; ?>
              </strong>
            </td>
          </tr>
        <?php endforeach; ?>

        <!-- CPD Seminars Row -->
        <tr>
          <td>
            <strong style="color: var(--text-primary); font-size: 13px;">
              Chapter's or District's CPD Seminars or Tours
            </strong>
          </td>
          <td>
            <span class="muted" style="font-size: 11.5px;">Professional Development (<?php echo $c['cpd_count']; ?> attended)</span>
          </td>
          <td style="text-align: center;">
            <span class="badge" style="background: rgba(245,158,11,0.12); color: #f59e0b; font-weight: 800; font-size: 11.5px;">
              1.0 pt each
            </span>
          </td>
          <td style="text-align: center;">
            <?php if ($c['cpd_count'] > 0): ?>
              <span class="badge-pill badge-paid" style="font-size: 11px; font-weight: 800;">
                <?php echo icon('check', '', 11); ?> <?php echo $c['cpd_count']; ?> Seminars
              </span>
            <?php else: ?>
              <span class="badge-pill badge-unpaid" style="font-size: 11px; font-weight: 800; opacity: 0.7;">
                0 Seminars
              </span>
            <?php endif; ?>
          </td>
          <td style="text-align: right;">
            <strong style="color: <?php echo $c['cpd_count'] > 0 ? '#10b981' : 'var(--text-secondary)'; ?>; font-size: 13px;">
              +<?php echo number_format($c['cpd_count'] * 1.0, 1); ?>
            </strong>
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr style="border-top: 2px solid var(--border-color); background: rgba(255,255,255,0.02);">
          <th colspan="4" style="text-align: right; font-size: 13px; font-weight: 700; color: var(--text-primary);">
            Total Special Event Points Accumulated:
          </th>
          <th style="text-align: right; font-size: 15px; font-weight: 900; color: #f59e0b;">
            <?php echo number_format($c['special_points'], 1); ?> pts
          </th>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Point Substitution Summary Box -->
  <div style="background: rgba(245,158,11,0.04); border: 1px dashed rgba(245,158,11,0.35); border-radius: 8px; padding: 16px 20px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
      <div>
        <strong style="color: var(--text-primary); font-size: 13.5px; display: block; margin-bottom: 2px;">
          Deficiency Substitution Accounting:
        </strong>
        <span style="font-size: 12.5px; color: var(--text-secondary);">
          <?php if ($c['points_used'] > 0): ?>
            <strong><?php echo number_format($c['points_used'], 1); ?> points</strong> have been applied to substitute for your missed meeting requirements, granting you <strong>Active Good Standing</strong>. Remaining balance: <strong><?php echo number_format($c['remaining_points'], 1); ?> points</strong>.
          <?php elseif ($c['is_active']): ?>
            You meet the meeting attendance quota directly without requiring deficiency points. All <strong><?php echo number_format($c['special_points'], 1); ?> points</strong> remain in your chapter activity ledger.
          <?php else: ?>
            You currently have <strong><?php echo number_format($c['special_points'], 1); ?> points</strong>. Attend more special events or monthly GMMs to reach full compliance.
          <?php endif; ?>
        </span>
      </div>
      <div style="text-align: right;">
        <span class="badge" style="font-size: 13px; font-weight: 800; padding: 6px 14px; background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid #10b981;">
          Net Balance: <?php echo number_format($c['remaining_points'], 1); ?> pts
        </span>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- 5. CURRENT FISCAL YEAR MONTHLY ATTENDANCE (JULY 26 - JUNE 27)  -->
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
<!-- 6. MAJOR CHAPTER EVENTS ATTENDANCE                             -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 24px; padding: 24px;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
    <div>
      <h2 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 8px;">
        <?php echo icon('shield_check', '', 18); ?> Major Chapter Events (Board Reso. Sec. 2 &amp; 3)
      </h2>
      <span class="muted" style="font-size: 12px;">Members must attend at least two (2) of these official major milestones during the current fiscal year.</span>
    </div>
    <span class="badge" style="background: rgba(139,92,246,0.15); color: #8b5cf6; font-weight: 700; font-size: 11.5px;">
      <?php echo $c['major_count']; ?> / 2 Attended
    </span>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
    <?php
      $majorEvents = get_major_events_list();
      foreach ($majorEvents as $col => $title):
        $attended = !empty($attendance[$col]);
    ?>
      <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; gap: 12px;">
        <div>
          <strong style="font-size: 13px; color: var(--text-primary); display: block; margin-bottom: 2px;">
            <?php echo htmlspecialchars($title); ?>
          </strong>
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

<!-- ============================================================== -->
<!-- 7. SCHEDULED CHAPTER MEETINGS & CPD SEMINARS ATTENDED          -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 24px; padding: 24px;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
    <div>
      <h2 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 8px;">
        <?php echo icon('calendar', '', 18); ?> Scheduled Chapter Meetings &amp; CPD Programs Attended
      </h2>
      <span class="muted" style="font-size: 12px;">Meetings scheduled through the chapter calendar and verified via QR attendance scanner</span>
    </div>
    <a href="events.php" class="btn btn-sm btn-outline" style="font-size: 12px; padding: 5px 12px; display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('calendar', '', 12); ?> <span>Browse Chapter Meetings</span>
    </a>
  </div>

  <?php if (!empty($attendedMeetings)): ?>
    <div style="display: flex; flex-direction: column; gap: 10px;">
      <?php foreach ($attendedMeetings as $am): ?>
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; flex-wrap: wrap; gap: 10px;">
          <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
              <span class="badge" style="font-size: 10px; background: rgba(59,130,246,0.12); color: #60a5fa; text-transform: uppercase;">
                <?php echo htmlspecialchars($am['event_type']); ?>
              </span>
              <?php if ((float)$am['cpd_units'] > 0): ?>
                <span class="badge" style="font-size: 10px; background: rgba(245,158,11,0.15); color: #fbbf24;">
                  <?php echo (float)$am['cpd_units']; ?> CPD Units
                </span>
              <?php endif; ?>
              <strong style="font-size: 13.5px; color: var(--text-primary);"><?php echo htmlspecialchars($am['title']); ?></strong>
            </div>
            <div style="font-size: 12px; color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
              <span><?php echo date('F d, Y', strtotime($am['event_date'])); ?></span>
              <span class="muted">&bull;</span>
              <span><?php echo htmlspecialchars($am['venue_name'] ?: 'Official Venue'); ?></span>
            </div>
          </div>
          <div style="text-align: right;">
            <span class="badge-pill badge-paid" style="font-size: 11.5px; display: inline-flex; align-items: center; gap: 5px;">
              <?php echo icon('check', '', 12); ?> <strong>Verified Attended</strong>
              <?php if (($am['attendance_mode'] ?? '') === 'zoom'): ?>
                <span style="opacity: 0.85; font-size: 10.5px; margin-left: 2px;">(Online)</span>
              <?php else: ?>
                <span style="opacity: 0.85; font-size: 10.5px; margin-left: 2px;">(In-Person)</span>
              <?php endif; ?>
            </span>
            <span class="muted" style="font-size: 11px; display: block; margin-top: 4px;">
              Scanned: <?php echo date('M d, Y h:i A', strtotime($am['scanned_at'])); ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div style="text-align: center; padding: 24px 16px; background: rgba(255,255,255,0.01); border-radius: 8px; border: 1px dashed var(--border-color);">
      <p style="margin: 0 0 10px 0; font-size: 13px; color: var(--text-secondary);">
        No scheduled chapter meetings or CPD seminars attended yet this fiscal year.
      </p>
      <a href="events.php" class="btn btn-sm btn-primary" style="font-size: 12px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('calendar', '', 12); ?> <span>View Upcoming Meetings &amp; Events</span>
      </a>
    </div>
  <?php endif; ?>
</div>

<style>
@media (max-width: 860px) {
  .schemes-grid {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
