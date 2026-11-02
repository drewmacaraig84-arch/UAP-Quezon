-- Migration 022: Create event_attendance_logs table
-- Records individual check-in events via QR scanning or manual verification with timestamps and audit trail.

CREATE TABLE IF NOT EXISTS event_attendance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    fiscal_year VARCHAR(20) NOT NULL DEFAULT '2026-2027',
    event_key VARCHAR(50) NOT NULL,
    event_title VARCHAR(255) NOT NULL,
    attendance_mode ENUM('f2f', 'zoom') NOT NULL DEFAULT 'f2f',
    scanned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scanned_by INT NULL,
    verification_method VARCHAR(50) NOT NULL DEFAULT 'qr_scan',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_event_fy (user_id, fiscal_year, event_key),
    KEY idx_event_key (event_key),
    KEY idx_fiscal_year (fiscal_year),
    KEY idx_scanned_at (scanned_at),
    KEY idx_user_id (user_id),
    CONSTRAINT fk_attendance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_admin FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
