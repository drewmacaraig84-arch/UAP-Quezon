<?php
// Bridge to the shared UAP-Quezon DB connection
// This file is in website/api/ which is 2 levels up from the UAP-Quezon root
$uapRoot = __DIR__ . '/../../includes/config.php';
if (!file_exists($uapRoot)) {
    http_response_code(500);
    echo json_encode(['error' => 'Config not found']);
    exit;
}
require_once $uapRoot;

// Release PHP session lock immediately so parallel public API requests don't block each other
if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}

// CORS headers for Vite dev server
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['http://localhost:5173', 'http://127.0.0.1:5173'];
if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
