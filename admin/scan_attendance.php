<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_admin();

$fy = get_active_fiscal_year();
$selectedEventKey = trim($_GET['event_key'] ?? '');
if (empty($selectedEventKey)) {
    $selectedEventKey = get_default_event_key($pdo);
}
$eventCategories = get_available_chapter_events($pdo);
$selectedEventTitle = get_event_title_by_key($selectedEventKey, $pdo);

// Fetch active event metadata if this is a chapter_events meeting
$activeEventMeta = null;
$isActiveEventToday = false;
if (str_starts_with($selectedEventKey, 'ce_')) {
    try {
        $ceId = (int)substr($selectedEventKey, 3);
        $ceStmt = $pdo->prepare("SELECT * FROM chapter_events WHERE id = ?");
        $ceStmt->execute([$ceId]);
        $activeEventMeta = $ceStmt->fetch(PDO::FETCH_ASSOC);
        if ($activeEventMeta && $activeEventMeta['event_date'] === date('Y-m-d')) {
            $isActiveEventToday = true;
        }
    } catch (Throwable $e) {}
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $logs = get_event_attendance_logs($pdo, $selectedEventKey, $fy, 1000);
    $filename = "UAP-Quezon-Attendance-" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $selectedEventKey) . "-" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['UAP QUEZON PROVINCE CHAPTER - OFFICIAL ATTENDANCE ROSTER']);
    fputcsv($out, ['Event:', $selectedEventTitle]);
    fputcsv($out, ['Fiscal Year:', $fy]);
    fputcsv($out, ['Export Date:', date('F j, Y - h:i A')]);
    fputcsv($out, ['Total Attendees:', count($logs)]);
    fputcsv($out, []); // blank line
    fputcsv($out, ['No.', 'Architect Full Name', 'PRC License No.', 'UAP Member ID', 'Attendance Mode', 'Check-In Timestamp', 'Verification Method', 'Verified By']);

    $num = 1;
    foreach ($logs as $row) {
        fputcsv($out, [
            $num++,
            $row['name'],
            $row['prc_number'] ?: '—',
            $row['id_number'] ?: '—',
            strtoupper($row['attendance_mode']),
            date('Y-m-d h:i:s A', strtotime($row['scanned_at'])),
            $row['verification_method'] === 'manual_override' ? 'Manual Override' : 'QR Code Scan',
            $row['admin_name'] ?: 'System Admin'
        ]);
    }
    fclose($out);
    exit;
}

// Fetch recent attendance logs for the active event
$recentLogs = get_event_attendance_logs($pdo, $selectedEventKey, $fy, 100);
$totalAttendeesCount = count($recentLogs);

$page_title = 'QR Attendance Scanner • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">
      MEMBERSHIP COMPLIANCE &bull; TODAY: <strong style="color: #f59e0b;"><?php echo date('l, F j, Y'); ?></strong>
    </p>
    <h1>QR Attendance Scanner</h1>
    <p class="page-subtitle">Scan member digital passes for real-time verification, photo audit, and duplicate prevention.</p>
  </div>
  <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
    <a href="events.php" class="btn btn-sm btn-primary" style="font-weight: 800; display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; text-decoration: none; border-radius: 8px; box-shadow: 0 4px 14px rgba(245,158,11,0.35);">
      <?php echo icon('plus', '', 14); ?> <span>+ Create New Event</span>
    </a>
    <div class="hero-badge" style="background: rgba(245,158,11,0.12); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3);">
      <?php echo icon('check', '', 14); ?> <span id="attendeeCountBadge"><?php echo $totalAttendeesCount; ?> Present</span>
    </div>
    <a href="attendance.php" class="btn btn-sm btn-secondary">
      <?php echo icon('members', '', 12); ?> Attendance Manager
    </a>
  </div>
</div>

