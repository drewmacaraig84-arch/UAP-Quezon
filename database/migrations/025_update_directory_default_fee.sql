-- Migration 025: Set Default Inactive Website Directory Fee to ₱1,000.00
INSERT INTO site_settings (setting_key, setting_value) 
VALUES ('website_directory_fee', '1000.00')
ON DUPLICATE KEY UPDATE setting_value = '1000.00';

