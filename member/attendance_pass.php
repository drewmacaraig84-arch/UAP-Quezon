<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_member();

$userId = current_user_id();

// Load member details
$uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

// Try to get photo from website_members if not in users
$photoPath = $user['profile_photo'] ?? null;
try {
    $wmStmt = $pdo->prepare("SELECT photo_path FROM website_members WHERE user_id = ?");
    $wmStmt->execute([$userId]);
    $wmPhoto = $wmStmt->fetchColumn();
    if ($wmPhoto && (!$photoPath || !file_exists(__DIR__ . '/../' . ltrim($photoPath, '/')))) {
        $photoPath = $wmPhoto;
    }
} catch (Throwable $e) {}

$avatarUrl = $photoPath ? (str_starts_with($photoPath, 'http') ? $photoPath : BASE_URL . '/' . ltrim($photoPath, '/')) : null;
$userInitials = strtoupper(substr($user['name'], 0, 1) . substr(strrchr($user['name'], ' ') ?: $user['name'], 1, 1));

// Attendance standing
$attendance = get_or_create_member_attendance($pdo, $userId);
$computed = $attendance['computed'];

$page_title = 'Digital Event Pass • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">OFFICIAL CHAPTER PASS &bull; ANTI-FRAUD VERIFIED</p>
    <h1>Digital Attendance Pass</h1>
    <p class="page-subtitle">Present this live screen at chapter registration desks to verify your attendance.</p>
  </div>
  <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
    <a href="chapter_status.php" style="text-decoration: none;">
      <?php echo render_activity_badge($attendance, true); ?>
    </a>
  </div>
</div>

<div style="max-width: 520px; margin: 0 auto 40px;">
  <!-- DIGITAL PASS CARD -->
  <div class="card" style="padding: 0; overflow: hidden; border: 2px solid rgba(245, 158, 11, 0.4); box-shadow: 0 20px 45px rgba(0,0,0,0.45); background: var(--card-bg); border-radius: 20px;">
    
    <!-- Pass Header -->
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 22px 24px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 14px;">
        <img src="<?php echo BASE_URL; ?>/public/logo.jpg" alt="Logo" style="width: 44px; height: 44px; border-radius: 10px; border: 2px solid #f59e0b; object-fit: cover;">
        <div>
          <div style="font-size: 11px; font-weight: 800; letter-spacing: 1px; color: #f59e0b; text-transform: uppercase;">United Architects of the Philippines</div>
          <div style="font-size: 17px; font-weight: 800; color: #ffffff; letter-spacing: -0.3px;">QUEZON PROVINCE CHAPTER</div>
        </div>
      </div>
      <!-- Live Indicator Pulse -->
      <div style="display: flex; align-items: center; gap: 6px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); padding: 5px 10px; border-radius: 999px;">
        <span class="live-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block; animation: pulseGlow 1.6s infinite ease-in-out;"></span>
        <span style="font-size: 11px; font-weight: 800; color: #10b981; letter-spacing: 0.5px;">LIVE PASS</span>
      </div>
    </div>

    <!-- Member Identity Strip -->
    <div style="padding: 24px 24px 18px; display: flex; align-items: center; gap: 18px; border-bottom: 1px dashed rgba(255,255,255,0.12); background: rgba(15, 23, 42, 0.4);">
      <div style="position: relative;">
        <?php if ($avatarUrl): ?>
          <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Photo" style="width: 72px; height: 72px; border-radius: 16px; object-fit: cover; border: 2px solid #f59e0b; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
        <?php else: ?>
          <div style="width: 72px; height: 72px; border-radius: 16px; background: linear-gradient(135deg, #f59e0b, #15803d); color: white; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800; border: 2px solid #f59e0b;">
            <?php echo $userInitials; ?>
          </div>
        <?php endif; ?>
        <span style="position: absolute; bottom: -4px; right: -4px; background: #10b981; border: 2px solid var(--card-bg); width: 14px; height: 14px; border-radius: 50%;" title="Verified Member"></span>
      </div>
      
      <div style="flex: 1; min-width: 0;">
        <span style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Registered Architect</span>
        <h2 style="font-size: 18px; font-weight: 800; color: var(--text-primary); margin: 2px 0 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
          <?php echo htmlspecialchars($user['name']); ?>
        </h2>
        <div style="display: flex; gap: 10px; font-size: 12px; color: var(--text-secondary); flex-wrap: wrap;">
          <span><strong>PRC No:</strong> <?php echo htmlspecialchars($user['prc_number'] ?: '—'); ?></span>
          <span>&bull;</span>
          <span><strong>UAP ID:</strong> <?php echo htmlspecialchars($user['id_number'] ?: '—'); ?></span>
        </div>
      </div>
    </div>

    <!-- Live Dynamic QR Section -->
    <div style="padding: 28px 24px 22px; text-align: center; background: radial-gradient(circle at 50% 50%, rgba(245,158,11,0.06) 0%, transparent 70%);">
      
      <!-- Current Date & Live Clock -->
      <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); padding: 6px 14px; border-radius: 999px; margin-bottom: 20px; font-size: 12.5px; color: var(--text-secondary);">
        <?php echo icon('clock', '', 14); ?>
        <span id="liveClockDisplay"><?php echo date('l, F j, Y &bull; h:i:s A'); ?></span>
      </div>

      <!-- QR Box Container -->
      <div style="display: inline-block; padding: 16px; background: #ffffff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.35); position: relative;">
        <div id="qrcodeCanvas" style="width: 220px; height: 220px; display: flex; align-items: center; justify-content: center;">
          <div class="spinner" style="width: 32px; height: 32px; border: 3px solid rgba(0,0,0,0.1); border-top-color: #f59e0b; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
        </div>
      </div>

      <!-- Rotating Countdown Timer -->
      <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 22px;">
        <!-- Circular SVG Timer -->
        <div style="position: relative; width: 44px; height: 44px;">
          <svg style="transform: rotate(-90deg); width: 44px; height: 44px;">
            <circle cx="22" cy="22" r="18" stroke="rgba(255,255,255,0.1)" stroke-width="3.5" fill="none" />
            <circle id="timerProgressCircle" cx="22" cy="22" r="18" stroke="#f59e0b" stroke-width="3.5" stroke-linecap="round" fill="none" stroke-dasharray="113.09" stroke-dashoffset="0" style="transition: stroke-dashoffset 1s linear, stroke 0.3s ease;" />
          </svg>
          <span id="countdownNumber" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; color: #f59e0b;">60</span>
        </div>
        
        <div style="text-align: left;">
          <div style="font-size: 12.5px; font-weight: 700; color: var(--text-primary);">Dynamic Rolling Code</div>
          <div style="font-size: 11px; color: var(--text-secondary);" id="countdownStatusText">Refreshes in <span id="countdownSecondsText">60</span>s to prevent fraud</div>
        </div>

        <button type="button" onclick="manualRefreshPass()" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: var(--text-primary); border-radius: 8px; padding: 6px 10px; font-size: 11px; cursor: pointer; display: flex; align-items: center; gap: 5px; margin-left: 8px;" title="Refresh QR code now">
          <?php echo icon('clock', '', 12); ?> Refresh
        </button>
      </div>

    </div>

    <!-- Security Advisory Note -->
    <div style="background: rgba(15, 23, 42, 0.7); padding: 14px 20px; border-top: 1px solid rgba(255,255,255,0.08); font-size: 11.5px; color: var(--text-secondary); display: flex; align-items: center; gap: 10px;">
      <div style="color: #f59e0b; flex-shrink: 0;">
        <?php echo icon('shield_check', '', 18); ?>
      </div>
      <div>
        <strong>Anti-Proxy Security:</strong> This QR code continuously rolls and expires. Screenshots or forwarded photos are automatically rejected by the registration scanner.
      </div>
    </div>
  </div>

  <div style="margin-top: 20px; text-align: center; display: flex; justify-content: center; gap: 12px;">
    <a href="dashboard.php" class="btn btn-secondary">
      <?php echo icon('dashboard', '', 14); ?> Back to Dashboard
    </a>
    <a href="chapter_status.php" class="btn btn-outline" style="border-color: rgba(245,158,11,0.4); color: #f59e0b;">
      <?php echo icon('check', '', 14); ?> View Attendance Record
    </a>
  </div>
