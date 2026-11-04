-- Migration 026: Add soft delete support for dues packages
-- UAP Quezon Province Chapter

ALTER TABLE dues ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER term;
ALTER TABLE dues ADD COLUMN deleted_by INT NULL DEFAULT NULL AFTER deleted_at;