<!-- ============================================================== -->
<!-- TOP CONTROL BAR: EVENT SELECTOR & CAMERA TOGGLES               -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
  <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 16px; align-items: center; flex-wrap: wrap;" class="scanner-controls-grid">
    
    <!-- Event Selector -->
    <div>
      <label style="display: block; font-size: 11px; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
        Active Event / Meeting Roster
      </label>
      <div style="display: flex; gap: 10px; align-items: center;">
        <select id="eventKeySelect" onchange="changeActiveEvent(this.value)" class="form-control" style="font-weight: 700; font-size: 14px; background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color); padding: 10px 14px; border-radius: 8px; width: 100%; max-width: 440px;">
          <?php foreach ($eventCategories as $catKey => $cat): ?>
            <optgroup label="<?php echo htmlspecialchars($cat['title']); ?>">
              <?php foreach ($cat['events'] as $eKey => $eMeta): ?>
                <option value="<?php echo $eKey; ?>" <?php echo $selectedEventKey === $eKey ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($eMeta['label']); ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        
        <!-- Attendance Mode (F2F vs Zoom) -->
        <select id="attendanceModeSelect" class="form-control" style="font-size: 13px; font-weight: 600; width: auto; background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color); padding: 10px 12px; border-radius: 8px;">
          <option value="f2f" <?php echo ($activeEventMeta && ($activeEventMeta['location_type'] ?? '') === 'zoom') ? '' : 'selected'; ?>>Face-to-Face (F2F)</option>
          <option value="zoom" <?php echo ($activeEventMeta && ($activeEventMeta['location_type'] ?? '') === 'zoom') ? 'selected' : ''; ?>>Zoom Webinar</option>
        </select>
      </div>
    </div>

    <!-- Sound Toggle -->
    <div>
      <label style="display: block; font-size: 11px; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
        Audio Feedback
      </label>
      <button type="button" id="soundToggleBtn" onclick="toggleAudioFeedback()" class="btn btn-secondary" style="display: flex; align-items: center; gap: 6px; padding: 9px 14px;">
        <span id="soundIcon"><?php echo icon('activity', '', 14); ?></span>
        <span id="soundText">Sound: ON</span>
      </button>
    </div>

    <!-- Export Button -->
    <div>
      <label style="display: block; font-size: 11px; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
        Official Roster
      </label>
      <a href="scan_attendance.php?export=csv&event_key=<?php echo urlencode($selectedEventKey); ?>" class="btn btn-primary" style="display: flex; align-items: center; gap: 6px; padding: 9px 16px;">
        <?php echo icon('download', '', 14); ?> Export to CSV
      </a>
    </div>

    <!-- Active Event Confirmation Badge Strip -->
    <div style="grid-column: 1 / -1; background: <?php echo $isActiveEventToday ? 'rgba(16,185,129,0.08)' : 'rgba(245,158,11,0.08)'; ?>; border: 1px solid <?php echo $isActiveEventToday ? 'rgba(16,185,129,0.35)' : 'rgba(245,158,11,0.35)'; ?>; border-radius: 10px; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
      <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <span class="badge" style="background: <?php echo $isActiveEventToday ? '#10b981' : '#f59e0b'; ?>; color: #ffffff; font-weight: 800; font-size: 11px; padding: 4px 9px; letter-spacing: 0.5px;">
          <?php echo $isActiveEventToday ? '🔴 LIVE EVENT TODAY' : 'ACTIVE TARGET ROSTER'; ?>
        </span>
        <strong style="color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($selectedEventTitle); ?></strong>
        <?php if ($activeEventMeta): ?>
          <span style="font-size: 12.5px; color: var(--text-secondary);">
            &bull; <?php echo date('l, M j, Y', strtotime($activeEventMeta['event_date'])); ?>
            <?php if (!empty($activeEventMeta['start_time'])): ?>
              (<?php echo date('h:i A', strtotime($activeEventMeta['start_time'])); ?><?php echo !empty($activeEventMeta['end_time']) ? ' - ' . date('h:i A', strtotime($activeEventMeta['end_time'])) : ''; ?>)
            <?php endif; ?>
            &bull; 📍 <?php echo htmlspecialchars($activeEventMeta['venue_name'] ?: ($activeEventMeta['location_type'] === 'zoom' ? 'Zoom Webinar' : 'Chapter Venue')); ?>
          </span>
        <?php endif; ?>
      </div>
      <div style="font-size: 12px; color: #10b981; font-weight: 700; display: flex; align-items: center; gap: 5px;">
        <?php echo icon('check', '', 13); ?> Attendance scans will credit directly to this meeting
      </div>
    </div>

  </div>