</div>

<style>
@keyframes pulseGlow {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
  70% { transform: scale(1.1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>

<!-- Load standalone QRCode.js -->
<script src="<?php echo BASE_URL; ?>/public/js/qrcode.min.js"></script>

<script>
let qrCodeInstance = null;
let currentToken = null;
let countdownRemaining = 60;
let countdownInterval = null;
const TOTAL_CYCLE = 60;
const CIRCLE_CIRCUMFERENCE = 2 * Math.PI * 18; // ~113.097

// Live clock updates every second
function updateLiveClock() {
  const clockEl = document.getElementById('liveClockDisplay');
  if (!clockEl) return;
  const now = new Date();
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
  clockEl.textContent = now.toLocaleDateString('en-US', options).replace(/,([^,]*)$/, ' •$1');
}
setInterval(updateLiveClock, 1000);

// Fetch fresh rolling token from server
async function fetchFreshToken() {
  try {
    const res = await fetch('<?php echo BASE_URL; ?>/member/api/get_rolling_qr.php', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!res.ok) throw new Error('Network error fetching pass');
    const data = await res.json();
    if (data.success && data.token) {
      renderQRCode(data.token);
      resetCountdown(data.expires_in || 60);
    }
  } catch (err) {
    console.error('Pass update error:', err);
  }
}

function renderQRCode(tokenString) {
  currentToken = tokenString;
  const container = document.getElementById('qrcodeCanvas');
  container.innerHTML = '';
  qrCodeInstance = new QRCode(container, {
    text: tokenString,
    width: 220,
    height: 220,
    colorDark: '#0f172a',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M
  });
}

function resetCountdown(seconds) {
  countdownRemaining = seconds;
  updateTimerUI();
  clearInterval(countdownInterval);
  countdownInterval = setInterval(() => {
    countdownRemaining--;
    if (countdownRemaining <= 0) {
      clearInterval(countdownInterval);
      fetchFreshToken();
    } else {
      updateTimerUI();
    }
  }, 1000);
}

function updateTimerUI() {
  const numEl = document.getElementById('countdownNumber');
  const txtEl = document.getElementById('countdownSecondsText');
  const circleEl = document.getElementById('timerProgressCircle');

  if (numEl) numEl.textContent = countdownRemaining;
  if (txtEl) txtEl.textContent = countdownRemaining;

  if (circleEl) {
    const fraction = countdownRemaining / TOTAL_CYCLE;
    const offset = CIRCLE_CIRCUMFERENCE * (1 - fraction);
    circleEl.style.strokeDashoffset = offset;
    // Turn amber/red when under 10 seconds
    if (countdownRemaining <= 10) {
      circleEl.style.stroke = '#ef4444';
      if (numEl) numEl.style.color = '#ef4444';
    } else {
      circleEl.style.stroke = '#f59e0b';
      if (numEl) numEl.style.color = '#f59e0b';
    }
  }
}

function manualRefreshPass() {
  clearInterval(countdownInterval);
  document.getElementById('countdownNumber').textContent = '...';
  fetchFreshToken();
}

// Initial load
window.addEventListener('DOMContentLoaded', () => {
  fetchFreshToken();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

