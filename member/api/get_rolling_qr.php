<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/attendance_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$userId = current_user_id();
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid member account.']);
    exit;
}

$tokenData = generate_rolling_attendance_token($userId);

echo json_encode([
    'success'     => true,
    'token'       => $tokenData['token'],
    'expires_in'  => $tokenData['expires_in'],
    'expires_at'  => $tokenData['expires_at'],
    'server_time' => $tokenData['server_time'],
]);
exit;

