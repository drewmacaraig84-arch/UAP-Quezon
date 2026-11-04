CREATE TABLE IF NOT EXISTS chapter_officers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(150) NOT NULL,
    title_prefix VARCHAR(50) NOT NULL DEFAULT 'Ar.',
    title_suffix VARCHAR(50) NOT NULL DEFAULT 'uap',
    position VARCHAR(150) NOT NULL,
    committee VARCHAR(150) NULL,
    fiscal_year VARCHAR(30) NOT NULL DEFAULT '2026-2027',
    photo_path VARCHAR(255) NULL,
    bio TEXT NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_officers_fy_active (fiscal_year, is_active, display_order),
    INDEX idx_officers_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