</div>

<!-- ============================================================== -->
<!-- MAIN SCANNER & VERIFICATION LAYOUT                             -->
<!-- ============================================================== -->
<div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 24px; margin-bottom: 30px;" class="scanner-main-layout">
  
  <!-- LEFT PANEL: CAMERA SCANNER & MANUAL OVERRIDE -->
  <div class="card" style="margin: 0; padding: 22px; display: flex; flex-direction: column;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
      <h2 style="font-size: 16px; font-weight: 800; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 8px;">
        <?php echo icon('qr_codes', '', 18); ?> Live Camera Scanner
      </h2>
      <div style="display: flex; align-items: center; gap: 8px;">
        <select id="cameraDeviceSelect" style="font-size: 12px; padding: 4px 8px; border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color); max-width: 170px;">
          <option value="">Detecting cameras...</option>
        </select>
        <button type="button" id="startStopBtn" onclick="toggleScannerState()" class="btn btn-sm btn-secondary" style="font-size: 11px; padding: 5px 10px;">
          Pause
        </button>
      </div>
    </div>

    <!-- Viewfinder Container -->
    <div style="position: relative; border-radius: 16px; overflow: hidden; background: #0b1120; border: 2px solid var(--border-color); min-height: 290px; display: flex; align-items: center; justify-content: center;">
      <div id="reader" style="width: 100%;"></div>
      
      <!-- Target Overlay Grid Lines -->
      <div id="scannerOverlay" style="position: absolute; inset: 0; pointer-events: none; display: flex; align-items: center; justify-content: center;">
        <div style="width: 220px; height: 220px; border: 2px dashed rgba(245,158,11,0.7); border-radius: 16px; box-shadow: 0 0 0 9999px rgba(0,0,0,0.35); position: relative;">
          <div style="position: absolute; top: 0; left: 0; width: 24px; height: 24px; border-top: 4px solid #f59e0b; border-left: 4px solid #f59e0b; border-top-left-radius: 12px;"></div>
          <div style="position: absolute; top: 0; right: 0; width: 24px; height: 24px; border-top: 4px solid #f59e0b; border-right: 4px solid #f59e0b; border-top-right-radius: 12px;"></div>
          <div style="position: absolute; bottom: 0; left: 0; width: 24px; height: 24px; border-bottom: 4px solid #f59e0b; border-left: 4px solid #f59e0b; border-bottom-left-radius: 12px;"></div>
          <div style="position: absolute; bottom: 0; right: 0; width: 24px; height: 24px; border-bottom: 4px solid #f59e0b; border-right: 4px solid #f59e0b; border-bottom-right-radius: 12px;"></div>
        </div>
      </div>

      <!-- Scanner Status Text -->
      <div id="scannerStatusBadge" style="position: absolute; bottom: 12px; background: rgba(0,0,0,0.75); color: #94a3b8; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 999px; backdrop-filter: blur(4px);">
        Align member QR pass within frame
      </div>
    </div>

    <!-- Manual Check-in Fallback Bar -->
    <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border-color);">
      <div style="display: flex; gap: 8px;">
        <input type="text" id="manualQueryInput" placeholder="PRC Number or Member Name..." class="form-control" style="font-size: 13px; padding: 8px 12px; background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 8px; flex: 1;">
        <button type="button" onclick="submitManualCheckin()" class="btn btn-secondary" style="font-size: 12.5px; padding: 8px 14px; white-space: nowrap;">
          <?php echo icon('check', '', 12); ?> Manual Check-in
        </button>
      </div>
      <span style="font-size: 11px; color: var(--text-secondary); margin-top: 4px; display: block;">
        Backup for members whose phone battery died or forgot their pass.
      </span>
    </div>

  </div>

  <!-- RIGHT PANEL: VERIFICATION & PHOTO DISPLAY -->
  <div class="card" style="margin: 0; padding: 22px; display: flex; flex-direction: column; justify-content: center; min-height: 380px;" id="verificationDisplayCard">
    
    <!-- Empty Idle State -->
    <div id="idleStateContainer" style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
      <div style="width: 72px; height: 72px; margin: 0 auto 16px; border-radius: 50%; background: rgba(245,158,11,0.08); display: flex; align-items: center; justify-content: center; color: #f59e0b;">
        <?php echo icon('qr_codes', '', 36); ?>
      </div>
      <h3 style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin: 0 0 6px;">Ready to Scan</h3>
      <p style="font-size: 13px; max-width: 300px; margin: 0 auto; line-height: 1.5;">
        Point the camera at an architect's digital pass to verify identity and record attendance.
      </p>
    </div>

    <!-- Active Scan Result Banner (Initially Hidden) -->
    <div id="scanResultContainer" style="display: none; animation: fadeIn 0.3s ease-out;">
      
      <!-- Status Alert Banner -->
      <div id="resultStatusAlert" style="padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 14px;">
        <span id="resultStatusIcon"></span>
        <span id="resultStatusText">ATTENDANCE CONFIRMED</span>
      </div>

      <!-- Architect Identity Card -->
      <div style="display: flex; gap: 20px; align-items: center; padding: 18px; border-radius: 16px; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); margin-bottom: 18px;">
        <img id="resultMemberPhoto" src="" alt="Photo" style="width: 88px; height: 88px; border-radius: 16px; object-fit: cover; border: 3px solid #f59e0b; box-shadow: 0 4px 15px rgba(0,0,0,0.3); flex-shrink: 0;">
        <div id="resultPhotoPlaceholder" style="width: 88px; height: 88px; border-radius: 16px; background: linear-gradient(135deg, #f59e0b, #15803d); color: white; display: none; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; border: 3px solid #f59e0b; flex-shrink: 0;">
        </div>

        <div style="flex: 1; min-width: 0;">
          <span style="font-size: 11px; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Registered Architect</span>
          <h2 id="resultMemberName" style="font-size: 19px; font-weight: 800; color: var(--text-primary); margin: 3px 0 6px;">
            Ar. Juan Dela Cruz
          </h2>
          <div style="font-size: 12.5px; color: var(--text-secondary); margin-bottom: 8px;">
            <span><strong>PRC No:</strong> <span id="resultMemberPrc">0012345</span></span> &bull; 
            <span><strong>ID:</strong> <span id="resultMemberId">001</span></span>
          </div>
          <div>
            <span id="resultStandingBadge" class="badge"></span>
          </div>
        </div>
      </div>

      <!-- Scan Audit Metrics Strip -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px; background: rgba(0,0,0,0.25); padding: 12px 14px; border-radius: 10px; border: 1px solid var(--border-color);">
        <div>
          <span class="muted" style="display: block; font-size: 10.5px; text-transform: uppercase;">Check-in Timestamp</span>
          <strong id="resultScannedTime" style="color: var(--text-primary); font-size: 13px;">—</strong>
        </div>
        <div>
          <span class="muted" style="display: block; font-size: 10.5px; text-transform: uppercase;">Target Event</span>
          <strong id="resultEventTitle" style="color: #f59e0b; font-size: 12.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">—</strong>
        </div>
      </div>

    </div>

  </div>

