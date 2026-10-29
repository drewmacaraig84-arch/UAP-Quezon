-- Add settings tables for website content management
-- Run this to add sponsors, news, and about_us functionality

CREATE TABLE IF NOT EXISTS sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    logo_path VARCHAR(255) NOT NULL,
    description VARCHAR(255),
    url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS news_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    date_posted DATE NOT NULL,
    display_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default about us content if not exists
INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('about_us', 'The United Architects of the Philippines (UAP) Quezon Province Chapter unites registered and licensed architects across Quezon Province. We stand committed to architectural excellence, environmental stewardship of Mt. Banahaw and the Quezon coastline, heritage preservation, and sustainable community development.');
