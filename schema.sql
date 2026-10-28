-- ====================================================================
-- UAP Quezon Chapter Management System - Database Schema
-- United Architects of the Philippines - Quezon Chapter
-- Includes Super Admin, System Health, Crash Logs, Soft Deleted Files,
-- Audit Trail, Dues Management, and Website Directory
-- ====================================================================

CREATE DATABASE IF NOT EXISTS uap_quezon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uap_quezon;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS soft_deleted_files;
DROP TABLE IF EXISTS system_crash_logs;
DROP TABLE IF EXISTS chapter_milestones;
DROP TABLE IF EXISTS contact_inquiries;
DROP TABLE IF EXISTS directory_applications;
DROP TABLE IF EXISTS website_members;
DROP TABLE IF EXISTS news_announcements;
DROP TABLE IF EXISTS sponsors;
DROP TABLE IF EXISTS site_settings;
DROP TABLE IF EXISTS qr_codes;
DROP TABLE IF EXISTS receipts;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS member_dues;
DROP TABLE IF EXISTS dues;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS TABLE
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    id_number VARCHAR(50) NOT NULL UNIQUE,
    prc_number VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    gender VARCHAR(20) NULL,
    birth_date DATE NULL,
    age INT NULL,
    civil_status VARCHAR(30) NULL,
    address TEXT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('member', 'admin', 'super_admin') NOT NULL DEFAULT 'member',
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    good_standing_override ENUM('auto', 'revoked', 'granted') NOT NULL DEFAULT 'auto',
    good_standing_reason VARCHAR(255) NULL,
    good_standing_updated_at TIMESTAMP NULL,
    profile_photo VARCHAR(255) NULL,
    is_good_standing_override TINYINT(1) DEFAULT 0,
    terms_accepted TINYINT(1) DEFAULT 0,
    terms_accepted_at TIMESTAMP NULL,
    privacy_accepted TINYINT(1) DEFAULT 0,
    privacy_accepted_at TIMESTAMP NULL,
    cookie_accepted TINYINT(1) DEFAULT 0,
    cookie_accepted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL,
    INDEX idx_user_role (role),
    INDEX idx_user_status (status),
    INDEX idx_user_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. DUES TABLE
CREATE TABLE dues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NULL,
    term VARCHAR(50) NULL,
    is_archived TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL,
    INDEX idx_dues_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. MEMBER DUES TABLE