</div>

<!-- ============================================================== -->
<!-- LIVE ATTENDEES TABLE FOR ACTIVE EVENT                          -->
<!-- ============================================================== -->
<div class="card" style="padding: 22px;">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h2 style="font-size: 17px; font-weight: 800; color: var(--text-primary); margin: 0;">
        Live Attendees Roster &bull; <span style="color: #f59e0b;"><?php echo htmlspecialchars($selectedEventTitle); ?></span>
      </h2>
      <span class="muted" style="font-size: 12px;">Official verified check-ins for the active event.</span>
    </div>
    <div>
      <span class="badge" style="background: rgba(16,185,129,0.12); color: #10b981; font-weight: 800; font-size: 12px; padding: 6px 12px;">
        <span id="tableCountBadge"><?php echo $totalAttendeesCount; ?></span> Total Verified
      </span>
    </div>
  </div>

  <div style="overflow-x: auto;">
    <table class="table" id="attendeesTable">
      <thead>
        <tr>
          <th style="width: 50px;">#</th>
          <th>Architect</th>
          <th>PRC License</th>
          <th>UAP ID</th>
          <th>Mode</th>
          <th>Check-in Time</th>
          <th>Method</th>
          <th>Verified By</th>
        </tr>
      </thead>
      <tbody id="attendeesTableBody">
        <?php if (empty($recentLogs)): ?>
          <tr id="emptyTableRow">
            <td colspan="8" style="text-align: center; padding: 32px; color: var(--text-secondary);">
              No attendees recorded for this event yet. Start scanning above!
            </td>
          </tr>
        <?php else: ?>
          <?php $idx = 1; foreach ($recentLogs as $log): 
            $p = $log['profile_photo'] ? (str_starts_with($log['profile_photo'], 'http') ? $log['profile_photo'] : BASE_URL . '/' . ltrim($log['profile_photo'], '/')) : null;
            $inits = strtoupper(substr($log['name'], 0, 1) . substr(strrchr($log['name'], ' ') ?: $log['name'], 1, 1));
          ?>
            <tr>
              <td style="font-weight: 700; color: var(--text-secondary);"><?php echo $idx++; ?></td>
              <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                  <?php if ($p): ?>
                    <img src="<?php echo htmlspecialchars($p); ?>" alt="Photo" style="width: 36px; height: 36px; border-radius: 8px; object-fit: cover; border: 1.5px solid #f59e0b;">
                  <?php else: ?>
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.2); color: #f59e0b; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 13px;">
                      <?php echo $inits; ?>
                    </div>
                  <?php endif; ?>
                  <div>
                    <strong style="color: var(--text-primary); font-size: 13.5px; display: block;"><?php echo htmlspecialchars($log['name']); ?></strong>
                  </div>
                </div>
              </td>
              <td><code><?php echo htmlspecialchars($log['prc_number'] ?: '—'); ?></code></td>
              <td><?php echo htmlspecialchars($log['id_number'] ?: '—'); ?></td>
              <td>
                <span class="badge" style="background: rgba(59,130,246,0.12); color: #3b82f6; font-size: 11px;">
                  <?php echo strtoupper($log['attendance_mode']); ?>
                </span>
              </td>
              <td style="font-weight: 600; color: var(--text-primary);">
                <?php echo date('h:i:s A', strtotime($log['scanned_at'])); ?>
              </td>
              <td>
                <span style="font-size: 11.5px; color: var(--text-secondary);">
                  <?php echo $log['verification_method'] === 'manual_override' ? 'Manual' : 'QR Scan'; ?>
                </span>
              </td>
              <td style="font-size: 12px; color: var(--text-secondary);">
                <?php echo htmlspecialchars($log['admin_name'] ?: 'Admin'); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 900px) {
  .scanner-main-layout { grid-template-columns: 1fr !important; }
  .scanner-controls-grid { grid-template-columns: 1fr !important; }
}
</style>

