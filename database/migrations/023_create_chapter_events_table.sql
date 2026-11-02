-- Migration 023: Create chapter_events table
-- Dynamic scheduling and tracking of Chapter Meetings, GMMs, CPD Seminars, Board Meetings, and Fellowships.

CREATE TABLE IF NOT EXISTS chapter_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    event_type ENUM('gmm', 'board_meeting', 'cpd_seminar', 'fellowship', 'assembly', 'major', 'special') NOT NULL DEFAULT 'gmm',
    fiscal_year VARCHAR(20) NOT NULL DEFAULT '2026-2027',
    event_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    location_type ENUM('f2f', 'zoom', 'hybrid') NOT NULL DEFAULT 'f2f',
    venue_name VARCHAR(255) NULL,
    meeting_link VARCHAR(500) NULL,
    description TEXT NULL,
    banner_image VARCHAR(255) NULL,
    cpd_units DECIMAL(4,1) DEFAULT 0.0,
    counts_for_standing TINYINT(1) NOT NULL DEFAULT 1,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL DEFAULT NULL,
    KEY idx_event_date (event_date),
    KEY idx_event_type (event_type),
    KEY idx_status (status),
    KEY idx_fiscal_year (fiscal_year),
    CONSTRAINT fk_event_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

