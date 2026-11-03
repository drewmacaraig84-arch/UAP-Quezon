<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (isset($pdo)) {
    ensure_member_attendance_table($pdo);
}
$fy = get_active_fiscal_year();
$error = '';
$success = '';

// Handle POST: Update member attendance
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'save_attendance' && $targetUserId > 0) {
        // Fetch or create attendance record
        $rec = get_or_create_member_attendance($pdo, $targetUserId, $fy);

        $months = get_member_attendance_months();
        $monthValues = [];
        foreach ($months as $k => $m) {
            $val = trim($_POST['month_' . $k] ?? 'absent');
            if (!in_array($val, ['absent', 'f2f', 'zoom'], true)) {
                $val = 'absent';
            }
            $monthValues['month_' . $k] = $val;
        }

        $majorEvents = get_major_events_list();
        $majorValues = [];
        foreach ($majorEvents as $col => $title) {
            $majorValues[$col] = !empty($_POST[$col]) ? 1 : 0;
        }

        $specialEvents = get_special_events_list();
        $specialValues = [];
        foreach ($specialEvents as $col => $title) {
            $specialValues[$col] = !empty($_POST[$col]) ? 1 : 0;
        }
        $cpdCount = max(0, (int)($_POST['cpd_seminars_count'] ?? 0));

        // Prepare update according to Board Resolution schema
        $sql = "UPDATE member_attendance SET 
            month_july = ?, month_august = ?, month_september = ?, month_october = ?, month_november = ?, month_december = ?,
            month_january = ?, month_february = ?, month_march = ?, month_april = ?, month_may = ?, month_june = ?,
            event_induction = ?, event_anniversary = ?, event_christmas_party = ?,
            event_natcon = ?, event_aba = ?, event_nca = ?, event_pafnam = ?, event_wad = ?, event_csr = ?, event_summer_outing = ?,
            cpd_seminars_count = ?
            WHERE id = ?
        ";

        $params = [
            $monthValues['month_july'], $monthValues['month_august'], $monthValues['month_september'],
            $monthValues['month_october'], $monthValues['month_november'], $monthValues['month_december'],
            $monthValues['month_january'], $monthValues['month_february'], $monthValues['month_march'],
            $monthValues['month_april'], $monthValues['month_may'], $monthValues['month_june'],
            $majorValues['event_induction'], $majorValues['event_anniversary'], $majorValues['event_christmas_party'],
            $specialValues['event_natcon'] ?? 0,
            $specialValues['event_aba'] ?? 0,
            $specialValues['event_nca'] ?? 0,
            $specialValues['event_pafnam'] ?? 0,
            $specialValues['event_wad'] ?? 0,
            $specialValues['event_csr'] ?? 0,
            $specialValues['event_summer_outing'] ?? 0,
            $cpdCount,
            $rec['id']
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Recalculate & sync activity status under Board Resolution rules
        $updatedRec = get_or_create_member_attendance($pdo, $targetUserId, $fy);
        $computed = $updatedRec['computed'];

        if (function_exists('audit_log')) {
            audit_log('ATTENDANCE_UPDATE', 'ATTENDANCE', "Updated attendance for user ID #$targetUserId. Total: {$computed['total_attended']}, Standing: {$computed['status']}, Scheme: {$computed['scheme_label']}, Special Points: {$computed['special_points']}", $_SESSION['user_id']);
        }

        $success = "Attendance updated for member. Standing: <strong>" . strtoupper($computed['status']) . "</strong> &bull; Scheme: <em>" . htmlspecialchars($computed['scheme_label']) . "</em> (" . $computed['total_attended'] . " events attended, " . $computed['special_points'] . " special pts).";
    }
}

