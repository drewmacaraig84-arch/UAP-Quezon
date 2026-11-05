<?php
/**
 * Safe Image Streamer for Chapter Bulletins & Announcements
 * Bypasses static asset security barriers (such as aes.js challenges) on shared hosting environments like InfinityFree.
 */
require_once __DIR__ . '/../includes/config.php';

$file = $_GET['file'] ?? '';
$file = str_replace(['..', '\\'], ['', '/'], $file);
$file = ltrim($file, '/');

// Only allow serving from uploads/news/ or uploads/
if (!str_starts_with($file, 'uploads/news/') && !str_starts_with($file, 'uploads/')) {
    http_response_code(403);
    exit('Access denied');
}

$fullPath = realpath(__DIR__ . '/../' . $file);
$allowedRoot = realpath(__DIR__ . '/../uploads');

if (!$fullPath || !$allowedRoot || !str_starts_with($fullPath, $allowedRoot) || !is_file($fullPath)) {
    http_response_code(404);
    // Serve fallback 1x1 transparent png
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    exit;
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeMap = [
    'webp' => 'image/webp',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml'
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// Set clean caching and binary delivery headers
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
header('Accept-Ranges: bytes');

readfile($fullPath);
exit;