<!-- Standalone HTML5-QRCode Library -->
<script src="<?php echo BASE_URL; ?>/public/js/html5-qrcode.min.js"></script>

<script>
let html5QrCode = null;
let isScanning = false;
let isProcessingScan = false;
let soundEnabled = true;
let currentEventKey = '<?php echo $selectedEventKey; ?>';
let audioCtx = null;

// ==============================================================================
// WEB AUDIO API SOUND GENERATOR
// ==============================================================================
function getAudioContext() {
  if (!audioCtx) {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (AudioContextClass) audioCtx = new AudioContextClass();
  }
  if (audioCtx && audioCtx.state === 'suspended') {
    audioCtx.resume();
  }
  return audioCtx;
}

function playSuccessChime() {
  if (!soundEnabled) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;
    const now = ctx.currentTime;
    
    // Tone 1: 880 Hz (A5)
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(880, now);
    gain1.gain.setValueAtTime(0.15, now);
    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.12);
    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.start(now);
    osc1.stop(now + 0.12);

    // Tone 2: 1320 Hz (E6)
    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.type = 'sine';
    osc2.frequency.setValueAtTime(1320, now + 0.10);
    gain2.gain.setValueAtTime(0.18, now + 0.10);
    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.28);
    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.start(now + 0.10);
    osc2.stop(now + 0.28);
  } catch (e) {
    console.warn('Audio playback error:', e);
  }
}

