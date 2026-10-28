<?php
/**
 * UAP Quezon - Soft Delete & File Quarantine Management Service
 * Provides non-destructive deletion, file quarantine, and one-click recovery.
 */

if (!defined('SOFT_DELETE_INITIALIZED')) {
    define('SOFT_DELETE_INITIALIZED', true);

    /**
     * Soft delete and quarantine a file instead of permanently deleting it
     *
     * @param string $relativePath Relative file path from project root (e.g. 'uploads/proofs/proof_1.jpg')
     * @param string|null $sourceTable
     * @param int|null $sourceRecordId
     * @param string $reason
     * @return int|false ID in soft_deleted_files table or false on failure
     */
    function soft_delete_file($relativePath, $sourceTable = null, $sourceRecordId = null, $reason = '') {
        global $pdo;

        if (empty($relativePath)) {
            return false;
        }

        $projectRoot = dirname(__DIR__);
        $originalFullPath = $projectRoot . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

        if (!file_exists($originalFullPath)) {
            return false;
        }

        $trashDir = $projectRoot . '/uploads/trash';
        if (!is_dir($trashDir)) {
            @mkdir($trashDir, 0777, true);
        }

        $fileName = basename($originalFullPath);
        $fileSize = filesize($originalFullPath) ?: 0;
        $fileType = mime_content_type($originalFullPath) ?: 'application/octet-stream';
        $quarantineFileName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $fileName;
        $quarantineFullPath = $trashDir . '/' . $quarantineFileName;
        $quarantineRelativePath = 'uploads/trash/' . $quarantineFileName;

        // Move the file into quarantine folder
        if (!@rename($originalFullPath, $quarantineFullPath)) {
            if (!@copy($originalFullPath, $quarantineFullPath)) {
                return false;
            }
            @unlink($originalFullPath);
        }

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO soft_deleted_files 
                (file_name, original_path, quarantine_path, file_size, file_type, source_table, source_record_id, deleted_by, reason, deleted_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $fileName,
                ltrim(str_replace('\\', '/', $relativePath), '/'),
                $quarantineRelativePath,
                $fileSize,
                $fileType,
                $sourceTable,
                $sourceRecordId,
                $userId,
                $reason
            ]);

            $softDeleteId = (int)$pdo->lastInsertId();

            if (function_exists('audit_log')) {
                audit_log('FILE_SOFT_DELETED', 'STORAGE', "Quarantined file '$fileName' ($fileSize bytes) from $sourceTable:$sourceRecordId. Reason: $reason", $userId);
            }

            return $softDeleteId;
        } catch (Throwable $e) {
            if (function_exists('log_system_crash')) {
                log_system_crash('ERROR', 'Failed to record soft-deleted file: ' . $e->getMessage(), 'SoftDeleteException', __FILE__, __LINE__);
            }
            return false;
        }
    }

    /**
     * Restore a soft-deleted file back to its original location
     */
    function restore_soft_deleted_file($fileId) {
        global $pdo;
        $fileId = (int)$fileId;

        $stmt = $pdo->prepare("SELECT * FROM soft_deleted_files WHERE id = ?");
        $stmt->execute([$fileId]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['success' => false, 'message' => 'Record not found.'];
        }

        $projectRoot = dirname(__DIR__);
        $quarantineFullPath = $projectRoot . '/' . ltrim(str_replace('\\', '/', $record['quarantine_path']), '/');
        $originalFullPath = $projectRoot . '/' . ltrim(str_replace('\\', '/', $record['original_path']), '/');

        if (!file_exists($quarantineFullPath)) {
            return ['success' => false, 'message' => 'Quarantined file does not exist on disk.'];
        }

        $originalDir = dirname($originalFullPath);
        if (!is_dir($originalDir)) {
            @mkdir($originalDir, 0777, true);
        }

        // If file already exists at original location, do not overwrite blindly
        if (file_exists($originalFullPath)) {
            $originalFullPath = $originalDir . '/restored_' . time() . '_' . $record['file_name'];
        }

        if (!@rename($quarantineFullPath, $originalFullPath)) {
            if (!@copy($quarantineFullPath, $originalFullPath)) {
                return ['success' => false, 'message' => 'Failed to move file out of quarantine.'];
            }
            @unlink($quarantineFullPath);
        }

        $pdo->prepare("UPDATE soft_deleted_files SET is_restored = 1, restored_at = NOW() WHERE id = ?")->execute([$fileId]);

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        if (function_exists('audit_log')) {
            audit_log('FILE_RESTORED', 'STORAGE', "Restored file '{$record['file_name']}' (ID: $fileId)", $userId);
        }

        return ['success' => true, 'message' => "File '{$record['file_name']}' restored successfully."];
    }

    /**
     * Permanently purge a soft-deleted file from disk and database
     */
    function permanently_purge_file($fileId) {
        global $pdo;
        $fileId = (int)$fileId;

        $stmt = $pdo->prepare("SELECT * FROM soft_deleted_files WHERE id = ?");
        $stmt->execute([$fileId]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['success' => false, 'message' => 'Record not found.'];
        }

        $projectRoot = dirname(__DIR__);
        $quarantineFullPath = $projectRoot . '/' . ltrim(str_replace('\\', '/', $record['quarantine_path']), '/');

        if (file_exists($quarantineFullPath)) {
            @unlink($quarantineFullPath);
        }

        $pdo->prepare("DELETE FROM soft_deleted_files WHERE id = ?")->execute([$fileId]);

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        if (function_exists('audit_log')) {
            audit_log('FILE_PURGED', 'STORAGE', "Permanently wiped quarantined file '{$record['file_name']}'", $userId);
        }

        return ['success' => true, 'message' => "File '{$record['file_name']}' permanently purged."];
    }

    /**
     * Soft delete a database record
     */
    function soft_delete_record($table, $id, $reason = '') {
        global $pdo;
        $allowedTables = [
            'users', 'dues', 'member_dues', 'payments', 
            'sponsors', 'news_announcements', 'website_members', 
            'directory_applications', 'contact_inquiries', 'chapter_milestones'
        ];

        if (!in_array($table, $allowedTables, true)) {
            return false;
        }

        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $id = (int)$id;

        $stmt = $pdo->prepare("UPDATE `{$table}` SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
        $success = $stmt->execute([$userId, $id]);

        if ($success && function_exists('audit_log')) {
            audit_log('RECORD_SOFT_DELETED', 'DATABASE', "Soft deleted record in $table with ID $id. Reason: $reason", $userId);
        }

        return $success;
    }

    /**
     * Restore a soft-deleted database record
     */
    function restore_record($table, $id) {
        global $pdo;
        $allowedTables = [
            'users', 'dues', 'member_dues', 'payments', 
            'sponsors', 'news_announcements', 'website_members', 
            'directory_applications', 'contact_inquiries', 'chapter_milestones'
        ];

        if (!in_array($table, $allowedTables, true)) {
            return false;
        }

        $id = (int)$id;
        $stmt = $pdo->prepare("UPDATE `{$table}` SET deleted_at = NULL, deleted_by = NULL WHERE id = ?");
        $success = $stmt->execute([$id]);

        if ($success && function_exists('audit_log')) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            audit_log('RECORD_RESTORED', 'DATABASE', "Restored record in $table with ID $id", $userId);
        }

        return $success;
    }

    /**
     * Permanently delete a record from table
     */
    function permanently_purge_record($table, $id) {
        global $pdo;
        $allowedTables = [
            'users', 'dues', 'member_dues', 'payments', 
            'sponsors', 'news_announcements', 'website_members', 
            'directory_applications', 'contact_inquiries', 'chapter_milestones'
        ];

        if (!in_array($table, $allowedTables, true)) {
            return false;
        }

        $id = (int)$id;
        $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE id = ?");
        $success = $stmt->execute([$id]);

        if ($success && function_exists('audit_log')) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            audit_log('RECORD_PURGED', 'DATABASE', "Permanently purged record in $table with ID $id", $userId);
        }

        return $success;
    }
}

