<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qr_helper.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid directory profile ID.");
}

$stmt = $pdo->prepare("SELECT * FROM website_members WHERE id = ? OR user_id = ?");
$stmt->execute([$id, $id]);
$wm = $stmt->fetch();

if (!$wm) {
    die("Directory profile not found.");
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
