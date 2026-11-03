<?php
/**
 * Direct One-Click Online Meeting Join & Attendance Logger
 * UAP Quezon Province Chapter Portal
 *
 * When an architect member clicks "Join Zoom & Check In" from their portal:
 * 1. Verifies member authentication
 * 2. Fetches the scheduled event & meeting link
 * 3. Records official attendance with mode = 'zoom' and method = 'portal_one_click'
 * 4. Redirects to Zoom / Google Meet / MS Teams meeting URL
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';

require_member();

$currentUid = current_user_id();
$eventId = (int)($_GET['id'] ?? 0);

if ($eventId <= 0) {
    header('Location: ' . BASE_URL . '/member/events.php');
    exit;
}

ensure_chapter_events_schema($pdo);

// Fetch event details
$stmt = $pdo->prepare("
    SELECT * 
    FROM chapter_events 
    WHERE id = ? AND deleted_at IS NULL AND is_published = 1
    LIMIT 1
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header('Location: ' . BASE_URL . '/member/events.php?msg=event_not_found');
    exit;
}

$meetingLink = trim($event['meeting_link'] ?? '');

if (empty($meetingLink)) {
    header('Location: ' . BASE_URL . '/member/events.php?msg=no_link');
    exit;
}

// Ensure full URL scheme
if (!preg_match('~^(?:f|ht)tps?://~i', $meetingLink)) {
    $meetingLink = 'https://' . $meetingLink;
}

// Record verified online attendance
$eventKey = "ce_{$eventId}";
$logResult = record_event_scan_attendance(
    $pdo,
    $currentUid,
    $eventKey,
    null, // null adminId since self-logged via secure member portal
    'zoom',
    'portal_one_click',
    'One-click attendance verified via Member Portal Join Zoom button'
);

// Redirect to meeting
header('Location: ' . $meetingLink);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="1;url=<?php echo htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connecting to Meeting &bull; UAP Quezon</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0b1120;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #f1f5f9;
        }
        .container {
            max-width: 460px;
            margin: 20px;
            padding: 32px 28px;
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);
        }
        .check-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            border: 2px solid #10b981;
        }
        h2 {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
        }
        p {
            margin: 0 0 20px 0;
            font-size: 13.5px;
            line-height: 1.5;
            color: #94a3b8;
        }
        .btn-launch {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s ease;
        }
        .btn-launch:hover {
            background: #1d4ed8;
        }
        .event-box {
            background: rgba(0,0,0,0.25);
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #e2e8f0;
            border-left: 3px solid #f59e0b;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="check-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <h2>Attendance Confirmed!</h2>
        <p>Your online attendance has been verified and recorded to your Chapter Standing.</p>

        <div class="event-box">
            <strong><?php echo htmlspecialchars($event['title']); ?></strong>
            <div style="font-size: 12px; color: #94a3b8; margin-top: 4px;">
                Mode: Online / Zoom &bull; <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
            </div>
        </div>

        <a href="<?php echo htmlspecialchars($meetingLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn-launch">
            Click here to Launch Meeting &rarr;
        </a>
    </div>
</body>
</html>

