<?php
/**
 * Bulletin & Chapter Announcement Helper
 * Handles self-healing schema migration, audience filtering, and bulletin fetching.
 */

require_once __DIR__ . '/config.php';

/**
 * Ensures required columns exist on the news_announcements table.
 * Self-healing migration for local and production database environments.
 */
function ensure_bulletin_schema(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM news_announcements")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('target_audience', $cols, true)) {
            $pdo->exec("ALTER TABLE news_announcements ADD COLUMN target_audience ENUM('members_only', 'public', 'all') NOT NULL DEFAULT 'members_only' AFTER content");
            // Set existing items to 'public' to preserve website news
            $pdo->exec("UPDATE news_announcements SET target_audience = 'public' WHERE target_audience IS NULL OR target_audience = '' OR target_audience = 'members_only'");
        }

        if (!in_array('category', $cols, true)) {
            $pdo->exec("ALTER TABLE news_announcements ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT 'General Chapter Announcement' AFTER target_audience");
        }

        if (!in_array('is_pinned', $cols, true)) {
            $pdo->exec("ALTER TABLE news_announcements ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER category");
        }

        if (!in_array('pinned_at', $cols, true)) {
            $pdo->exec("ALTER TABLE news_announcements ADD COLUMN pinned_at TIMESTAMP NULL DEFAULT NULL AFTER is_pinned");
        }

        if (!in_array('pdf_attachment', $cols, true)) {
            $pdo->exec("ALTER TABLE news_announcements ADD COLUMN pdf_attachment VARCHAR(255) NULL DEFAULT NULL AFTER image_path");
        }

        if (!in_array('author_id', $cols, true)) {
            $pdo->exec("ALTER TABLE news_announcements ADD COLUMN author_id INT NULL DEFAULT NULL AFTER pdf_attachment");
        }
    } catch (Throwable $e) {
        if (function_exists('log_system_crash')) {
            log_system_crash('ensure_bulletin_schema', $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }
}

/**
 * Returns available bulletin categories (Clean professional architectural categories, no emojis).
 */
function get_bulletin_categories(): array {
    return [
        'Official Chapter Memorandum & Board Resolution',
        'CPD & Educational Masterclass',
        'Dues, Fees & Financial Advisory',
        'Chapter Meeting & Assembly Notice',
        'General Chapter Announcement',
    ];
}

/**
 * Returns active pinned bulletin for member dashboard if any.
 */
function get_pinned_bulletin(PDO $pdo): ?array {
    ensure_bulletin_schema($pdo);
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM news_announcements 
            WHERE is_pinned = 1 
              AND is_active = 1 
              AND is_published = 1 
              AND deleted_at IS NULL 
              AND target_audience IN ('members_only', 'all')
            ORDER BY COALESCE(pinned_at, created_at) DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Fetches bulletins visible to chapter members.
 */
function get_member_bulletins(PDO $pdo, int $limit = 20, ?string $category = null, ?string $search = null): array {
    ensure_bulletin_schema($pdo);
    try {
        $sql = "
            SELECT * FROM news_announcements 
            WHERE is_active = 1 
              AND is_published = 1 
              AND deleted_at IS NULL 
              AND target_audience IN ('members_only', 'all')
        ";
        $params = [];

        if ($category && $category !== 'All') {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        if ($search) {
            $sql .= " AND (title LIKE ? OR summary LIKE ? OR content LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY is_pinned DESC, COALESCE(pinned_at, created_at) DESC, display_order ASC LIMIT " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}