CREATE TABLE member_dues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    due_id INT NOT NULL,
    status ENUM('unpaid', 'pending', 'partial', 'paid', 'rejected') NOT NULL DEFAULT 'unpaid',
    payment_type ENUM('full', 'partial') DEFAULT NULL,
    installment_months INT DEFAULT NULL,
    total_paid DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    other_obligations DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    due_period VARCHAR(100) DEFAULT NULL,
    custom_title VARCHAR(150) DEFAULT NULL,
    custom_description VARCHAR(255) DEFAULT NULL,
    custom_amount DECIMAL(10,2) DEFAULT NULL,
    custom_due_date DATE DEFAULT NULL,
    custom_term VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (due_id) REFERENCES dues(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_due (user_id, due_id),
    INDEX idx_md_status (status),
    INDEX idx_md_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. PAYMENTS TABLE
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_due_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    method ENUM('gcash', 'maya', 'card', 'bank') NOT NULL,
    reference_number VARCHAR(100) NULL,
    proof_image VARCHAR(255) NULL,
    installment_number INT DEFAULT NULL,
    status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    verified_by INT NULL,
    remarks VARCHAR(255) NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL,
    FOREIGN KEY (member_due_id) REFERENCES member_dues(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_payments_status (status),
    INDEX idx_payments_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. OFFICIAL RECEIPTS TABLE
CREATE TABLE receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    receipt_number VARCHAR(40) NOT NULL UNIQUE,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. QR CODES TABLE
CREATE TABLE qr_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method ENUM('gcash', 'maya', 'bank') NOT NULL UNIQUE,
    image_path VARCHAR(255) NOT NULL,
    account_name VARCHAR(100) NULL,
    account_number VARCHAR(100) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. SITE SETTINGS TABLE
CREATE TABLE site_settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value MEDIUMTEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. SPONSORS TABLE
CREATE TABLE sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    tier ENUM('platinum', 'gold', 'silver', 'bronze') NOT NULL DEFAULT 'gold',
    logo_path VARCHAR(255) NULL,
    website_url VARCHAR(255) NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL,
    INDEX idx_sponsors_active (is_active),
    INDEX idx_sponsors_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. NEWS & ANNOUNCEMENTS
CREATE TABLE news_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    summary TEXT NULL,
    content MEDIUMTEXT NULL,
    date_display VARCHAR(50) NULL,
    date_posted VARCHAR(50) NULL,
    image_path VARCHAR(255) NULL,
    display_order INT DEFAULT 0,
    is_published TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL,
    INDEX idx_news_published (is_published),
    INDEX idx_news_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. WEBSITE DIRECTORY MEMBERS
CREATE TABLE website_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(150) NOT NULL,
    role_title VARCHAR(100) DEFAULT 'Architect',
    specialty VARCHAR(150) DEFAULT 'Professional Architect',
    id_number VARCHAR(50) NULL,
    prc_number VARCHAR(50) NULL,
    location VARCHAR(100) DEFAULT 'Quezon Province',
    achievements TEXT NULL,
    awards TEXT NULL,
    photo_path VARCHAR(255) NULL,
    company_name VARCHAR(150) NULL,
    company_logo VARCHAR(255) NULL,
    company_website VARCHAR(255) NULL,
    linkedin_url VARCHAR(255) NULL,
    portfolio_url VARCHAR(255) NULL,
    qr_image_path VARCHAR(255) NULL,
    is_published TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_wm_published (is_published),
    INDEX idx_wm_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. DIRECTORY APPLICATIONS
CREATE TABLE directory_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    fee_amount DECIMAL(10,2) DEFAULT 500.00,
    status ENUM('pending_fee', 'pending_review', 'approved', 'rejected') NOT NULL DEFAULT 'pending_fee',
    payment_method VARCHAR(50) NULL,
    proof_image VARCHAR(255) NULL,
    reference_number VARCHAR(100) NULL,
    submitted_at TIMESTAMP NULL DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    reviewed_by INT NULL,
    rejection_reason VARCHAR(255) NULL,
    reapply_allowed TINYINT(1) NOT NULL DEFAULT 1,
    reapply_after DATE NULL,
    dismissed_notification TINYINT(1) NOT NULL DEFAULT 0,
    rejected_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_da_status (status),
    INDEX idx_da_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. CONTACT INQUIRIES
CREATE TABLE contact_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    subject VARCHAR(200) NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'replied', 'archived') NOT NULL DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL,
    INDEX idx_ci_status (status),
    INDEX idx_ci_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. CHAPTER MILESTONES
CREATE TABLE chapter_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year VARCHAR(20) NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT NULL,
    INDEX idx_milestones_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. SUPER ADMIN: SYSTEM CRASH & ERROR LOGS
CREATE TABLE system_crash_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    severity ENUM('CRITICAL', 'ERROR', 'WARNING', 'INFO') NOT NULL DEFAULT 'ERROR',
    message TEXT NOT NULL,
    exception_class VARCHAR(255) NULL,
    file VARCHAR(255) NULL,
    line INT NULL,
    trace MEDIUMTEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    user_id INT NULL,
    url VARCHAR(500) NULL,
    method VARCHAR(10) NULL,
    is_resolved TINYINT(1) DEFAULT 0,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    resolved_by INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_severity (severity),
    INDEX idx_log_resolved (is_resolved),
    INDEX idx_log_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. SUPER ADMIN: SOFT DELETED FILES (QUARANTINE / TRASH BIN)
CREATE TABLE soft_deleted_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    original_path VARCHAR(255) NOT NULL,
    quarantine_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NULL,
    source_table VARCHAR(50) NULL,
    source_record_id INT NULL,
    deleted_by INT NULL,
    reason VARCHAR(255) NULL,
    is_restored TINYINT(1) DEFAULT 0,
    restored_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sdf_restored (is_restored),
    INDEX idx_sdf_deleted (deleted_at),
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. SUPER ADMIN: AUDIT LOGS / ACTIVITY TRAIL
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_category (category),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- INITIAL SEED DATA
-- ====================================================================

-- Default Site Settings for UAP Quezon
INSERT INTO site_settings (setting_key, setting_value) VALUES
('chapter_name', 'United Architects of the Philippines - Quezon Chapter'),
('chapter_short_name', 'UAP Quezon Chapter'),
('chapter_code', 'UAP-QC'),
('logo', 'public/logo.jpg'),
('favicon', 'public/logo.jpg'),
('primary_color', '#E58A13'),
('secondary_color', '#15803D'),
('about_us', 'The United Architects of the Philippines (UAP) Quezon Chapter unites registered and licensed architects across Quezon Province. We stand committed to architectural excellence, environmental stewardship of Mt. Banahaw and the Quezon coastline, heritage preservation, and sustainable community development.'),
('contact_email', 'quezon@united-architects.ph'),
('contact_phone', '+63 917 555 UAPQ'),
('contact_address', 'Lucena City, Quezon Province, Philippines'),
('system_version', '2.0.0-Quezon-SuperAdmin'),
('maintenance_mode', '0');

-- Default Accounts:
-- 1. Super Admin (SUPERADMIN01 / admin123)
-- 2. Chapter Admin (ADMIN001 / admin123)
-- 3. Sample Member (UAPQ-2026-001 / member123)
-- Note: bcrypt hash of 'admin123' is $2y$10$rhTiIVrjN9Uh/Jj47WMzoeksyWBPC6l/wR7z9qnPb.tb93CKwUpPS
-- Note: bcrypt hash of 'member123' is $2y$10$3p6jD73ZqRzL5yL2h3x3uebQ1W4h8h.Z.5oJd7W1.N2w9o1Q3j2k2

INSERT INTO users (name, id_number, email, phone, password, role, status) VALUES
('Super Administrator', 'SUPERADMIN01', 'superadmin@uap-quezon.org', '09170000001', '$2y$10$rhTiIVrjN9Uh/Jj47WMzoeksyWBPC6l/wR7z9qnPb.tb93CKwUpPS', 'super_admin', 'approved'),
('Quezon Chapter Admin', 'ADMIN001', 'admin@uap-quezon.org', '09170000002', '$2y$10$rhTiIVrjN9Uh/Jj47WMzoeksyWBPC6l/wR7z9qnPb.tb93CKwUpPS', 'admin', 'approved'),
('Ar. Juan Dela Cruz, uap', 'UAPQ-2026-001', 'juandelacruz@uap-quezon.org', '09170000003', '$2y$10$rhTiIVrjN9Uh/Jj47WMzoeksyWBPC6l/wR7z9qnPb.tb93CKwUpPS', 'member', 'approved'),
('Ar. Maria Santos, uap', 'UAPQ-2026-002', 'mariasantos@uap-quezon.org', '09170000004', '$2y$10$rhTiIVrjN9Uh/Jj47WMzoeksyWBPC6l/wR7z9qnPb.tb93CKwUpPS', 'member', 'approved');

-- Default Dues Packages for UAP Quezon
INSERT INTO dues (title, description, amount, due_date, term) VALUES
('FY 2026-2027 Chapter Annual Dues', 'Annual chapter membership maintenance, national remittance, and standard architect chapter benefits.', 3500.00, '2026-12-31', 'Annual 2026-2027'),
('Quezon Architecture Summit 2026 Delegate Fee', 'CPD accredited summit at Lucena City convention center including kit and dinner.', 1500.00, '2026-11-30', 'Special Event 2026'),
('Chapter Building & Heritage Fund Contribution', 'Voluntary contribution for Quezon historical structures preservation project.', 1000.00, '2026-12-31', 'Special Fund 2026');

-- Assign dues to sample members
INSERT INTO member_dues (user_id, due_id, status, payment_type, total_paid) VALUES
(3, 1, 'unpaid', 'full', 0.00),
(3, 2, 'unpaid', 'full', 0.00),
(4, 1, 'paid', 'full', 3500.00);

-- Record payment and receipt for member 4
INSERT INTO payments (member_due_id, amount_paid, method, reference_number, status, submitted_at, verified_at, verified_by, remarks) VALUES
(3, 3500.00, 'gcash', 'GC-8839201948', 'verified', NOW(), NOW(), 1, 'Full annual dues received via GCash');

INSERT INTO receipts (payment_id, receipt_number, issued_at) VALUES
(1, 'UAPQ-2026-00001', NOW());

-- Default QR codes
INSERT INTO qr_codes (method, image_path, account_name, account_number) VALUES
('gcash', 'uploads/qr_codes/gcash_sample.png', 'UAP Quezon Chapter (Treasurer)', '0917-555-UAPQ'),
('maya', 'uploads/qr_codes/maya_sample.png', 'UAP Quezon Chapter', '0917-555-UAPQ'),
('bank', 'uploads/qr_codes/bank_sample.png', 'UAP Quezon Chapter / BDO Lucena', '0045-8821-9923');

-- Default Sponsors
INSERT INTO sponsors (name, tier, website_url, description, is_active, display_order) VALUES
('Boysen Paints Philippines', 'platinum', 'https://boysen.com.ph', 'The Quality Paint - Official Architectural Finishes Partner of UAP Quezon', 1, 1),
('Davies Paints', 'gold', 'https://daviespaints.com.ph', 'Colors of Innovation & Green Building Solutions', 1, 2),
('Holcim Philippines', 'gold', 'https://holcim.ph', 'Building Progress for People and the Planet in Southern Luzon', 1, 3),
('Southern Luzon Glass & Aluminum Supply', 'silver', 'https://example.com', 'Leading architectural glazing and curtain wall systems in Lucena and Quezon', 1, 4);

-- Default News & Announcements
INSERT INTO news_announcements (title, summary, content, date_display, display_order, is_published) VALUES
('UAP Quezon 2026 Chapter Assembly & General Membership Meeting', 'Join us for our upcoming Chapter Assembly in Lucena City focusing on sustainable regional architecture and local master plans.', 'The UAP Quezon Chapter warmly invites all members to our General Membership Meeting. Agenda includes 2026 Chapter Plans, CPD seminars, induction of new members, and review of local building bylaws in Quezon Province.', 'NOV 15, 2026', 1, 1),
('Quezon Heritage Architecture & Mt. Banahaw Eco-Sanctuary Workshop', 'A 2-day CPD accredited design workshop exploring indigenous Quezon building materials and vernacular heritage preservation.', 'Architects across District 1, 2, 3, and 4 will convene for an on-site heritage analysis of ancestral houses and eco-resilient community shelters.', 'DEC 05, 2026', 2, 1),
('CPD Credit Masterclass: Republic Act 9266 Enforcement in Local LGUs', 'Legal clinic and masterclass on protecting the architectural profession and working with local building officials across Quezon municipalities.', 'Learn practical guidelines, recent jurisprudence, and collaborative workflows with engineering departments and building permit offices.', 'JAN 20, 2027', 3, 1);

-- Default Chapter Milestones
INSERT INTO chapter_milestones (year, title, description, display_order) VALUES
('1984', 'UAP Quezon Chapter Chartering', 'Official chartering of the Quezon Chapter under Area B, uniting visionary architects of the province.', 1),
('2000', 'Quezon Heritage Preservation Initiative', 'Pioneered heritage survey of historical landmarks and neoclassical buildings across Tayabas and Lucena.', 2),
('2015', 'Southern Luzon Best Chapter Award', 'Recognized by UAP National for outstanding membership growth and community architecture assistance.', 3),
('2026', 'Launch of Digital Portal & Super Admin Hub', 'Modern web portal, automated dues verification, and member directory launched for all Quezon architects.', 4);

-- Default Website Members
INSERT INTO website_members (user_id, name, role_title, specialty, id_number, prc_number, location, achievements, awards, is_published) VALUES
(3, 'Ar. Juan Dela Cruz, uap', 'Principal Architect & Urban Designer', 'Sustainable Residential & Resort Architecture', 'UAPQ-2026-001', '0045812', 'Lucena City, Quezon', 'Lead architect of 35+ residential estates and eco-tourism resorts across Southern Luzon.', 'UAP Outstanding Architect Award nominee', 1),
(4, 'Ar. Maria Santos, uap', 'Chapter Vice President & Heritage Architect', 'Historic Conservation & Modern Commercial Design', 'UAPQ-2026-002', '0056981', 'Tayabas, Quezon', 'Consultant for heritage landmark restoration and municipal plaza revitalizations in Quezon Province.', 'National Heritage Award 2024', 1);

-- Seed initial audit log
INSERT INTO audit_logs (user_id, action, category, details, ip_address) VALUES
(1, 'SYSTEM_INIT', 'SYSTEM', 'UAP Quezon Management System with Super Admin initialized successfully.', '127.0.0.1');