// Fetch all active chapter members
$search = trim($_GET['search'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$selectedMemberId = (int)($_GET['edit_member_id'] ?? 0);

$membersQuery = "SELECT u.id, u.name, u.id_number, u.prc_number, u.email, u.phone, u.profile_photo,
                 COALESCE(ma.total_attended, 0) as total_attended,
                 COALESCE(ma.total_f2f, 0) as total_f2f,
                 COALESCE(ma.total_zoom, 0) as total_zoom,
                 COALESCE(ma.special_points, 0.0) as special_points,
                 COALESCE(ma.points_used, 0.0) as points_used,
                 ma.scheme_qualified,
                 COALESCE(ma.activity_status, 'inactive') as activity_status
                 FROM users u
                 LEFT JOIN member_attendance ma ON ma.user_id = u.id AND ma.fiscal_year = ?
                 WHERE u.role = 'member' AND u.status = 'approved' AND u.deleted_at IS NULL
";

$queryParams = [$fy];

if ($search !== '') {
    $membersQuery .= " AND (u.name LIKE ? OR u.id_number LIKE ? OR u.prc_number LIKE ?)";
    $like = "%$search%";
    $queryParams = array_merge($queryParams, [$like, $like, $like]);
}

if ($filterStatus !== '') {
    $membersQuery .= " AND COALESCE(ma.activity_status, 'inactive') = ?";
    $queryParams[] = $filterStatus;
}

$membersQuery .= " ORDER BY u.name ASC";

$stmt = $pdo->prepare($membersQuery);
$stmt->execute($queryParams);
$members = $stmt->fetchAll();

// Overall stats
$totalMembersCount = count($members);
$activeCount = 0;
$inactiveCount = 0;
$probationaryCount = 0;

foreach ($members as $m) {
    if ($m['activity_status'] === 'active') {
        $activeCount++;
    } elseif ($m['activity_status'] === 'inactive') {
        $inactiveCount++;
    } else {
        $probationaryCount++;
    }
}

// Fetch selected member record if editing
$selectedMember = null;
$selectedAttendance = null;
if ($selectedMemberId > 0) {
    $mStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'member'");
    $mStmt->execute([$selectedMemberId]);
    $selectedMember = $mStmt->fetch();
    if ($selectedMember) {
        $selectedAttendance = get_or_create_member_attendance($pdo, $selectedMemberId, $fy);
    }
}

$page_title = 'Member Attendance Manager • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">MEMBERSHIP COMPLIANCE &bull; FISCAL YEAR <?php echo htmlspecialchars($fy); ?></p>
    <h1>Attendance &amp; Activity Manager</h1>
    <p class="page-subtitle">Record and audit General Membership Meetings, Zoom webinars, and Major Event participation to verify Active vs Inactive standing.</p>
  </div>
  <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    <a href="scan_attendance.php" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px; padding: 9px 18px; font-weight: 800; box-shadow: 0 4px 14px rgba(245,158,11,0.3);">
      <?php echo icon('qr_codes', '', 16); ?> Open Live QR Scanner
    </a>
    <div class="hero-badge">
      <?php echo icon('check', '', 14); ?> <span>Active Criteria: 7+ Events</span>
    </div>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert alert-success" style="margin-bottom: 20px;">
    <?php echo icon('check', '', 18); ?>
    <span><?php echo $success; ?></span>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-error" style="margin-bottom: 20px;">
    <?php echo icon('alert', '', 18); ?>
    <span><?php echo htmlspecialchars($error); ?></span>
  </div>
<?php endif; ?>

<!-- Overall Stat Grid -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
  <div class="stat-card" style="border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Active Members</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('check', '', 18); ?>
      </div>
    </div>
    <strong style="color: #10b981; font-size: 24px;"><?php echo $activeCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">7 or more events attended</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #ef4444;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Inactive Members</span>
      <div class="stat-card-icon" style="background: rgba(239,68,68,0.12); color: #ef4444;">
        <?php echo icon('alert', '', 18); ?>
      </div>
    </div>
    <strong style="color: #ef4444; font-size: 24px;"><?php echo $inactiveCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">2 or fewer events attended</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #f59e0b;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Partially Active</span>
      <div class="stat-card-icon" style="background: rgba(245,158,11,0.12); color: #f59e0b;">
        <?php echo icon('clock', '', 18); ?>
      </div>
    </div>
    <strong style="color: #f59e0b; font-size: 24px;"><?php echo $probationaryCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">3 to 6 events (in progress)</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #3b82f6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Approved Roster</span>
      <div class="stat-card-icon" style="background: rgba(59,130,246,0.12); color: #3b82f6;">
        <?php echo icon('members', '', 18); ?>
      </div>
    </div>
    <strong style="font-size: 24px;"><?php echo $totalMembersCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Chapter registered architects</span>
  </div>
</div>

<!-- Official Board Resolution Reference Banner -->
<div class="card" style="margin-bottom: 24px; padding: 18px 22px; background: rgba(245,158,11,0.03); border: 1px solid rgba(245,158,11,0.25);">
  <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
    <div>
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
        <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; font-weight: 800; font-size: 11px;">OFFICIAL CHAPTER BOARD RESOLUTION</span>
        <strong style="color: var(--text-primary); font-size: 14px;">Good Standing &amp; Attendance Compliance Schemes</strong>
      </div>
      <p style="margin: 0; font-size: 12.5px; color: var(--text-secondary); line-height: 1.5;">
        Governing Fiscal Year compliance, meeting participation quotas, and deficiency compensation points:
      </p>
    </div>
  </div>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-top: 14px;">
    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px;">
      <strong style="color: #10b981; font-size: 13px; display: block; margin-bottom: 4px;">Section 2: Face-to-Face Scheme</strong>
      <span style="font-size: 12px; color: var(--text-secondary); line-height: 1.4; display: block;">
        &bull; At least <strong>7 GMMs</strong> in person (F2F)<br>
        &bull; At least <strong>2 Major Events</strong> (Induction, Anniversary, Christmas Party)
      </span>
    </div>
    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px;">
      <strong style="color: #06b6d4; font-size: 13px; display: block; margin-bottom: 4px;">Section 3: Hybrid Attendance Scheme</strong>
      <span style="font-size: 12px; color: var(--text-secondary); line-height: 1.4; display: block;">
        &bull; At least <strong>4 GMMs</strong> in person (F2F)<br>
        &bull; At least <strong>6 GMMs</strong> online (Zoom)<br>
        &bull; At least <strong>2 Major Events</strong>
      </span>
    </div>
    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 14px;">
      <strong style="color: #f59e0b; font-size: 13px; display: block; margin-bottom: 4px;">Section 4 &amp; 5: Point Substitution</strong>
      <span style="font-size: 12px; color: var(--text-secondary); line-height: 1.4; display: block;">
        &bull; <strong>5 points</strong> = substitute for 1 missed GMM<br>
        &bull; <strong>7 points</strong> = substitute for 1 missed Major Event<br>
        <span style="color: #94a3b8; font-size: 11px;">(NATCON: 3pts &bull; ABA: 2pts &bull; NCA: 2pts &bull; PAFNAM: 2pts &bull; WAD/CSR/Outing/CPD: 1pt each)</span>
      </span>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- EDIT ATTENDANCE MODAL / PANEL (IF SELECTED)                    -->
<!-- ============================================================== -->
<?php if ($selectedMember && $selectedAttendance): 
  $sc = $selectedAttendance['computed'];
?>
  <div class="card" style="margin-bottom: 24px; padding: 24px; border: 2px solid var(--accent-primary); background: var(--card-bg);" id="edit-attendance-panel">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
      <div>
        <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; margin-bottom: 6px;">EDITING ATTENDANCE RECORD</span>
        <h2 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0;">
          <?php echo htmlspecialchars($selectedMember['name']); ?>
          <span class="muted" style="font-size: 14px; font-weight: normal;">(PRC: <?php echo htmlspecialchars($selectedMember['prc_number'] ?: $selectedMember['id_number']); ?>)</span>
        </h2>
      </div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <?php echo render_activity_badge($selectedAttendance, true); ?>
        <a href="attendance.php" class="btn btn-sm btn-secondary"><?php echo icon('x', '', 12); ?> Close</a>
      </div>
    </div>

    <form method="post" action="attendance.php?edit_member_id=<?php echo $selectedMemberId; ?>">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_attendance">
      <input type="hidden" name="user_id" value="<?php echo $selectedMemberId; ?>">

      <!-- 12 Months Grid -->
      <h3 style="font-size: 14.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
        1. Monthly General Membership Meetings (July 2026 &ndash; June 2027)
      </h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px;">
        <?php
          $months = get_member_attendance_months();
          foreach ($months as $k => $m):
            $curVal = strtolower($selectedAttendance['month_' . $k] ?? 'absent');
        ?>
          <div style="padding: 10px 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px;">
            <label style="font-size: 12px; font-weight: 600; color: var(--text-primary); display: block; margin-bottom: 6px;">
              <?php echo $m['label']; ?>
            </label>
            <select name="month_<?php echo $k; ?>" class="form-control" style="font-size: 11.5px; padding: 6px 8px;">
              <option value="absent" <?php echo $curVal === 'absent' ? 'selected' : ''; ?>>Absent</option>
              <option value="f2f" <?php echo $curVal === 'f2f' ? 'selected' : ''; ?>>Present &bull; F2F</option>
              <option value="zoom" <?php echo $curVal === 'zoom' ? 'selected' : ''; ?>>Present &bull; Zoom</option>
            </select>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Major & Special Events Checkboxes -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;" class="admin-events-grid">
        <!-- Major Events (Sec. 2 & 3) -->
        <div style="padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin: 0;">
              2. Major Chapter Events (Reso. Sec. 2 &amp; 3)
            </h4>
            <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981; font-size: 10px;">At least 2 Req.</span>
          </div>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <?php
              $majorEvents = get_major_events_list();
              foreach ($majorEvents as $col => $title):
                $checked = !empty($selectedAttendance[$col]);
            ?>
              <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer;">
                <input type="checkbox" name="<?php echo $col; ?>" value="1" <?php echo $checked ? 'checked' : ''; ?> style="width: 16px; height: 16px; accent-color: #10b981;">
                <span style="color: var(--text-primary);"><?php echo htmlspecialchars($title); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Special Events (Sec. 5 Point System) -->
        <div style="padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin: 0;">
              3. Special Events &amp; Points System (Reso. Sec. 5)
            </h4>
            <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; font-size: 10px;">5pts = 1 GMM | 7pts = 1 Major</span>
          </div>
          <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px;">
            <?php
              $specialConfig = get_resolution_special_event_points();
              foreach ($specialConfig as $col => $meta):
                $checked = !empty($selectedAttendance[$col]);
            ?>
              <label style="display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; cursor: pointer; padding: 4px 6px; border-radius: 4px; background: rgba(255,255,255,0.01);">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <input type="checkbox" name="<?php echo $col; ?>" value="1" <?php echo $checked ? 'checked' : ''; ?> style="width: 16px; height: 16px; accent-color: #f59e0b;">
                  <span style="color: var(--text-primary);"><?php echo htmlspecialchars($meta['title']); ?></span>
                </div>
                <span class="badge" style="font-size: 10.5px; font-weight: 700; background: rgba(245,158,11,0.12); color: #f59e0b;">
                  +<?php echo number_format($meta['points'], 1); ?> pts
                </span>
              </label>
            <?php endforeach; ?>
          </div>

          <!-- CPD Seminars or Tours Count -->
          <div style="padding-top: 10px; border-top: 1px dashed var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 10px;">
            <div>
              <strong style="font-size: 12px; color: var(--text-primary); display: block;">Chapter's or District's CPD Seminars or Tours:</strong>
              <span class="muted" style="font-size: 11px;">1.0 pt per attended seminar/tour</span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
              <input type="number" name="cpd_seminars_count" min="0" max="50" value="<?php echo (int)($selectedAttendance['cpd_seminars_count'] ?? 0); ?>" style="width: 70px; font-size: 12px; padding: 5px 8px; text-align: center;" class="form-control">
              <span style="font-size: 11px; color: #f59e0b; font-weight: 700;">pts</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Real-Time Scheme Compliance Banner for Member -->
      <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 14px 18px; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
          <div>
            <span class="muted" style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Board Resolution Compliance Preview</span>
            <div style="font-size: 14px; font-weight: 800; color: <?php echo $sc['badge_color']; ?>; margin-top: 2px;">
              <?php echo htmlspecialchars($sc['badge_label']); ?> &bull; <em><?php echo htmlspecialchars($sc['scheme_label']); ?></em>
            </div>
          </div>
          <div style="text-align: right;">
            <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; font-size: 12px; font-weight: 800; padding: 5px 12px;">
              Total Special Points: <?php echo number_format($sc['special_points'], 1); ?> pts
            </span>
          </div>
        </div>
        <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.45;">
          <?php echo htmlspecialchars($sc['qualification_note']); ?>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <a href="attendance.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; padding: 10px 22px;">
          <?php echo icon('check', '', 16); ?> Save Attendance &amp; Update Status
        </button>
      </div>
    </form>
  </div>
<?php endif; ?>

<!-- ============================================================== -->
<!-- MEMBER ATTENDANCE ROSTER TABLE                                 -->
<!-- ============================================================== -->
<div class="card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h2 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0;">Chapter Member Attendance Records</h2>
      <span class="muted" style="font-size: 12px;">Showing all verified architects for FY <?php echo htmlspecialchars($fy); ?></span>
    </div>

    <!-- Search & Filter Controls -->
    <form method="get" style="display: flex; gap: 8px; flex-wrap: wrap;">
      <input type="text" name="search" placeholder="Search member name or PRC..." value="<?php echo htmlspecialchars($search); ?>" style="font-size: 12.5px; padding: 6px 10px; min-width: 200px;">
      <select name="status" style="font-size: 12.5px; padding: 6px 10px;">
        <option value="">All Statuses</option>
        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active (7+ events)</option>
        <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive (&le; 2 events)</option>
        <option value="probationary" <?php echo $filterStatus === 'probationary' ? 'selected' : ''; ?>>Partially Active (3-6)</option>
      </select>
      <button class="btn btn-sm btn-primary" type="submit"><?php echo icon('search', '', 12); ?> Filter</button>
      <?php if ($search || $filterStatus): ?>
        <a href="attendance.php" class="btn btn-sm btn-secondary"><?php echo icon('x', '', 12); ?></a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($members)): ?>
    <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
      No member attendance records found matching your filters.
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Architect / Member</th>
            <th>PRC License #</th>
            <th>Standing &amp; Scheme</th>
            <th>Special Points</th>
            <th>Total Attended</th>
            <th>F2F In-Person</th>
            <th>Zoom Virtual</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $mem): 
            $st = $mem['activity_status'];
            $badgeBg = $st === 'active' ? 'rgba(16,185,129,0.15)' : ($st === 'inactive' ? 'rgba(239,68,68,0.15)' : 'rgba(245,158,11,0.15)');
            $badgeColor = $st === 'active' ? '#10b981' : ($st === 'inactive' ? '#ef4444' : '#f59e0b');
            $badgeBorder = $badgeColor;
            $badgeLabel = $st === 'active' ? 'ACTIVE' : ($st === 'inactive' ? 'INACTIVE' : 'PARTIALLY ACTIVE');

            $schemeLabel = '';
            if ($mem['scheme_qualified'] === 'f2f_scheme') {
                $schemeLabel = 'Face-to-Face Scheme';
            } elseif ($mem['scheme_qualified'] === 'hybrid_scheme') {
                $schemeLabel = 'Hybrid Scheme';
            } elseif ($mem['scheme_qualified'] === 'f2f_compensated') {
                $schemeLabel = 'F2F (Substituted ' . (float)$mem['points_used'] . ' pts)';
            } elseif ($mem['scheme_qualified'] === 'hybrid_compensated') {
                $schemeLabel = 'Hybrid (Substituted ' . (float)$mem['points_used'] . ' pts)';
            } elseif ($st === 'probationary') {
                $schemeLabel = 'In Progress';
            }
          ?>
            <tr>
              <td>
                <strong style="color: var(--text-primary); font-size: 13.5px; display: block;">
                  <?php echo htmlspecialchars($mem['name']); ?>
                </strong>
                <span class="muted" style="font-size: 11.5px;"><?php echo htmlspecialchars($mem['email'] ?: 'No email'); ?></span>
              </td>
              <td>
                <span class="badge" style="background: rgba(245,158,11,0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); font-size: 11px;">
                  PRC #<?php echo htmlspecialchars($mem['prc_number'] ?: $mem['id_number']); ?>
                </span>
              </td>
              <td>
                <span class="badge" style="background: <?php echo $badgeBg; ?>; color: <?php echo $badgeColor; ?>; border: 1px solid <?php echo $badgeBorder; ?>; font-weight: 800; font-size: 11px; padding: 3px 8px; border-radius: 5px; display: inline-block;">
                  <?php echo $badgeLabel; ?>
                </span>
                <?php if ($schemeLabel): ?>
                  <span class="muted" style="display: block; font-size: 10.5px; margin-top: 3px;"><?php echo htmlspecialchars($schemeLabel); ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge" style="background: rgba(245,158,11,0.12); color: #f59e0b; font-weight: 700; font-size: 11.5px; padding: 3px 8px; border: 1px solid rgba(245,158,11,0.25);">
                  <?php echo number_format((float)$mem['special_points'], 1); ?> pts
                </span>
              </td>
              <td>
                <strong style="font-size: 14px; color: <?php echo $badgeColor; ?>;">
                  <?php echo $mem['total_attended']; ?>
                </strong>
              </td>
              <td>
                <span style="font-weight: 600; color: #10b981;"><?php echo $mem['total_f2f']; ?></span>
              </td>
              <td>
                <span style="font-weight: 600; color: #06b6d4;"><?php echo $mem['total_zoom']; ?></span>
              </td>
              <td style="text-align: right;">
                <a href="attendance.php?edit_member_id=<?php echo $mem['id']; ?>#edit-attendance-panel" class="btn btn-sm btn-outline" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px;">
                  <?php echo icon('edit', '', 12); ?> <span>Update</span>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<style>
@media (max-width: 860px) {
  .admin-events-grid {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
