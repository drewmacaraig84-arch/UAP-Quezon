<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_admin();

$adminId = current_user_id();
$fy = get_active_fiscal_year();
$error = '';
$success = '';

// -----------------------------------------------------------------------------
// POST HANDLERS: CREATE, EDIT, STATUS, DELETE
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_event' || $action === 'edit_event') {
        $title = trim($_POST['title'] ?? '');
        $eventType = trim($_POST['event_type'] ?? 'gmm');
        $eventDate = trim($_POST['event_date'] ?? '');
        $startTime = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $endTime = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
        $locationType = trim($_POST['location_type'] ?? 'f2f');
        $venueName = trim($_POST['venue_name'] ?? '');
        $meetingLink = trim($_POST['meeting_link'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $cpdUnits = floatval($_POST['cpd_units'] ?? 0);
        $countsForStanding = !empty($_POST['counts_for_standing']) ? 1 : 0;
        $status = trim($_POST['status'] ?? 'upcoming');

        if (!$title || !$eventDate) {
            $error = 'Meeting Title and Event Date are required.';
        } else {
            if ($action === 'create_event') {
                $stmt = $pdo->prepare("
                    INSERT INTO chapter_events 
                    (title, event_type, fiscal_year, event_date, start_time, end_time, location_type, venue_name, meeting_link, description, cpd_units, counts_for_standing, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $eventType, $fy, $eventDate, $startTime, $endTime, $locationType, $venueName, $meetingLink, $description, $cpdUnits, $countsForStanding, $status, $adminId
                ]);
                $newEventId = $pdo->lastInsertId();

                if (function_exists('audit_log')) {
                    audit_log('CREATE_EVENT', 'EVENTS', "Created chapter meeting: $title on $eventDate", $adminId);
                }
                $success = "Chapter meeting <strong>" . htmlspecialchars($title) . "</strong> scheduled successfully!";
            } else {
                $eventId = (int)($_POST['event_id'] ?? 0);
                $stmt = $pdo->prepare("
                    UPDATE chapter_events SET 
                    title = ?, event_type = ?, event_date = ?, start_time = ?, end_time = ?, location_type = ?, 
                    venue_name = ?, meeting_link = ?, description = ?, cpd_units = ?, counts_for_standing = ?, status = ?
                    WHERE id = ? AND deleted_at IS NULL
                ");
                $stmt->execute([
                    $title, $eventType, $eventDate, $startTime, $endTime, $locationType,
                    $venueName, $meetingLink, $description, $cpdUnits, $countsForStanding, $status, $eventId
                ]);

                if (function_exists('audit_log')) {
                    audit_log('UPDATE_EVENT', 'EVENTS', "Updated chapter meeting ID #$eventId: $title", $adminId);
                }
                $success = "Chapter meeting <strong>" . htmlspecialchars($title) . "</strong> updated successfully.";
            }
        }
    }

    if ($action === 'delete_event') {
        $eventId = (int)($_POST['event_id'] ?? 0);
        if ($eventId > 0) {
            $stmt = $pdo->prepare("UPDATE chapter_events SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
            $stmt->execute([$adminId, $eventId]);

            if (function_exists('audit_log')) {
                audit_log('DELETE_EVENT', 'EVENTS', "Cancelled/deleted chapter meeting ID #$eventId", $adminId);
            }
            $success = "Chapter meeting removed successfully.";
        }
    }
}

// -----------------------------------------------------------------------------
// QUERY EVENTS & METRICS
// -----------------------------------------------------------------------------
$filterStatus = trim($_GET['status'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$search = trim($_GET['search'] ?? '');

$query = "
    SELECT ce.*, u.name as creator_name,
           (SELECT COUNT(*) FROM event_attendance_logs l WHERE l.event_key = CONCAT('ce_', ce.id)) as attendees_count
    FROM chapter_events ce
    LEFT JOIN users u ON ce.created_by = u.id
    WHERE ce.deleted_at IS NULL
";
$params = [];

if ($filterStatus !== '') {
    $query .= " AND ce.status = ?";
    $params[] = $filterStatus;
}
if ($filterType !== '') {
    $query .= " AND ce.event_type = ?";
    $params[] = $filterType;
}
if ($search !== '') {
    $query .= " AND (ce.title LIKE ? OR ce.venue_name LIKE ? OR ce.description LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}

$query .= " ORDER BY ce.event_date DESC, ce.start_time DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall Statistics
$totalEventsCount = (int)$pdo->query("SELECT COUNT(*) FROM chapter_events WHERE deleted_at IS NULL")->fetchColumn();
$upcomingCount = (int)$pdo->query("SELECT COUNT(*) FROM chapter_events WHERE status = 'upcoming' AND deleted_at IS NULL")->fetchColumn();
$cpdCount = (int)$pdo->query("SELECT COUNT(*) FROM chapter_events WHERE event_type = 'cpd_seminar' AND deleted_at IS NULL")->fetchColumn();
$totalCheckinsCount = (int)$pdo->query("SELECT COUNT(*) FROM event_attendance_logs WHERE event_key LIKE 'ce_%'")->fetchColumn();

$typeLabels = [
    'gmm'           => ['label' => 'GMM',           'bg' => 'rgba(245,158,11,0.15)', 'color' => '#f59e0b', 'border' => 'rgba(245,158,11,0.4)'],
    'board_meeting' => ['label' => 'BOARD MEETING', 'bg' => 'rgba(59,130,246,0.15)',  'color' => '#3b82f6', 'border' => 'rgba(59,130,246,0.4)'],
    'cpd_seminar'   => ['label' => 'CPD SEMINAR',   'bg' => 'rgba(16,185,129,0.15)', 'color' => '#10b981', 'border' => 'rgba(16,185,129,0.4)'],
    'fellowship'    => ['label' => 'FELLOWSHIP',    'bg' => 'rgba(168,85,247,0.15)', 'color' => '#a855f7', 'border' => 'rgba(168,85,247,0.4)'],
    'assembly'      => ['label' => 'ASSEMBLY',      'bg' => 'rgba(236,72,153,0.15)', 'color' => '#ec4899', 'border' => 'rgba(236,72,153,0.4)'],
    'major'         => ['label' => 'MAJOR EVENT',   'bg' => 'rgba(239,68,68,0.15)',  'color' => '#ef4444', 'border' => 'rgba(239,68,68,0.4)'],
    'special'       => ['label' => 'SPECIAL',       'bg' => 'rgba(14,165,233,0.15)', 'color' => '#0ea5e9', 'border' => 'rgba(14,165,233,0.4)'],
];

$page_title = 'Chapter Meetings & Events Manager • UAP Quezon';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">MEETINGS &amp; PROGRAMS &bull; FISCAL YEAR <?php echo htmlspecialchars($fy); ?></p>
    <h1>Chapter Meetings &amp; Events Manager</h1>
    <p class="page-subtitle">Schedule, coordinate, and audit General Membership Meetings, CPD seminars, and assemblies with instant QR attendance tracking.</p>
  </div>
  <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    <button type="button" onclick="openCreateModal()" class="btn btn-primary" style="display: flex; align-items: center; gap: 8px; padding: 9px 18px; font-weight: 800; box-shadow: 0 4px 14px rgba(245,158,11,0.3);">
      <?php echo icon('sparkles', '', 14); ?> <span>+ Schedule Meeting</span>
    </button>
    <a href="scan_attendance.php" class="btn btn-secondary" style="display: flex; align-items: center; gap: 6px;">
      <?php echo icon('qr_codes', '', 14); ?> <span>Open Scanner</span>
    </a>
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

<!-- ============================================================== -->
<!-- METRIC STAT CARDS                                              -->
<!-- ============================================================== -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 24px;">
  <div class="stat-card" style="border-top: 3px solid #f59e0b;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Scheduled</span>
      <div class="stat-card-icon" style="background: rgba(245,158,11,0.12); color: #f59e0b;">
        <?php echo icon('calendar', '', 18); ?>
      </div>
    </div>
    <strong style="color: #f59e0b; font-size: 24px;"><?php echo $totalEventsCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Meetings &amp; Seminars</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Upcoming Events</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('clock', '', 18); ?>
      </div>
    </div>
    <strong style="color: #10b981; font-size: 24px;"><?php echo $upcomingCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Scheduled on calendar</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #3b82f6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">CPD Technical Seminars</span>
      <div class="stat-card-icon" style="background: rgba(59,130,246,0.12); color: #3b82f6;">
        <?php echo icon('award', '', 18); ?>
      </div>
    </div>
    <strong style="color: #3b82f6; font-size: 24px;"><?php echo $cpdCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Accredited training programs</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #a855f7;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">QR Check-ins Recorded</span>
      <div class="stat-card-icon" style="background: rgba(168,85,247,0.12); color: #a855f7;">
        <?php echo icon('check', '', 18); ?>
      </div>
    </div>
    <strong style="color: #a855f7; font-size: 24px;"><?php echo $totalCheckinsCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Verified attendee check-ins</span>
  </div>
</div>

<!-- ============================================================== -->
<!-- SEARCH & FILTER BAR                                            -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 24px; padding: 18px 20px;">
  <form method="get" action="events.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 220px;">
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search meetings by title, venue, or topics..." class="form-control" style="font-size: 13px; padding: 9px 14px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; width: 100%;">
    </div>

    <div>
      <select name="type" class="form-control" style="font-size: 13px; padding: 9px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
        <option value="">All Meeting Types</option>
        <option value="gmm" <?php echo $filterType === 'gmm' ? 'selected' : ''; ?>>General Membership (GMM)</option>
        <option value="board_meeting" <?php echo $filterType === 'board_meeting' ? 'selected' : ''; ?>>Chapter Board Meeting</option>
        <option value="cpd_seminar" <?php echo $filterType === 'cpd_seminar' ? 'selected' : ''; ?>>CPD Seminar</option>
        <option value="fellowship" <?php echo $filterType === 'fellowship' ? 'selected' : ''; ?>>Fellowship / Gathering</option>
        <option value="assembly" <?php echo $filterType === 'assembly' ? 'selected' : ''; ?>>Special Assembly</option>
      </select>
    </div>

    <div>
      <select name="status" class="form-control" style="font-size: 13px; padding: 9px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
        <option value="">All Statuses</option>
        <option value="upcoming" <?php echo $filterStatus === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
        <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
        <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
      </select>
    </div>

    <button type="submit" class="btn btn-secondary" style="padding: 9px 16px;">
      <?php echo icon('search', '', 14); ?> Filter
    </button>
    <?php if ($filterStatus !== '' || $filterType !== '' || $search !== ''): ?>
      <a href="events.php" class="btn btn-sm btn-outline">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- ============================================================== -->
<!-- SCHEDULED MEETINGS TABLE                                       -->
<!-- ============================================================== -->
<div class="card" style="padding: 24px;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h2 style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin: 0;">
        Chapter Meetings Schedule &bull; <?php echo count($events); ?> Listed
      </h2>
      <span class="muted" style="font-size: 12px;">All meetings automatically synchronize with the QR Attendance Scanner.</span>
    </div>
  </div>

  <div style="overflow-x: auto;">
    <table class="table">
      <thead>
        <tr>
          <th style="min-width: 130px;">Date &amp; Time</th>
          <th style="min-width: 250px;">Meeting Title &amp; Details</th>
          <th style="min-width: 140px;">Type &amp; Credit</th>
          <th style="min-width: 200px;">Location / Platform</th>
          <th style="text-align: center;">Attendees</th>
          <th>Status</th>
          <th style="text-align: right; min-width: 220px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($events)): ?>
          <tr>
            <td colspan="7" style="text-align: center; padding: 36px; color: var(--text-secondary);">
              No chapter meetings found matching criteria. Click <strong>+ Schedule Meeting</strong> to create one!
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($events as $e): 
            $tMeta = $typeLabels[$e['event_type']] ?? ['label' => strtoupper($e['event_type']), 'bg' => 'rgba(255,255,255,0.08)', 'color' => '#fff', 'border' => 'rgba(255,255,255,0.2)'];
            $timeStr = '';
            if ($e['start_time']) {
                $timeStr = date('h:i A', strtotime($e['start_time']));
                if ($e['end_time']) $timeStr .= ' - ' . date('h:i A', strtotime($e['end_time']));
            }
          ?>
            <tr>
              <td>
                <div style="font-weight: 800; color: var(--text-primary); font-size: 14px;">
                  <?php echo date('M j, Y', strtotime($e['event_date'])); ?>
                </div>
                <div style="font-size: 12px; color: #f59e0b; margin-top: 2px;">
                  <?php echo $timeStr ?: date('l', strtotime($e['event_date'])); ?>
                </div>
              </td>

              <td>
                <strong style="font-size: 14.5px; color: var(--text-primary); display: block; margin-bottom: 4px;">
                  <?php echo htmlspecialchars($e['title']); ?>
                </strong>
                <?php if ($e['description']): ?>
                  <p class="muted" style="font-size: 12px; margin: 0; line-height: 1.4; max-width: 380px;">
                    <?php echo htmlspecialchars(mb_strimwidth($e['description'], 0, 110, '...')); ?>
                  </p>
                <?php endif; ?>
              </td>

              <td>
                <span class="badge" style="background: <?php echo $tMeta['bg']; ?>; color: <?php echo $tMeta['color']; ?>; border: 1px solid <?php echo $tMeta['border']; ?>; font-size: 10.5px; font-weight: 800; padding: 4px 8px; border-radius: 6px;">
                  <?php echo $tMeta['label']; ?>
                </span>
                
                <div style="display: flex; gap: 4px; align-items: center; margin-top: 6px; flex-wrap: wrap;">
                  <?php if ((int)$e['counts_for_standing'] === 1): ?>
                    <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981; font-size: 10px;" title="Attending counts towards 7+ active member requirement">
                      ✓ Active Credit
                    </span>
                  <?php else: ?>
                    <span class="badge" style="background: rgba(255,255,255,0.06); color: var(--text-secondary); font-size: 10px;">
                      Optional
                    </span>
                  <?php endif; ?>

                  <?php if ($e['cpd_units'] > 0): ?>
                    <span class="badge" style="background: rgba(59,130,246,0.12); color: #3b82f6; font-size: 10px;">
                      <?php echo $e['cpd_units']; ?> CPD
                    </span>
                  <?php endif; ?>
                </div>
              </td>

              <td>
                <div style="font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 2px;">
                  <?php 
                    if ($e['location_type'] === 'f2f') echo '🏛 In-Person (F2F)';
                    elseif ($e['location_type'] === 'zoom') echo '💻 Zoom Webinar';
                    else echo '🌐 Hybrid (F2F + Zoom)';
                  ?>
                </div>
                <?php if ($e['venue_name']): ?>
                  <div style="font-size: 12px; color: var(--text-secondary);">
                    📍 <?php echo htmlspecialchars($e['venue_name']); ?>
                  </div>
                <?php endif; ?>
                <?php if ($e['meeting_link']): ?>
                  <a href="<?php echo htmlspecialchars($e['meeting_link']); ?>" target="_blank" rel="noopener" style="font-size: 11px; color: #3b82f6; text-decoration: underline; display: inline-block; margin-top: 2px;">
                    Open Zoom Link &rarr;
                  </a>
                <?php endif; ?>
              </td>

              <td style="text-align: center;">
                <span class="badge" style="background: rgba(245,158,11,0.12); color: #f59e0b; font-size: 13px; font-weight: 800; padding: 6px 12px; border-radius: 8px;">
                  <?php echo (int)$e['attendees_count']; ?>
                </span>
              </td>

              <td>
                <?php if ($e['status'] === 'upcoming'): ?>
                  <span class="badge" style="background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.4); font-size: 11px; font-weight: 800;">UPCOMING</span>
                <?php elseif ($e['status'] === 'completed'): ?>
                  <span class="badge" style="background: rgba(255,255,255,0.08); color: var(--text-secondary); font-size: 11px;">COMPLETED</span>
                <?php else: ?>
                  <span class="badge" style="background: rgba(239,68,68,0.15); color: #ef4444; font-size: 11px;">CANCELLED</span>
                <?php endif; ?>
              </td>

              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 6px; align-items: center;">
                  <!-- Direct Scanner Button -->
                  <a href="scan_attendance.php?event_key=ce_<?php echo $e['id']; ?>" class="btn btn-sm btn-primary" style="display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; font-size: 11.5px; font-weight: 700;" title="Scan attendance for this meeting">
                    <?php echo icon('qr_codes', '', 13); ?> <span>Scan</span>
                  </a>

                  <!-- Edit Button -->
                  <button type="button" onclick='openEditModal(<?php echo json_encode($e); ?>)' class="btn btn-sm btn-secondary" style="padding: 6px 10px; font-size: 11.5px;" title="Edit meeting details">
                    <?php echo icon('edit', '', 12); ?>
                  </button>

                  <!-- Delete Form -->
                  <form method="post" action="events.php" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to cancel and remove this meeting?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_event">
                    <input type="hidden" name="event_id" value="<?php echo $e['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-secondary" style="padding: 6px 10px; font-size: 11.5px; color: #ef4444;" title="Cancel meeting">
                      <?php echo icon('trash', '', 12); ?>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============================================================== -->
<!-- SCHEDULE / EDIT MEETING MODAL                                  -->
<!-- ============================================================== -->
<div id="eventModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
  <div class="card" style="max-width: 620px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 26px; border: 2px solid var(--accent-primary); background: var(--card-bg); border-radius: 16px; margin: 0; box-shadow: 0 25px 50px rgba(0,0,0,0.5);">
    
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
      <div>
        <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; margin-bottom: 4px;" id="modalBadge">CHAPTER CALENDAR</span>
        <h2 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 0;" id="modalTitle">
          Schedule New Chapter Meeting
        </h2>
      </div>
      <button type="button" onclick="closeEventModal()" class="btn btn-sm btn-secondary" style="padding: 6px 10px;">
        <?php echo icon('x', '', 14); ?>
      </button>
    </div>

    <form method="post" action="events.php" id="eventForm">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" id="formAction" value="create_event">
      <input type="hidden" name="event_id" id="formEventId" value="">

      <!-- Meeting Title -->
      <div style="margin-bottom: 16px;">
        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
          Meeting / Event Title *
        </label>
        <input type="text" name="title" id="formTitle" placeholder="e.g. Special GMM on Architectural Heritage & RA 9266" required class="form-control" style="width: 100%; font-size: 14px; font-weight: 600; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
      </div>

      <!-- Type & Status Grid -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
            Meeting Type *
          </label>
          <select name="event_type" id="formEventType" class="form-control" style="width: 100%; font-size: 13px; font-weight: 600; padding: 9px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
            <option value="gmm">General Membership (GMM)</option>
            <option value="board_meeting">Chapter Board Meeting</option>
            <option value="cpd_seminar">CPD Technical Seminar</option>
            <option value="fellowship">Fellowship / Gathering</option>
            <option value="assembly">Special Assembly</option>
            <option value="major">Major Event / Milestone</option>
            <option value="special">Special National Program</option>
          </select>
        </div>

        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
            Status *
          </label>
          <select name="status" id="formStatus" class="form-control" style="width: 100%; font-size: 13px; font-weight: 600; padding: 9px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
            <option value="upcoming">Upcoming (Active)</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>

      <!-- Date & Times Grid -->
      <div style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 14px; margin-bottom: 16px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
            Meeting Date *
          </label>
          <input type="date" name="event_date" id="formEventDate" value="<?php echo date('Y-m-d'); ?>" required class="form-control" style="width: 100%; font-size: 13px; padding: 9px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
        </div>

        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
            Start Time
          </label>
          <input type="time" name="start_time" id="formStartTime" value="18:00" class="form-control" style="width: 100%; font-size: 13px; padding: 9px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
        </div>

        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
            End Time
          </label>
          <input type="time" name="end_time" id="formEndTime" value="21:00" class="form-control" style="width: 100%; font-size: 13px; padding: 9px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
        </div>
      </div>

      <!-- Location Type & Venue -->
      <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 14px; margin-bottom: 16px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
            Platform / Format *
          </label>
          <select name="location_type" id="formLocationType" onchange="handleLocationTypeChange(this.value)" class="form-control" style="width: 100%; font-size: 13px; padding: 9px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
            <option value="f2f">In-Person (F2F)</option>
            <option value="zoom">Virtual (Zoom/Meet)</option>
            <option value="hybrid">Hybrid (F2F + Zoom)</option>
          </select>
        </div>

        <div id="venueGroup">
          <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
            Physical Venue / Address
          </label>
          <input type="text" name="venue_name" id="formVenueName" placeholder="e.g. Lucena Grand Central Hotel" class="form-control" style="width: 100%; font-size: 13px; padding: 9px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
        </div>
      </div>

      <!-- Virtual Link (Zoom / Meet) -->
      <div style="margin-bottom: 16px;" id="linkGroup">
        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
          Zoom / Virtual Meeting Link (Optional)
        </label>
        <input type="url" name="meeting_link" id="formMeetingLink" placeholder="https://zoom.us/j/..." class="form-control" style="width: 100%; font-size: 13px; padding: 9px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
      </div>

      <!-- CPD Units & Standing Credit -->
      <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 14px; margin-bottom: 16px; align-items: center; background: rgba(0,0,0,0.15); padding: 14px; border-radius: 10px; border: 1px solid var(--border-color);">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">
            CPD Credit Units
          </label>
          <input type="number" step="0.5" name="cpd_units" id="formCpdUnits" value="0.0" class="form-control" style="width: 100%; font-size: 13px; padding: 8px 12px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;">
        </div>

        <div>
          <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 700; color: var(--text-primary); cursor: pointer; margin-top: 18px;">
            <input type="checkbox" name="counts_for_standing" id="formCountsForStanding" value="1" checked style="width: 18px; height: 18px; accent-color: #f59e0b;">
            <span>Counts toward 7+ Active Standing</span>
          </label>
        </div>
      </div>

      <!-- Description / Agenda -->
      <div style="margin-bottom: 22px;">
        <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">
          Agenda / Topics / Description
        </label>
        <textarea name="description" id="formDescription" rows="3" placeholder="Outline key topics, guest speaker, or agenda items for members..." class="form-control" style="width: 100%; font-size: 13px; padding: 10px 14px; background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px;"></textarea>
      </div>

      <!-- Modal Action Buttons -->
      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" onclick="closeEventModal()" class="btn btn-secondary" style="padding: 10px 18px;">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary" style="padding: 10px 22px; font-weight: 800;">
          <?php echo icon('check', '', 14); ?> <span id="submitBtnText">Save &amp; Schedule Meeting</span>
        </button>
      </div>
    </form>

  </div>
</div>

<script>
function openCreateModal() {
  document.getElementById('eventForm').reset();
  document.getElementById('formAction').value = 'create_event';
  document.getElementById('formEventId').value = '';
  document.getElementById('modalTitle').textContent = 'Schedule New Chapter Meeting';
  document.getElementById('modalBadge').textContent = 'NEW SCHEDULE';
  document.getElementById('submitBtnText').textContent = 'Save & Schedule Meeting';
  document.getElementById('formCountsForStanding').checked = true;
  document.getElementById('formEventDate').value = '<?php echo date("Y-m-d"); ?>';
  document.getElementById('eventModal').style.display = 'flex';
}

function openEditModal(eventData) {
  document.getElementById('formAction').value = 'edit_event';
  document.getElementById('formEventId').value = eventData.id;
  document.getElementById('modalTitle').textContent = 'Edit Meeting: ' + eventData.title;
  document.getElementById('modalBadge').textContent = 'EDITING SCHEDULE';
  document.getElementById('submitBtnText').textContent = 'Update Meeting Details';

  document.getElementById('formTitle').value = eventData.title || '';
  document.getElementById('formEventType').value = eventData.event_type || 'gmm';
  document.getElementById('formStatus').value = eventData.status || 'upcoming';
  document.getElementById('formEventDate').value = eventData.event_date || '';
  document.getElementById('formStartTime').value = eventData.start_time ? eventData.start_time.substring(0, 5) : '';
  document.getElementById('formEndTime').value = eventData.end_time ? eventData.end_time.substring(0, 5) : '';
  document.getElementById('formLocationType').value = eventData.location_type || 'f2f';
  document.getElementById('formVenueName').value = eventData.venue_name || '';
  document.getElementById('formMeetingLink').value = eventData.meeting_link || '';
  document.getElementById('formCpdUnits').value = eventData.cpd_units || '0.0';
  document.getElementById('formCountsForStanding').checked = parseInt(eventData.counts_for_standing) === 1;
  document.getElementById('formDescription').value = eventData.description || '';

  document.getElementById('eventModal').style.display = 'flex';
}

function closeEventModal() {
  document.getElementById('eventModal').style.display = 'none';
}

function handleLocationTypeChange(type) {
  const linkGroup = document.getElementById('linkGroup');
  if (type === 'f2f') {
    linkGroup.style.display = 'none';
  } else {
    linkGroup.style.display = 'block';
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

