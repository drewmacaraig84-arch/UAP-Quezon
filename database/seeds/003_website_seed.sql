-- 003_website_seed.sql
-- Website content seed: site_settings contact info and office hours defaults
-- Real sponsors/announcements/directory are managed via Admin Portal Settings tab.

-- ============================================================
-- SITE SETTINGS: Contact Info & Office Hours (safe upsert)
-- ============================================================
INSERT INTO site_settings (setting_key, setting_value) VALUES
  ('contact_address',       'Lucena City, Quezon Province, Philippines'),
  ('contact_email',         'quezon@united-architects.ph'),
  ('contact_phone',         '+63 (0) XXXX XXXX'),
  ('office_hours_weekdays', '9:00 AM – 5:00 PM'),
  ('office_hours_saturday', '9:00 AM – 12:00 PM'),
  ('office_hours_sunday',   'Closed')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
-- Note: ON DUPLICATE KEY UPDATE setting_value = setting_value means:
--   if the key already exists (e.g. set by admin), do NOT overwrite it.

