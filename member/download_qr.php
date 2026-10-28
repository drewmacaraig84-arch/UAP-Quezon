<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qr_helper.php';
require_member();

$userId = current_user_id();

// Find website directory profile for current member
$stmt = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ?");
$stmt->execute([$userId]);
$wm = $stmt->fetch();

if (!$wm) {
    die("No directory profile found for your account.");
}

// Ensure the member's website directory application is paid/unlocked
if (function_exists('has_unlocked_website_directory') && !has_unlocked_website_directory($pdo, $userId)) {
    die("Your website directory application is currently pending approval or payment. Your public QR code will be available once unlocked.");
}

// Ensure member is in good standing
if (function_exists('is_good_member') && !is_good_member($pdo, $userId)) {
    die("Your member standing is currently revoked or on administrative hold. QR code download is suspended.");
}

// Generate or fetch QR code relative path (force fresh generation with live domain)
$qrPath = generate_member_directory_qr($pdo, (int)$wm['id'], true);
if (!$qrPath) {
    die("Unable to generate QR code at this time.");
}

$fullPath = __DIR__ . '/../' . ltrim(str_replace('\\', '/', $qrPath), '/');

if (!file_exists($fullPath)) {
    die("QR Code file not found on server.");
}

$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $wm['name'] ?: 'Member');
$downloadFilename = "UAP-Quezon-QR-{$safeName}.png";

header('Content-Description: File Transfer');
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));

readfile($fullPath);
exit;