function playErrorBuzz() {
  if (!soundEnabled) return;
  try {
    const ctx = getAudioContext();
    if (!ctx) return;
    const now = ctx.currentTime;
    
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(220, now);
    osc.frequency.setValueAtTime(180, now + 0.12);
    gain.gain.setValueAtTime(0.18, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.30);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(now);
    osc.stop(now + 0.30);
  } catch (e) {
    console.warn('Audio playback error:', e);
  }
}

function toggleAudioFeedback() {
  soundEnabled = !soundEnabled;
  const iconEl = document.getElementById('soundIcon');
  const txtEl = document.getElementById('soundText');
  if (soundEnabled) {
    txtEl.textContent = 'Sound: ON';
    getAudioContext();
    playSuccessChime();
  } else {
    txtEl.textContent = 'Sound: MUTED';
  }
}

// ==============================================================================
// CAMERA SCANNER INITIALIZATION
// ==============================================================================
async function initScanner() {
  try {
    const cameras = await Html5Qrcode.getCameras();
    const cameraSelect = document.getElementById('cameraDeviceSelect');
    cameraSelect.innerHTML = '';

    if (!cameras || cameras.length === 0) {
      cameraSelect.innerHTML = '<option value="">No camera detected</option>';
      document.getElementById('scannerStatusBadge').textContent = 'No camera detected on this device.';
      return;
    }

    let defaultCameraId = cameras[0].id;
    // Prefer back/rear camera if available on mobile
    cameras.forEach(cam => {
      const opt = document.createElement('option');
      opt.value = cam.id;
      opt.text = cam.label || `Camera ${cameraSelect.children.length + 1}`;
      if (cam.label.toLowerCase().includes('back') || cam.label.toLowerCase().includes('rear')) {
        defaultCameraId = cam.id;
        opt.selected = true;
      }
      cameraSelect.appendChild(opt);
    });

    cameraSelect.addEventListener('change', () => {
      stopAndRestartCamera(cameraSelect.value);
    });

    html5QrCode = new Html5Qrcode("reader");
    startScanning(defaultCameraId);
  } catch (err) {
    console.error('Camera init error:', err);
    document.getElementById('scannerStatusBadge').textContent = 'Camera permission required.';
  }
}

async function startScanning(cameraId) {
  if (isScanning || !html5QrCode) return;
  try {
    await html5QrCode.start(
      cameraId,
      {
        fps: 15,
        qrbox: { width: 220, height: 220 },
        aspectRatio: 1.0
      },
      onScanSuccess,
      onScanFailure
    );
    isScanning = true;
    document.getElementById('startStopBtn').textContent = 'Pause';
    document.getElementById('scannerStatusBadge').textContent = 'Scanner Active &bull; Align QR pass';
  } catch (err) {
    console.error('Failed to start scanning:', err);
  }
}

async function stopAndRestartCamera(newCameraId) {
  if (html5QrCode && isScanning) {
    await html5QrCode.stop();
    isScanning = false;
  }
  startScanning(newCameraId);
}

function toggleScannerState() {
  const btn = document.getElementById('startStopBtn');
  if (isScanning) {
    html5QrCode.stop().then(() => {
      isScanning = false;
      btn.textContent = 'Resume';
      document.getElementById('scannerStatusBadge').textContent = 'Scanner Paused';
    });
  } else {
    const sel = document.getElementById('cameraDeviceSelect');
    startScanning(sel.value).then(() => {
      btn.textContent = 'Pause';
    });
  }
}

