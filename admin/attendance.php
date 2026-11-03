<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (isset($pdo)) {
    ensure_member_attendance_table($pdo);
    ensure_compliance_activities_schema($pdo);
}
$fy = get_active_fiscal_year();
$error = '';
$success = '';

$currentTab = trim($_GET['tab'] ?? 'roster');
if (!in_array($currentTab, ['roster', 'settings', 'batch'], true)) {
    $currentTab = 'roster';
}

// ==============================================================
// 1. HANDLE POST ACTIONS
// ==============================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // Action A: Individual Member Attendance Update
    if ($action === 'save_attendance') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId > 0) {
            $rec = get_or_create_member_attendance($pdo, $targetUserId, $fy);

            $months = get_member_attendance_months($pdo);
            $monthValues = [];
            $customMonthValues = [];
            $stdMonths = ['july', 'august', 'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june'];

            foreach ($months as $k => $m) {
                $val = trim($_POST['month_' . $k] ?? 'absent');
                if (!in_array($val, ['absent', 'f2f', 'zoom'], true)) {
                    $val = 'absent';
                }
                if (in_array($k, $stdMonths, true)) {
                    $monthValues['month_' . $k] = $val;
                } else {
                    $customMonthValues['month_' . $k] = $val;
                }
            }

            $majorEvents = get_major_events_list($pdo);
            $majorValues = [];
            $customMajorValues = [];
            $stdMajors = ['event_induction', 'event_anniversary', 'event_christmas_party'];

            foreach ($majorEvents as $col => $title) {
                $isChk = !empty($_POST[$col]) ? 1 : 0;
                if (in_array($col, $stdMajors, true)) {
                    $majorValues[$col] = $isChk;
                } else {
                    $customMajorValues[$col] = $isChk;
                }
            }

            $specialEvents = get_resolution_special_event_points($pdo);
            $specialValues = [];
            $customSpecialValues = [];
            $stdSpecials = ['event_natcon', 'event_aba', 'event_nca', 'event_pafnam', 'event_wad', 'event_csr', 'event_summer_outing'];

            foreach ($specialEvents as $col => $meta) {
                $isChk = !empty($_POST[$col]) ? 1 : 0;
                if (in_array($col, $stdSpecials, true)) {
                    $specialValues[$col] = $isChk;
                } else {
                    $customSpecialValues[$col] = $isChk;
                }
            }

            $cpdCount = max(0, (int)($_POST['cpd_seminars_count'] ?? 0));

            // Merge any existing custom_events_json
            $existingCustom = !empty($rec['custom_events_json']) ? json_decode($rec['custom_events_json'], true) : [];
            if (!is_array($existingCustom)) $existingCustom = [];
            $mergedCustom = array_merge($existingCustom, $customMonthValues, $customMajorValues, $customSpecialValues);

            // Update standard columns
            $sql = "UPDATE member_attendance SET 
                month_july = ?, month_august = ?, month_september = ?, month_october = ?, month_november = ?, month_december = ?,
                month_january = ?, month_february = ?, month_march = ?, month_april = ?, month_may = ?, month_june = ?,
                event_induction = ?, event_anniversary = ?, event_christmas_party = ?,
                event_natcon = ?, event_aba = ?, event_nca = ?, event_pafnam = ?, event_wad = ?, event_csr = ?, event_summer_outing = ?,
                cpd_seminars_count = ?,
                custom_events_json = ?
                WHERE id = ?
            ";

            $params = [
                $monthValues['month_july'] ?? 'absent',
                $monthValues['month_august'] ?? 'absent',
                $monthValues['month_september'] ?? 'absent',
                $monthValues['month_october'] ?? 'absent',
                $monthValues['month_november'] ?? 'absent',
                $monthValues['month_december'] ?? 'absent',
                $monthValues['month_january'] ?? 'absent',
                $monthValues['month_february'] ?? 'absent',
                $monthValues['month_march'] ?? 'absent',
                $monthValues['month_april'] ?? 'absent',
                $monthValues['month_may'] ?? 'absent',
                $monthValues['month_june'] ?? 'absent',
                $majorValues['event_induction'] ?? 0,
                $majorValues['event_anniversary'] ?? 0,
                $majorValues['event_christmas_party'] ?? 0,
                $specialValues['event_natcon'] ?? 0,
                $specialValues['event_aba'] ?? 0,
                $specialValues['event_nca'] ?? 0,
                $specialValues['event_pafnam'] ?? 0,
                $specialValues['event_wad'] ?? 0,
                $specialValues['event_csr'] ?? 0,
                $specialValues['event_summer_outing'] ?? 0,
                $cpdCount,
                !empty($mergedCustom) ? json_encode($mergedCustom) : null,
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
            $currentTab = 'roster';
        }
    }

    // Action B: Save / Create Compliance Activity (GMM, Major Event, Special Activity)
    elseif ($action === 'save_compliance_activity') {
        $actId = (int)($_POST['activity_id'] ?? 0);
        $actType = trim($_POST['activity_type'] ?? 'monthly_gmm');
        if (!in_array($actType, ['monthly_gmm', 'major_event', 'special_event'], true)) {
            $actType = 'monthly_gmm';
        }
        $title = trim($_POST['title'] ?? '');
        $shortLabel = trim($_POST['short_label'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $pointVal = max(0.0, (float)($_POST['point_value'] ?? 1.0));
        $eventDate = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
        $venue = trim($_POST['venue_or_platform'] ?? '');
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($title === '') {
            $error = 'Activity title is required.';
        } else {
            if ($actId > 0) {
                // Update existing activity
                $upd = $pdo->prepare("
                    UPDATE chapter_compliance_activities 
                    SET activity_type = ?, title = ?, short_label = ?, category = ?, point_value = ?, event_date = ?, venue_or_platform = ?, is_active = ?, sort_order = ?
                    WHERE id = ?
                ");
                $upd->execute([$actType, $title, $shortLabel, $category, $pointVal, $eventDate, $venue, $isActive, $sortOrder, $actId]);
                $msgTitle = $title;
            } else {
                // Create new activity
                $prefix = ($actType === 'monthly_gmm') ? 'month_' : 'event_';
                $slug = preg_replace('/[^a-z0-9_]+/', '_', strtolower($shortLabel ?: $title));
                $key = $prefix . trim(substr($slug, 0, 30), '_');
                if (empty(trim($key, '_'))) {
                    $key = $prefix . time();
                }

                // Ensure unique activity_key
                $chk = $pdo->prepare("SELECT COUNT(*) FROM chapter_compliance_activities WHERE fiscal_year = ? AND activity_key = ?");
                $chk->execute([$fy, $key]);
                if ((int)$chk->fetchColumn() > 0) {
                    $key .= '_' . time();
                }

                $ins = $pdo->prepare("
                    INSERT INTO chapter_compliance_activities 
                    (fiscal_year, activity_type, activity_key, title, short_label, category, point_value, event_date, venue_or_platform, is_active, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([$fy, $actType, $key, $title, $shortLabel, $category, $pointVal, $eventDate, $venue, $isActive, $sortOrder]);
                $msgTitle = $title;
            }

            // Synchronize all member standings
            recalculate_all_members_attendance($pdo, $fy);

            if (function_exists('audit_log')) {
                audit_log('COMPLIANCE_SETTINGS', 'ATTENDANCE', "Saved compliance activity: {$msgTitle} ({$actType}, {$pointVal} pts)", $_SESSION['user_id']);
            }

            $success = "Compliance activity '<strong>" . htmlspecialchars($msgTitle) . "</strong>' saved successfully. All member compliance records have been synchronized.";
            $currentTab = 'settings';
        }
    }

    // Action C: Delete Compliance Activity
    elseif ($action === 'delete_compliance_activity') {
        $actId = (int)($_POST['activity_id'] ?? 0);
        if ($actId > 0) {
            $stmt = $pdo->prepare("SELECT title, activity_type FROM chapter_compliance_activities WHERE id = ?");
            $stmt->execute([$actId]);
            $actRow = $stmt->fetch();

            if ($actRow) {
                $del = $pdo->prepare("DELETE FROM chapter_compliance_activities WHERE id = ?");
                $del->execute([$actId]);

                recalculate_all_members_attendance($pdo, $fy);

                if (function_exists('audit_log')) {
                    audit_log('COMPLIANCE_DELETE', 'ATTENDANCE', "Deleted compliance activity #$actId: {$actRow['title']}", $_SESSION['user_id']);
                }

                $success = "Compliance activity '<strong>" . htmlspecialchars($actRow['title']) . "</strong>' removed. Member standings updated.";
            }
        }
        $currentTab = 'settings';
    }

    // Action D: Batch Attendance Check-In
    elseif ($action === 'save_batch_attendance') {
        $batchKey = trim($_POST['batch_event_key'] ?? '');
        $batchData = $_POST['batch_status'] ?? [];

        if (empty($batchKey)) {
            $error = 'Please select a meeting or event for batch check-in.';
            $currentTab = 'batch';
        } else {
            $updatedCount = 0;
            $stdCols = [
                'month_july', 'month_august', 'month_september', 'month_october', 'month_november', 'month_december',
                'month_january', 'month_february', 'month_march', 'month_april', 'month_may', 'month_june',
                'event_induction', 'event_anniversary', 'event_christmas_party',
                'event_natcon', 'event_aba', 'event_nca', 'event_pafnam', 'event_wad', 'event_csr', 'event_summer_outing'
            ];

            foreach ($batchData as $uId => $val) {
                $userId = (int)$uId;
                if ($userId <= 0) continue;

                $val = trim($val);
                $rec = get_or_create_member_attendance($pdo, $userId, $fy);

                // Handle standard or custom column
                $colKey = str_starts_with($batchKey, 'month_') || str_starts_with($batchKey, 'event_') ? $batchKey : 'month_' . $batchKey;

                if (in_array($colKey, $stdCols, true)) {
                    if (str_starts_with($colKey, 'month_')) {
                        $updVal = in_array($val, ['f2f', 'zoom'], true) ? $val : 'absent';
                        $pdo->prepare("UPDATE member_attendance SET {$colKey} = ? WHERE id = ?")->execute([$updVal, $rec['id']]);
                    } else {
                        $updVal = !empty($val) && $val !== 'absent' && $val !== '0' ? 1 : 0;
                        $pdo->prepare("UPDATE member_attendance SET {$colKey} = ? WHERE id = ?")->execute([$updVal, $rec['id']]);
                    }
                } else {
                    // Custom key in custom_events_json
                    $custom = !empty($rec['custom_events_json']) ? json_decode($rec['custom_events_json'], true) : [];
                    if (!is_array($custom)) $custom = [];
                    $custom[$colKey] = $val;
                    $pdo->prepare("UPDATE member_attendance SET custom_events_json = ? WHERE id = ?")->execute([json_encode($custom), $rec['id']]);
                }

                calculate_and_sync_activity_status($pdo, $userId);
                $updatedCount++;
            }

            $eventTitle = get_event_title_by_key($batchKey, $pdo);
            if (function_exists('audit_log')) {
                audit_log('BATCH_ATTENDANCE', 'ATTENDANCE', "Batch updated attendance for {$updatedCount} members at {$eventTitle}", $_SESSION['user_id']);
            }

            $success = "Batch attendance records successfully updated for <strong>" . $updatedCount . " members</strong> for event: <em>" . htmlspecialchars($eventTitle) . "</em>.";
            $currentTab = 'batch';
        }
    }
}

// ==============================================================
// 2. LOAD DATA ACCORDING TO ACTIVE TAB
// ==============================================================

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

// Load all compliance activities for Tab 2
$complianceActivities = [];
try {
    $cStmt = $pdo->prepare("
        SELECT * FROM chapter_compliance_activities 
        WHERE fiscal_year = ? 
        ORDER BY activity_type ASC, sort_order ASC, id ASC
    ");
    $cStmt->execute([$fy]);
    $complianceActivities = $cStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$monthlyGmmRows = [];
$majorEventRows = [];
$specialEventRows = [];

foreach ($complianceActivities as $act) {
    if ($act['activity_type'] === 'monthly_gmm') {
        $monthlyGmmRows[] = $act;
    } elseif ($act['activity_type'] === 'major_event') {
        $majorEventRows[] = $act;
    } else {
        $specialEventRows[] = $act;
    }
}

// Selected batch event for Tab 3
$batchSelectedEvent = trim($_GET['batch_event'] ?? get_default_event_key($pdo));

$page_title = 'Member Attendance Manager • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">MEMBERSHIP COMPLIANCE &bull; FISCAL YEAR <?php echo htmlspecialchars($fy); ?></p>
    <h1>Attendance &amp; Activity Manager</h1>
    <p class="page-subtitle">Configure compliance activities, manage General Membership Meetings, audit point substitutions, and record batch member attendance.</p>
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

<!-- TOP NAVIGATION TABS -->
<div class="attendance-tabs" style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
  <a href="attendance.php?tab=roster" class="tab-btn <?php echo $currentTab === 'roster' ? 'active' : ''; ?>">
    <?php echo icon('members', '', 16); ?> <span>Member Attendance Roster</span>
  </a>
  <a href="attendance.php?tab=settings" class="tab-btn <?php echo $currentTab === 'settings' ? 'active' : ''; ?>">
    <?php echo icon('settings', '', 16); ?> <span>Compliance Activities &amp; Points Settings</span>
  </a>
  <a href="attendance.php?tab=batch" class="tab-btn <?php echo $currentTab === 'batch' ? 'active' : ''; ?>">
    <?php echo icon('check', '', 16); ?> <span>Batch Meeting Check-In</span>
  </a>
</div>

<style>
.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: 8px;
  background: transparent;
  border: 1px solid transparent;
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
}
.tab-btn:hover {
  background: var(--bg-secondary);
  color: var(--text-primary);
}
.tab-btn.active {
  background: var(--button-primary, #f2b835);
  color: var(--button-primary-text, #1f2937);
  border-color: transparent;
  font-weight: 700;
  box-shadow: 0 4px 12px var(--button-shadow);
}
</style>

<!-- ============================================================== -->
<!-- TAB 1: MEMBER ATTENDANCE ROSTER                                -->
<!-- ============================================================== -->
<?php if ($currentTab === 'roster'): ?>

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
          Governing Fiscal Year compliance, meeting participation quotas, and deficiency compensation points. You can customize activities and point values under the <a href="attendance.php?tab=settings" style="color: #f59e0b; font-weight: 700;">Settings Tab</a>.
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
          <span style="color: #94a3b8; font-size: 11px;">Points are earned from verified Special Activities and CPD Seminars.</span>
        </span>
      </div>
    </div>
  </div>

  <!-- EDIT ATTENDANCE MODAL / PANEL (IF SELECTED) -->
  <?php if ($selectedMember && $selectedAttendance): 
    $sc = $selectedAttendance['computed'];
    $customAtt = !empty($selectedAttendance['custom_events_json']) ? json_decode($selectedAttendance['custom_events_json'], true) : [];
    if (!is_array($customAtt)) $customAtt = [];
  ?>
    <div class="card" style="margin-bottom: 24px; padding: 24px; border: 2px solid var(--accent-primary); background: var(--card-bg);" id="edit-attendance-panel">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
        <div>
          <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; margin-bottom: 6px; font-weight: 700;">EDITING ATTENDANCE RECORD</span>
          <h2 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0;">
            <?php echo htmlspecialchars($selectedMember['name']); ?>
            <span class="muted" style="font-size: 14px; font-weight: normal;">(PRC: <?php echo htmlspecialchars($selectedMember['prc_number'] ?: $selectedMember['id_number']); ?>)</span>
          </h2>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
          <?php echo render_activity_badge($selectedAttendance, true); ?>
          <a href="attendance.php?tab=roster" class="btn btn-sm btn-secondary"><?php echo icon('x', '', 12); ?> Close</a>
        </div>
      </div>

      <form method="post" action="attendance.php?tab=roster&edit_member_id=<?php echo $selectedMemberId; ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_attendance">
        <input type="hidden" name="user_id" value="<?php echo $selectedMemberId; ?>">

        <!-- 1. Monthly GMMs Grid -->
        <h3 style="font-size: 14.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
          1. Monthly General Membership Meetings (GMMs)
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px;">
          <?php
            $months = get_member_attendance_months($pdo);
            foreach ($months as $k => $m):
              $curVal = 'absent';
              if (isset($selectedAttendance['month_' . $k])) {
                  $curVal = strtolower($selectedAttendance['month_' . $k] ?? 'absent');
              } elseif (isset($customAtt['month_' . $k])) {
                  $curVal = strtolower($customAtt['month_' . $k] ?? 'absent');
              } elseif (isset($customAtt[$k])) {
                  $curVal = is_array($customAtt[$k]) ? ($customAtt[$k]['mode'] ?? 'absent') : $customAtt[$k];
              }
          ?>
            <div style="padding: 10px 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px;">
              <label style="font-size: 12px; font-weight: 600; color: var(--text-primary); display: block; margin-bottom: 6px;">
                <?php echo htmlspecialchars($m['label']); ?>
              </label>
              <select name="month_<?php echo htmlspecialchars($k); ?>" class="form-control" style="font-size: 11.5px; padding: 6px 8px;">
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
              <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981; font-size: 10px; font-weight: 700;">At least 2 Req.</span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
              <?php
                $majorEvents = get_major_events_list($pdo);
                foreach ($majorEvents as $col => $title):
                  $checked = !empty($selectedAttendance[$col]) || !empty($customAtt[$col]);
              ?>
                <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer;">
                  <input type="checkbox" name="<?php echo htmlspecialchars($col); ?>" value="1" <?php echo $checked ? 'checked' : ''; ?> style="width: 16px; height: 16px; accent-color: #10b981;">
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
              <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; font-size: 10px; font-weight: 700;">5pts = 1 GMM | 7pts = 1 Major</span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px;">
              <?php
                $specialConfig = get_resolution_special_event_points($pdo);
                foreach ($specialConfig as $col => $meta):
                  $checked = !empty($selectedAttendance[$col]) || !empty($customAtt[$col]);
              ?>
                <label style="display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; cursor: pointer; padding: 4px 6px; border-radius: 4px; background: rgba(255,255,255,0.01);">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="<?php echo htmlspecialchars($col); ?>" value="1" <?php echo $checked ? 'checked' : ''; ?> style="width: 16px; height: 16px; accent-color: #f59e0b;">
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
                <strong style="font-size: 12px; color: var(--text-primary); display: block;">Chapter or District CPD Seminars &amp; Tours:</strong>
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
          <a href="attendance.php?tab=roster" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; padding: 10px 22px;">
            <?php echo icon('check', '', 16); ?> Save Attendance &amp; Update Status
          </button>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <!-- MEMBER ATTENDANCE ROSTER TABLE -->
  <div class="card">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
      <div>
        <h2 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0;">Chapter Member Attendance Records</h2>
        <span class="muted" style="font-size: 12px;">Showing all verified architects for FY <?php echo htmlspecialchars($fy); ?></span>
      </div>

      <!-- Search & Filter Controls -->
      <form method="get" action="attendance.php" style="display: flex; gap: 8px; flex-wrap: wrap;">
        <input type="hidden" name="tab" value="roster">
        <input type="text" name="search" placeholder="Search member name or PRC..." value="<?php echo htmlspecialchars($search); ?>" style="font-size: 12.5px; padding: 6px 10px; min-width: 200px;">
        <select name="status" style="font-size: 12.5px; padding: 6px 10px;">
          <option value="">All Statuses</option>
          <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active (7+ events)</option>
          <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive (&le; 2 events)</option>
          <option value="probationary" <?php echo $filterStatus === 'probationary' ? 'selected' : ''; ?>>Partially Active (3-6)</option>
        </select>
        <button class="btn btn-sm btn-primary" type="submit"><?php echo icon('search', '', 12); ?> Filter</button>
        <?php if ($search || $filterStatus): ?>
          <a href="attendance.php?tab=roster" class="btn btn-sm btn-secondary"><?php echo icon('x', '', 12); ?></a>
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
                  <a href="attendance.php?tab=roster&edit_member_id=<?php echo $mem['id']; ?>#edit-attendance-panel" class="btn btn-sm btn-outline" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px;">
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

<!-- ============================================================== -->
<!-- TAB 2: RESOLUTION ACTIVITIES & POINTS SETTINGS                 -->
<!-- ============================================================== -->
<?php elseif ($currentTab === 'settings'): ?>

  <div class="card" style="margin-bottom: 24px; padding: 22px 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
      <div>
        <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; margin-bottom: 6px; font-weight: 700;">OFFICIAL RESOLUTION COMPLIANCE ENGINE</span>
        <h2 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0 0 4px 0;">
          Resolution Activities &amp; Points Settings
        </h2>
        <p style="margin: 0; font-size: 13px; color: var(--text-secondary); line-height: 1.45;">
          Manage the 12 Monthly GMM cards, Major Milestones, and Eligible Special Activities displayed on the Member Portal. Edit titles, categories, and point values with instant synchronization.
        </p>
      </div>
      <button type="button" class="btn btn-primary" onclick="openActivityModal(0, 'special_event')" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; padding: 9px 18px;">
        <?php echo icon('plus', '', 14); ?> Add Compliance Activity
      </button>
    </div>
  </div>

  <!-- SECTION 1: 12 MONTHLY GMMs -->
  <div class="card" style="margin-bottom: 24px; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
      <div>
        <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0 0 2px 0;">
          1. Current Fiscal Year Monthly GMM Attendance Cards
        </h3>
        <span class="muted" style="font-size: 12px;">Governs the 12 Monthly Attendance cards rendered on member portal</span>
      </div>
      <button type="button" class="btn btn-sm btn-outline" onclick="openActivityModal(0, 'monthly_gmm')">
        <?php echo icon('plus', '', 12); ?> Add Monthly GMM
      </button>
    </div>

    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Month Title</th>
            <th>Short Code</th>
            <th>Category</th>
            <th>Scheduled Date &amp; Venue</th>
            <th style="text-align: center;">Status</th>
            <th style="text-align: center;">Sort Order</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($monthlyGmmRows)): ?>
            <tr><td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 20px;">No monthly GMMs configured.</td></tr>
          <?php else: foreach ($monthlyGmmRows as $r): ?>
            <tr>
              <td><strong style="color: var(--text-primary); font-size: 13.5px;"><?php echo htmlspecialchars($r['title']); ?></strong></td>
              <td><span class="badge" style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($r['short_label'] ?: '—'); ?></span></td>
              <td><span class="muted" style="font-size: 12px;"><?php echo htmlspecialchars($r['category'] ?: 'General Membership Meeting'); ?></span></td>
              <td>
                <span style="font-size: 12px; color: var(--text-secondary);">
                  <?php echo $r['event_date'] ? date('M j, Y', strtotime($r['event_date'])) : 'TBD'; ?>
                  <?php if (!empty($r['venue_or_platform'])): ?>
                    &bull; <?php echo htmlspecialchars($r['venue_or_platform']); ?>
                  <?php endif; ?>
                </span>
              </td>
              <td style="text-align: center;">
                <?php if ($r['is_active']): ?>
                  <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981; font-weight: 700; font-size: 10.5px;">ACTIVE</span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(239,68,68,0.12); color: #ef4444; font-weight: 700; font-size: 10.5px;">INACTIVE</span>
                <?php endif; ?>
              </td>
              <td style="text-align: center; font-size: 12px; font-weight: 600;"><?php echo (int)$r['sort_order']; ?></td>
              <td style="text-align: right;">
                <button type="button" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 11.5px;" onclick='populateAndOpenModal(<?php echo json_encode($r); ?>)'>
                  <?php echo icon('edit', '', 12); ?> Edit
                </button>
                <form method="post" action="attendance.php?tab=settings" style="display: inline-block; margin-left: 4px;" onsubmit="return confirm('Delete this monthly GMM?');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="delete_compliance_activity">
                  <input type="hidden" name="activity_id" value="<?php echo $r['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 11.5px; color: #ef4444;">
                    <?php echo icon('trash', '', 12); ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- SECTION 2: MAJOR CHAPTER EVENTS -->
  <div class="card" style="margin-bottom: 24px; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
      <div>
        <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0 0 2px 0;">
          2. Major Chapter Events (Board Reso. Sec. 2 &amp; 3)
        </h3>
        <span class="muted" style="font-size: 12px;">Members must attend at least two (2) of these official annual milestones</span>
      </div>
      <button type="button" class="btn btn-sm btn-outline" onclick="openActivityModal(0, 'major_event')">
        <?php echo icon('plus', '', 12); ?> Add Major Event
      </button>
    </div>

    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Event Title</th>
            <th>Short Label</th>
            <th>Category</th>
            <th>Date &amp; Venue</th>
            <th style="text-align: center;">Status</th>
            <th style="text-align: center;">Sort Order</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($majorEventRows)): ?>
            <tr><td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 20px;">No major events configured.</td></tr>
          <?php else: foreach ($majorEventRows as $r): ?>
            <tr>
              <td><strong style="color: var(--text-primary); font-size: 13.5px;"><?php echo htmlspecialchars($r['title']); ?></strong></td>
              <td><span class="badge" style="font-size: 11px;"><?php echo htmlspecialchars($r['short_label'] ?: '—'); ?></span></td>
              <td><span class="muted" style="font-size: 12px;"><?php echo htmlspecialchars($r['category'] ?: 'Annual Official Chapter Event'); ?></span></td>
              <td>
                <span style="font-size: 12px; color: var(--text-secondary);">
                  <?php echo $r['event_date'] ? date('M j, Y', strtotime($r['event_date'])) : 'Annual Milestone'; ?>
                  <?php if (!empty($r['venue_or_platform'])): ?>
                    &bull; <?php echo htmlspecialchars($r['venue_or_platform']); ?>
                  <?php endif; ?>
                </span>
              </td>
              <td style="text-align: center;">
                <?php if ($r['is_active']): ?>
                  <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981; font-weight: 700; font-size: 10.5px;">ACTIVE</span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(239,68,68,0.12); color: #ef4444; font-weight: 700; font-size: 10.5px;">INACTIVE</span>
                <?php endif; ?>
              </td>
              <td style="text-align: center; font-size: 12px; font-weight: 600;"><?php echo (int)$r['sort_order']; ?></td>
              <td style="text-align: right;">
                <button type="button" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 11.5px;" onclick='populateAndOpenModal(<?php echo json_encode($r); ?>)'>
                  <?php echo icon('edit', '', 12); ?> Edit
                </button>
                <form method="post" action="attendance.php?tab=settings" style="display: inline-block; margin-left: 4px;" onsubmit="return confirm('Delete this major event?');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="delete_compliance_activity">
                  <input type="hidden" name="activity_id" value="<?php echo $r['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 11.5px; color: #ef4444;">
                    <?php echo icon('trash', '', 12); ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- SECTION 3: SPECIAL ACTIVITIES & POINTS SYSTEM -->
  <div class="card" style="margin-bottom: 24px; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
      <div>
        <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0 0 2px 0;">
          3. Eligible Special Activities &amp; Deficiency Point System (Board Reso. Sec. 4 &amp; 5)
        </h3>
        <span class="muted" style="font-size: 12px;">Substitution quota: 5 Points = 1 Missed GMM &bull; 7 Points = 1 Missed Major Event</span>
      </div>
      <button type="button" class="btn btn-sm btn-outline" onclick="openActivityModal(0, 'special_event')">
        <?php echo icon('plus', '', 12); ?> Add Special Activity
      </button>
    </div>

    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Eligible Special Activity</th>
            <th>Category</th>
            <th style="text-align: center;">Official Point Value</th>
            <th style="text-align: center;">Status</th>
            <th style="text-align: center;">Sort Order</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($specialEventRows)): ?>
            <tr><td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 20px;">No special activities configured.</td></tr>
          <?php else: foreach ($specialEventRows as $r): ?>
            <tr>
              <td>
                <strong style="color: var(--text-primary); font-size: 13.5px; display: block;">
                  <?php echo htmlspecialchars($r['title']); ?>
                </strong>
                <span class="muted" style="font-size: 11px;">Key: <?php echo htmlspecialchars($r['activity_key']); ?></span>
              </td>
              <td><span class="muted" style="font-size: 12px;"><?php echo htmlspecialchars($r['category'] ?: 'Special Activity'); ?></span></td>
              <td style="text-align: center;">
                <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; font-weight: 800; font-size: 12px; padding: 4px 10px; border: 1px solid rgba(245,158,11,0.3);">
                  <?php echo number_format((float)$r['point_value'], 1); ?> pts
                </span>
              </td>
              <td style="text-align: center;">
                <?php if ($r['is_active']): ?>
                  <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981; font-weight: 700; font-size: 10.5px;">ACTIVE</span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(239,68,68,0.12); color: #ef4444; font-weight: 700; font-size: 10.5px;">INACTIVE</span>
                <?php endif; ?>
              </td>
              <td style="text-align: center; font-size: 12px; font-weight: 600;"><?php echo (int)$r['sort_order']; ?></td>
              <td style="text-align: right;">
                <button type="button" class="btn btn-sm btn-outline" style="padding: 4px 8px; font-size: 11.5px;" onclick='populateAndOpenModal(<?php echo json_encode($r); ?>)'>
                  <?php echo icon('edit', '', 12); ?> Edit
                </button>
                <form method="post" action="attendance.php?tab=settings" style="display: inline-block; margin-left: 4px;" onsubmit="return confirm('Delete this special activity? Points and member compliance will be updated.');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="delete_compliance_activity">
                  <input type="hidden" name="activity_id" value="<?php echo $r['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 11.5px; color: #ef4444;">
                    <?php echo icon('trash', '', 12); ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ADD / EDIT ACTIVITY MODAL -->
  <div id="activity-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 580px; width: 100%; max-height: 90vh; overflow-y: auto; background: var(--card-bg); padding: 24px; border: 1px solid var(--border-color); box-shadow: 0 10px 40px rgba(0,0,0,0.4); border-radius: 12px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
        <h3 id="modal-title-heading" style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin: 0;">
          Add Compliance Activity
        </h3>
        <button type="button" onclick="closeActivityModal()" style="background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 18px;">
          &times;
        </button>
      </div>

      <form method="post" action="attendance.php?tab=settings" id="activity-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_compliance_activity">
        <input type="hidden" name="activity_id" id="form-activity-id" value="0">

        <div style="margin-bottom: 14px;">
          <label style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 5px;">Compliance Activity Classification</label>
          <select name="activity_type" id="form-activity-type" class="form-control" style="width: 100%;" onchange="handleActivityTypeChange()">
            <option value="monthly_gmm">1. Monthly General Membership Meeting (GMM)</option>
            <option value="major_event">2. Major Chapter Event (Induction, Anniversary, Christmas)</option>
            <option value="special_event">3. Special Activity &amp; Point Substitution (Reso. Sec. 5)</option>
          </select>
        </div>

        <div style="margin-bottom: 14px;">
          <label style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 5px;">Activity Title / Name <span style="color: #ef4444;">*</span></label>
          <input type="text" name="title" id="form-title" class="form-control" style="width: 100%;" placeholder="e.g. NATCON (National Convention)" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div>
            <label style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 5px;">Short Label / Code</label>
            <input type="text" name="short_label" id="form-short-label" class="form-control" style="width: 100%;" placeholder="e.g. NATCON or Jul 26">
          </div>
          <div>
            <label style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 5px;">Category</label>
            <input type="text" name="category" id="form-category" class="form-control" style="width: 100%;" placeholder="e.g. National Convention">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;" id="points-container">
          <div>
            <label style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 5px;">Official Point Value</label>
            <input type="number" step="0.5" min="0" max="20" name="point_value" id="form-point-value" class="form-control" style="width: 100%;" value="1.0">
            <span class="muted" style="font-size: 11px;">Points credited for deficiency substitution</span>
          </div>
          <div>
            <label style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 5px;">Sort Order</label>
            <input type="number" name="sort_order" id="form-sort-order" class="form-control" style="width: 100%;" value="1">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
          <div>
            <label style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 5px;">Scheduled Date (Optional)</label>
            <input type="date" name="event_date" id="form-event-date" class="form-control" style="width: 100%;">
          </div>
          <div>
            <label style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 5px;">Venue / Platform (Optional)</label>
            <input type="text" name="venue_or_platform" id="form-venue" class="form-control" style="width: 100%;" placeholder="e.g. Lucena City / Zoom">
          </div>
        </div>

        <div style="margin-bottom: 20px; padding: 10px 12px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 6px;">
          <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
            <input type="checkbox" name="is_active" id="form-is-active" value="1" checked style="width: 16px; height: 16px; accent-color: #10b981;">
            <span><strong>Active in Chapter Portal</strong> (Visible on Member Chapter Status)</span>
          </label>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px;">
          <button type="button" class="btn btn-secondary" onclick="closeActivityModal()">Cancel</button>
          <button type="submit" class="btn btn-primary" style="font-weight: 700; padding: 8px 20px;">
            Save Compliance Activity
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function openActivityModal(id, type) {
    document.getElementById('form-activity-id').value = id || 0;
    document.getElementById('modal-title-heading').innerText = id ? 'Edit Compliance Activity' : 'Add Compliance Activity';
    if (type) {
      document.getElementById('form-activity-type').value = type;
    }
    document.getElementById('form-title').value = '';
    document.getElementById('form-short-label').value = '';
    document.getElementById('form-category').value = '';
    document.getElementById('form-point-value').value = (type === 'special_event') ? '2.0' : '1.0';
    document.getElementById('form-event-date').value = '';
    document.getElementById('form-venue').value = '';
    document.getElementById('form-sort-order').value = '1';
    document.getElementById('form-is-active').checked = true;

    handleActivityTypeChange();
    document.getElementById('activity-modal').style.display = 'flex';
  }

  function populateAndOpenModal(row) {
    document.getElementById('form-activity-id').value = row.id;
    document.getElementById('modal-title-heading').innerText = 'Edit: ' + row.title;
    document.getElementById('form-activity-type').value = row.activity_type;
    document.getElementById('form-title').value = row.title || '';
    document.getElementById('form-short-label').value = row.short_label || '';
    document.getElementById('form-category').value = row.category || '';
    document.getElementById('form-point-value').value = row.point_value || '1.0';
    document.getElementById('form-event-date').value = row.event_date || '';
    document.getElementById('form-venue').value = row.venue_or_platform || '';
    document.getElementById('form-sort-order').value = row.sort_order || '1';
    document.getElementById('form-is-active').checked = (parseInt(row.is_active) === 1);

    handleActivityTypeChange();
    document.getElementById('activity-modal').style.display = 'flex';
  }

  function closeActivityModal() {
    document.getElementById('activity-modal').style.display = 'none';
  }

  function handleActivityTypeChange() {
    const t = document.getElementById('form-activity-type').value;
    const ptsDiv = document.getElementById('points-container');
    if (t === 'special_event') {
      ptsDiv.style.display = 'grid';
    } else {
      ptsDiv.style.display = 'grid';
    }
  }
  </script>

<!-- ============================================================== -->
<!-- TAB 3: BATCH MEETING CHECK-IN                                  -->
<!-- ============================================================== -->
<?php elseif ($currentTab === 'batch'): 
  // Load current event's existing attendance for all members
  $eventAttendees = [];
  try {
      $colKey = str_starts_with($batchSelectedEvent, 'month_') || str_starts_with($batchSelectedEvent, 'event_') ? $batchSelectedEvent : 'month_' . $batchSelectedEvent;
      $stdCols = [
          'month_july', 'month_august', 'month_september', 'month_october', 'month_november', 'month_december',
          'month_january', 'month_february', 'month_march', 'month_april', 'month_may', 'month_june',
          'event_induction', 'event_anniversary', 'event_christmas_party',
          'event_natcon', 'event_aba', 'event_nca', 'event_pafnam', 'event_wad', 'event_csr', 'event_summer_outing'
      ];

      if (in_array($colKey, $stdCols, true)) {
          $attQuery = $pdo->prepare("SELECT user_id, {$colKey} as att_val FROM member_attendance WHERE fiscal_year = ?");
          $attQuery->execute([$fy]);
          while ($ar = $attQuery->fetch()) {
              $eventAttendees[$ar['user_id']] = $ar['att_val'];
          }
      } else {
          $attQuery = $pdo->prepare("SELECT user_id, custom_events_json FROM member_attendance WHERE fiscal_year = ?");
          $attQuery->execute([$fy]);
          while ($ar = $attQuery->fetch()) {
              $cj = !empty($ar['custom_events_json']) ? json_decode($ar['custom_events_json'], true) : [];
              if (isset($cj[$colKey])) {
                  $eventAttendees[$ar['user_id']] = is_array($cj[$colKey]) ? ($cj[$colKey]['mode'] ?? 'f2f') : $cj[$colKey];
              }
          }
      }
  } catch (Throwable $e) {}

  $isGmmEvent = str_starts_with($batchSelectedEvent, 'month_') || in_array($batchSelectedEvent, ['july','august','september','october','november','december','january','february','march','april','may','june']);
  $eventTitleDisplay = get_event_title_by_key($batchSelectedEvent, $pdo);
?>

  <div class="card" style="margin-bottom: 24px; padding: 22px 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
      <div>
        <span class="badge" style="background: rgba(16,185,129,0.15); color: #10b981; margin-bottom: 6px; font-weight: 700;">FAST ROSTER CHECK-IN</span>
        <h2 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0 0 4px 0;">
          Batch Meeting &amp; Event Check-In
        </h2>
        <p style="margin: 0; font-size: 13px; color: var(--text-secondary); line-height: 1.45;">
          Select any Monthly GMM or Major Chapter Event and quickly mark attendance for the entire chapter in one click.
        </p>
      </div>
      <div style="display: flex; align-items: center; gap: 8px;">
        <span class="badge" style="background: rgba(245,158,11,0.12); color: #f59e0b; font-size: 12px; font-weight: 700; padding: 6px 12px;">
          Active Event: <?php echo htmlspecialchars($eventTitleDisplay); ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Event Selector Header Card -->
  <div class="card" style="margin-bottom: 24px; padding: 20px 24px; background: rgba(255,255,255,0.02);">
    <form method="get" action="attendance.php" style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
      <input type="hidden" name="tab" value="batch">
      <div style="flex: 1; min-width: 280px;">
        <label style="font-size: 12px; font-weight: 700; color: var(--text-primary); display: block; margin-bottom: 6px; text-transform: uppercase;">
          Select Compliance Activity or Scheduled Meeting:
        </label>
        <select name="batch_event" class="form-control" style="width: 100%; font-size: 13px; padding: 8px 12px;" onchange="this.form.submit()">
          <optgroup label="Monthly General Membership Meetings (GMMs)">
            <?php
              $mList = get_member_attendance_months($pdo);
              foreach ($mList as $k => $m):
                $keyVal = str_starts_with($k, 'month_') ? $k : 'month_' . $k;
            ?>
              <option value="<?php echo htmlspecialchars($keyVal); ?>" <?php echo $batchSelectedEvent === $keyVal ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($m['label']); ?> (Monthly GMM)
              </option>
            <?php endforeach; ?>
          </optgroup>

          <optgroup label="Major Chapter Events (Board Reso. Sec. 2 &amp; 3)">
            <?php
              $majList = get_major_events_list($pdo);
              foreach ($majList as $k => $t):
            ?>
              <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $batchSelectedEvent === $k ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($t); ?>
              </option>
            <?php endforeach; ?>
          </optgroup>

          <optgroup label="Special Events &amp; Deficiency Point Substitutions">
            <?php
              $spList = get_resolution_special_event_points($pdo);
              foreach ($spList as $k => $sp):
            ?>
              <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $batchSelectedEvent === $k ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($sp['title']); ?> (+<?php echo number_format($sp['points'], 1); ?> pts)
              </option>
            <?php endforeach; ?>
          </optgroup>
        </select>
      </div>
      <button type="submit" class="btn btn-secondary" style="margin-top: 22px;">Switch Meeting</button>
    </form>
  </div>

  <!-- Batch Attendance Entry Form -->
  <form method="post" action="attendance.php?tab=batch&batch_event=<?php echo urlencode($batchSelectedEvent); ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save_batch_attendance">
    <input type="hidden" name="batch_event_key" value="<?php echo htmlspecialchars($batchSelectedEvent); ?>">

    <div class="card" style="padding: 24px;">
      <!-- Batch Action Buttons Toolbar -->
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
        <div>
          <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0 0 2px 0;">
            Chapter Roster Check-In List (<?php echo count($members); ?> Architects)
          </h3>
          <span class="muted" style="font-size: 12px;">Recording attendance for: <strong><?php echo htmlspecialchars($eventTitleDisplay); ?></strong></span>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <?php if ($isGmmEvent): ?>
            <button type="button" class="btn btn-sm btn-outline" onclick="setAllAttendance('f2f')">Set All F2F</button>
            <button type="button" class="btn btn-sm btn-outline" onclick="setAllAttendance('zoom')">Set All Zoom</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setAllAttendance('absent')">Clear All (Absent)</button>
          <?php else: ?>
            <button type="button" class="btn btn-sm btn-outline" onclick="setAllMajor(1)">Set All Attended (Yes)</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setAllMajor(0)">Clear All (No)</button>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary" style="font-weight: 700;">
            <?php echo icon('check', '', 14); ?> Save Batch Attendance
          </button>
        </div>
      </div>

      <div class="table-shell">
        <table>
          <thead>
            <tr>
              <th>Architect Name</th>
              <th>PRC License #</th>
              <th>Current Standing</th>
              <th style="text-align: center;">Attendance Status for This Meeting</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $mem): 
              $mId = (int)$mem['id'];
              $curAtt = $eventAttendees[$mId] ?? 'absent';
              $isF2F = ($curAtt === 'f2f');
              $isZoom = ($curAtt === 'zoom');
              $isAttendedBool = (!empty($curAtt) && $curAtt !== 'absent' && $curAtt !== '0');
            ?>
              <tr>
                <td>
                  <strong style="color: var(--text-primary); font-size: 13.5px; display: block;">
                    <?php echo htmlspecialchars($mem['name']); ?>
                  </strong>
                  <span class="muted" style="font-size: 11.5px;"><?php echo htmlspecialchars($mem['email'] ?: 'No email'); ?></span>
                </td>
                <td>
                  <span class="badge" style="font-size: 11px;">
                    PRC #<?php echo htmlspecialchars($mem['prc_number'] ?: $mem['id_number']); ?>
                  </span>
                </td>
                <td>
                  <span class="badge" style="font-size: 11px; font-weight: 700;">
                    <?php echo strtoupper($mem['activity_status']); ?>
                  </span>
                </td>
                <td style="text-align: center;">
                  <?php if ($isGmmEvent): ?>
                    <div style="display: inline-flex; gap: 8px; align-items: center;">
                      <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="radio" name="batch_status[<?php echo $mId; ?>]" value="absent" class="att-radio-absent" <?php echo (!$isF2F && !$isZoom) ? 'checked' : ''; ?>>
                        <span class="muted">Absent</span>
                      </label>
                      <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px; color: #10b981; font-weight: 600;">
                        <input type="radio" name="batch_status[<?php echo $mId; ?>]" value="f2f" class="att-radio-f2f" <?php echo $isF2F ? 'checked' : ''; ?>>
                        <span>F2F (In-Person)</span>
                      </label>
                      <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px; color: #06b6d4; font-weight: 600;">
                        <input type="radio" name="batch_status[<?php echo $mId; ?>]" value="zoom" class="att-radio-zoom" <?php echo $isZoom ? 'checked' : ''; ?>>
                        <span>Zoom (Online)</span>
                      </label>
                    </div>
                  <?php else: ?>
                    <div style="display: inline-flex; gap: 12px; align-items: center;">
                      <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="radio" name="batch_status[<?php echo $mId; ?>]" value="0" class="major-radio-no" <?php echo !$isAttendedBool ? 'checked' : ''; ?>>
                        <span class="muted">Did Not Attend</span>
                      </label>
                      <label style="font-size: 12px; cursor: pointer; display: flex; align-items: center; gap: 4px; color: #10b981; font-weight: 700;">
                        <input type="radio" name="batch_status[<?php echo $mId; ?>]" value="1" class="major-radio-yes" <?php echo $isAttendedBool ? 'checked' : ''; ?>>
                        <span>Attended (Yes)</span>
                      </label>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
        <button type="submit" class="btn btn-primary" style="font-weight: 700; padding: 10px 24px;">
          <?php echo icon('check', '', 14); ?> Save Batch Attendance Records
        </button>
      </div>
    </div>
  </form>

  <script>
  function setAllAttendance(mode) {
    if (mode === 'f2f') {
      document.querySelectorAll('.att-radio-f2f').forEach(r => r.checked = true);
    } else if (mode === 'zoom') {
      document.querySelectorAll('.att-radio-zoom').forEach(r => r.checked = true);
    } else {
      document.querySelectorAll('.att-radio-absent').forEach(r => r.checked = true);
    }
  }

  function setAllMajor(status) {
    if (status === 1) {
      document.querySelectorAll('.major-radio-yes').forEach(r => r.checked = true);
    } else {
      document.querySelectorAll('.major-radio-no').forEach(r => r.checked = true);
    }
  }
  </script>

<?php endif; ?>

<style>
@media (max-width: 860px) {
  .admin-events-grid {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>