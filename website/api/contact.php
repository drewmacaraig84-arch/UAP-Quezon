<?php
/**
 * API: /api/contact.php
 * Handles contact inquiries submitted from the public website contact form.
 */
require_once __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Only POST is accepted.']);
    exit;
}

// Support both JSON payload and standard form post
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$subject = trim($data['subject'] ?? '');
$message = trim($data['message'] ?? '');

if (empty($name)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please provide your full name.']);
    exit;
}

if (empty($email) && empty($phone)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please provide at least one contact method (Email address or Mobile/Contact number).']);
    exit;
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please provide a valid email address.']);
    exit;
}

if (empty($message)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter your message.']);
    exit;
}

try {
    // Ensure table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_inquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NULL,
            phone VARCHAR(50) NULL,
            subject VARCHAR(255) NULL,
            message TEXT NOT NULL,
            status ENUM('unread', 'read', 'replied', 'archived') NOT NULL DEFAULT 'unread',
            admin_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_inquiries_status (status),
            INDEX idx_inquiries_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        INSERT INTO contact_inquiries (name, email, phone, subject, message, status)
        VALUES (?, ?, ?, ?, ?, 'unread')
    ");
    $stmt->execute([
        $name,
        !empty($email) ? $email : null,
        !empty($phone) ? $phone : null,
        !empty($subject) ? $subject : 'Website Inquiry',
        $message
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for reaching out! Your inquiry has been sent to the UAP Quezon Province Chapter Secretariat. We will get back to you shortly.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Unable to send message at this time. Please try again or contact us directly via email.'
    ]);
}
