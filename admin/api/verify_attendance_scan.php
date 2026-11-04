<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/attendance_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin authorization required.']);
    exit;
}

$adminId = current_user_id();

// Parse input (supports JSON or Form POST)
$rawInput = file_get_contents('php://input');
$inputData = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $inputData = $decoded;
    }
}
if (empty($inputData)) {
    $inputData = $_POST;
}

$action = $inputData['action'] ?? 'scan';
$eventKey = trim($inputData['event_key'] ?? '');
$mode = in_array($inputData['attendance_mode'] ?? '', ['f2f', 'zoom'], true) ? $inputData['attendance_mode'] : 'f2f';

if (empty($eventKey)) {
    $eventKey = get_default_event_key();
}

// -----------------------------------------------------------------------------
// ACTION A: MANUAL SEARCH / CHECK-IN BY PRC OR MEMBER ID
// -----------------------------------------------------------------------------
if ($action === 'manual_checkin') {
    $query = trim($inputData['search_query'] ?? '');
    if (empty($query)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please provide a PRC License Number or Member Name.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'member' AND (prc_number = ? OR id_number = ? OR name LIKE ?) LIMIT 1");
    $stmt->execute([$query, $query, "%$query%"]);
    $targetUserId = (int)$stmt->fetchColumn();

    if ($targetUserId <= 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "No member found matching '$query'."]);
        exit;
    }

    $res = record_event_scan_attendance($pdo, $targetUserId, $eventKey, $adminId, $mode, 'manual_override', 'Manual registration desk check-in');
    if (!empty($res['success'])) {
        @require_once __DIR__ . '/../../includes/social_notifier.php';
        if (function_exists('notify_admin_event')) {
            notify_admin_event($pdo, 'attendance', [
                'name' => $res['member']['name'] ?? 'Architect',
                'event_title' => $res['event']['title'] ?? $eventKey,
                'time' => date('h:i A'),
                'total_count' => $res['stats']['total_attendees'] ?? 0
            ]);
        }
    }
    echo json_encode($res);
    exit;
}

// -----------------------------------------------------------------------------
// ACTION B: VERIFY QR SCAN TOKEN
// -----------------------------------------------------------------------------
$token = trim($inputData['token'] ?? '');
if (empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No QR token provided in scan payload.']);
    exit;
}

// Verify token authenticity and timestamp
$verification = verify_rolling_attendance_token($token);

if (!$verification['valid']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'expired' => !empty($verification['expired']),
        'error'   => $verification['error'] ?? 'Invalid QR code.'
    ]);
    exit;
}

$userId = (int)$verification['user_id'];
$method = !empty($verification['is_static']) ? 'qr_scan_static' : 'qr_scan_rolling';

$res = record_event_scan_attendance($pdo, $userId, $eventKey, $adminId, $mode, $method);

if (!empty($res['success'])) {
    @require_once __DIR__ . '/../../includes/social_notifier.php';
    if (function_exists('notify_admin_event')) {
        notify_admin_event($pdo, 'attendance', [
            'name' => $res['member']['name'] ?? 'Architect',
            'event_title' => $res['event']['title'] ?? $eventKey,
            'time' => date('h:i A'),
            'total_count' => $res['stats']['total_attendees'] ?? 0
        ]);
    }
}

if (!empty($verification['warning']) && $res['success']) {
    $res['warning'] = $verification['warning'];
}

echo json_encode($res);
exit;