function onScanFailure(error) {
  // Silent frame-by-frame non-match
}

async function onScanSuccess(decodedText, decodedResult) {
  if (isProcessingScan) return;
  isProcessingScan = true;

  // Visual scan pulse on viewfinder
  document.getElementById('scannerStatusBadge').textContent = 'Verifying pass...';
  
  await processAttendanceToken(decodedText);

  // Resume scanning after 2.0s cooldown
  setTimeout(() => {
    isProcessingScan = false;
    document.getElementById('scannerStatusBadge').textContent = 'Scanner Active &bull; Align QR pass';
  }, 2000);
}

// ==============================================================================
// ATTENDANCE VERIFICATION API CALL
// ==============================================================================
async function processAttendanceToken(tokenString) {
  const mode = document.getElementById('attendanceModeSelect').value;
  try {
    const response = await fetch('<?php echo BASE_URL; ?>/admin/api/verify_attendance_scan.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        action: 'scan',
        token: tokenString,
        event_key: currentEventKey,
        attendance_mode: mode
      })
    });

    const data = await response.json();

    if (data.success) {
      playSuccessChime();
      renderScanResult(data, 'success');
      appendAttendeeToTable(data.member, data.scanned_time, mode, 'QR Scan');
    } else if (data.duplicate) {
      playErrorBuzz();
      renderScanResult(data, 'duplicate');
    } else {
      playErrorBuzz();
      renderScanResult(data, 'error');
    }
  } catch (err) {
    playErrorBuzz();
    console.error('Attendance processing error:', err);
    renderScanResult({ error: 'Network communication failure.' }, 'error');
  }
}

// ==============================================================================
// MANUAL CHECK-IN FALLBACK
// ==============================================================================
async function submitManualCheckin() {
  const input = document.getElementById('manualQueryInput');
  const query = input.value.trim();
  if (!query) {
    alert('Please enter a PRC License Number or Architect Name.');
    input.focus();
    return;
  }

  const mode = document.getElementById('attendanceModeSelect').value;
  try {
    const response = await fetch('<?php echo BASE_URL; ?>/admin/api/verify_attendance_scan.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        action: 'manual_checkin',
        search_query: query,
        event_key: currentEventKey,
        attendance_mode: mode
      })
    });

    const data = await response.json();
    input.value = '';

    if (data.success) {
      playSuccessChime();
      renderScanResult(data, 'success');
      appendAttendeeToTable(data.member, data.scanned_time, mode, 'Manual');
    } else if (data.duplicate) {
      playErrorBuzz();
      renderScanResult(data, 'duplicate');
    } else {
      playErrorBuzz();
      renderScanResult(data, 'error');
    }
  } catch (err) {
    playErrorBuzz();
    alert('Failed to process manual check-in.');
  }
}

