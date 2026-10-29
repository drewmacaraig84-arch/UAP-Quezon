-- ====================================================================
-- UPGRADE SCRIPT #2 — UAP Quezon Province Chapter rebrand + approval workflow
-- Run this on your EXISTING database (local XAMPP or InfinityFree).
-- Safe to run even if you already ran the first upgrade.sql before.
-- ====================================================================

-- 1. Add approval status column, default existing accounts to approved
--    (so current members/admin aren't locked out)
ALTER TABLE users ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved';

-- 2. Remove the year level / course column — no longer used
ALTER TABLE users DROP COLUMN course_year;

-- 3. Create the site_settings table (used for the logo upload)
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255)
);

-- 4. Pre-insert the UAP Quezon Province Chapter logo
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('logo', 'uploads/uap_logo.jpg');

-- Done. New registrations from now on will default to 'pending' until
-- an admin approves them (handled in code, not by this script).

-- 5. Add installment support to member_dues
ALTER TABLE member_dues ADD COLUMN status ENUM('unpaid','pending','partial','paid','rejected') NOT NULL DEFAULT 'unpaid';
ALTER TABLE member_dues ADD COLUMN payment_type ENUM('full','partial') DEFAULT NULL;
ALTER TABLE member_dues ADD COLUMN installment_months INT DEFAULT NULL;
ALTER TABLE member_dues ADD COLUMN total_paid DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE member_dues ADD COLUMN custom_title VARCHAR(150) DEFAULT NULL;
ALTER TABLE member_dues ADD COLUMN custom_description VARCHAR(255) DEFAULT NULL;
ALTER TABLE member_dues ADD COLUMN custom_amount DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE member_dues ADD COLUMN custom_due_date DATE DEFAULT NULL;
ALTER TABLE member_dues ADD COLUMN custom_term VARCHAR(50) DEFAULT NULL;

-- 6. Add installment number to payments
ALTER TABLE payments ADD COLUMN installment_number INT DEFAULT NULL;
