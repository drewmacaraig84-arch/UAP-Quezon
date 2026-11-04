<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/plain');

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $stmt = $pdo->prepare("
        INSERT INTO site_settings (setting_key, setting_value) 
        VALUES ('website_directory_fee', '1000.00')
        ON DUPLICATE KEY UPDATE setting_value = '1000.00'
    ");
    $stmt->execute();

    echo "OK: Default website directory fee updated to 1000.00\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

