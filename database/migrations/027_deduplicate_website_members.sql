-- Migration 027: Deduplicate website_members and enforce unique constraint on user_id
-- UAP Quezon Province Chapter

-- 1. Remove duplicate website_members rows, preserving the newest entry
DELETE wm1 FROM website_members wm1
INNER JOIN website_members wm2 
WHERE wm1.id < wm2.id 
  AND wm1.user_id = wm2.user_id 
  AND wm1.user_id IS NOT NULL;

-- 2. Add unique key constraint on user_id if not present
SET @key_exists = 0;
SELECT COUNT(*) INTO @key_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'website_members' 
  AND INDEX_NAME = 'uq_website_member_user';

SET @query = IF(@key_exists = 0, 'ALTER TABLE website_members ADD UNIQUE KEY uq_website_member_user (user_id)', 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