// ==============================================================================
// UI RENDERING: PHOTO POPUP & ATTENDEE LIST
// ==============================================================================
function renderScanResult(data, type) {
  document.getElementById('idleStateContainer').style.display = 'none';
  const container = document.getElementById('scanResultContainer');
  container.style.display = 'block';

  const alertBox = document.getElementById('resultStatusAlert');
  const alertText = document.getElementById('resultStatusText');
  const alertIcon = document.getElementById('resultStatusIcon');

  if (type === 'success') {
    alertBox.style.background = 'rgba(16, 185, 129, 0.15)';
    alertBox.style.color = '#10b981';
    alertBox.style.border = '1px solid rgba(16, 185, 129, 0.4)';
    alertText.textContent = 'ATTENDANCE CONFIRMED ✓';
    alertIcon.innerHTML = '<?php echo icon("check", "", 20); ?>';
  } else if (type === 'duplicate') {
    alertBox.style.background = 'rgba(245, 158, 11, 0.15)';
    alertBox.style.color = '#f59e0b';
    alertBox.style.border = '1px solid rgba(245, 158, 11, 0.4)';
    alertText.textContent = data.error || 'ALREADY CHECKED IN';
    alertIcon.innerHTML = '<?php echo icon("alert", "", 20); ?>';
  } else {
    alertBox.style.background = 'rgba(239, 68, 68, 0.15)';
    alertBox.style.color = '#ef4444';
    alertBox.style.border = '1px solid rgba(239, 68, 68, 0.4)';
    alertText.textContent = data.error || 'INVALID / EXPIRED PASS';
    alertIcon.innerHTML = '<?php echo icon("alert", "", 20); ?>';
  }

  // Populate Member Details if available
  if (data.member) {
    document.getElementById('resultMemberName').textContent = data.member.name;
    document.getElementById('resultMemberPrc').textContent = data.member.prc_number || '—';
    document.getElementById('resultMemberId').textContent = data.member.id_number || '—';

    const photoEl = document.getElementById('resultMemberPhoto');
    const placeholderEl = document.getElementById('resultPhotoPlaceholder');

    if (data.member.photo) {
      photoEl.src = data.member.photo;
      photoEl.style.display = 'block';
      placeholderEl.style.display = 'none';
    } else {
      photoEl.style.display = 'none';
      placeholderEl.style.display = 'flex';
      placeholderEl.textContent = data.member.name.charAt(0);
    }

    // Standing Badge
    const badgeEl = document.getElementById('resultStandingBadge');
    if (data.activity) {
      badgeEl.textContent = data.activity.badge_label;
      badgeEl.style.background = data.activity.badge_bg;
      badgeEl.style.color = data.activity.badge_color;
      badgeEl.style.border = '1px solid ' + data.activity.badge_border;
      badgeEl.style.fontWeight = '800';
    } else {
      badgeEl.textContent = 'MEMBER';
      badgeEl.style.background = 'rgba(245,158,11,0.15)';
      badgeEl.style.color = '#f59e0b';
    }

    document.getElementById('resultScannedTime').textContent = data.scanned_time || new Date().toLocaleTimeString();
    document.getElementById('resultEventTitle').textContent = data.event_title || 'Chapter Event';
  }
}

function appendAttendeeToTable(member, scannedTime, mode, method) {
  const tbody = document.getElementById('attendeesTableBody');
  const emptyRow = document.getElementById('emptyTableRow');
  if (emptyRow) emptyRow.remove();

  const tr = document.createElement('tr');
  tr.style.animation = 'fadeIn 0.4s ease-out';
  
  const countBadge = document.getElementById('tableCountBadge');
  const topBadge = document.getElementById('attendeeCountBadge');
  const currentCount = parseInt(countBadge.textContent || '0') + 1;
  countBadge.textContent = currentCount;
  topBadge.textContent = currentCount + ' Present';

  const avatar = member.photo 
    ? `<img src="${member.photo}" alt="Photo" style="width: 36px; height: 36px; border-radius: 8px; object-fit: cover; border: 1.5px solid #f59e0b;">`
    : `<div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.2); color: #f59e0b; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 13px;">${member.name.charAt(0)}</div>`;

  tr.innerHTML = `
    <td style="font-weight: 700; color: #10b981;">NEW</td>
    <td>
      <div style="display: flex; align-items: center; gap: 10px;">
        ${avatar}
        <div>
          <strong style="color: var(--text-primary); font-size: 13.5px; display: block;">${member.name}</strong>
        </div>
      </div>
    </td>
    <td><code>${member.prc_number || '—'}</code></td>
    <td>${member.id_number || '—'}</td>
    <td><span class="badge" style="background: rgba(59,130,246,0.12); color: #3b82f6; font-size: 11px;">${mode.toUpperCase()}</span></td>
    <td style="font-weight: 600; color: var(--text-primary);">${scannedTime}</td>
    <td><span style="font-size: 11.5px; color: var(--text-secondary);">${method}</span></td>
    <td style="font-size: 12px; color: var(--text-secondary);">You</td>
  `;

  tbody.insertBefore(tr, tbody.firstChild);
}

function changeActiveEvent(newKey) {
  window.location.href = 'scan_attendance.php?event_key=' + encodeURIComponent(newKey);
}

// Initial camera boot on page load
window.addEventListener('DOMContentLoaded', () => {
  initScanner();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

