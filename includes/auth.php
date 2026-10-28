<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/attendance_helper.php';

function ensure_user_profile_photo_column($pdo) {
    static $checked = false;
    if ($checked || !$pdo) return;
    $checked = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER status");
        }
        $colStanding = $pdo->query("SHOW COLUMNS FROM users LIKE 'good_standing_override'")->fetch();
        if (!$colStanding) {
            $pdo->exec("ALTER TABLE users ADD COLUMN good_standing_override ENUM('auto', 'revoked', 'granted') NOT NULL DEFAULT 'auto' AFTER status");
            $pdo->exec("ALTER TABLE users ADD COLUMN good_standing_reason VARCHAR(255) NULL AFTER good_standing_override");
            $pdo->exec("ALTER TABLE users ADD COLUMN good_standing_updated_at TIMESTAMP NULL AFTER good_standing_reason");
        }
        $wmCheck = $pdo->query("SHOW TABLES LIKE 'website_members'")->fetch();
        if ($wmCheck) {
            $colCompany = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'company_name'")->fetch();
            if (!$colCompany) {
                $pdo->exec("ALTER TABLE website_members ADD COLUMN company_name VARCHAR(255) NULL AFTER location");
            }
            $colLogo = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'company_logo_path'")->fetch();
            if (!$colLogo) {
                $pdo->exec("ALTER TABLE website_members ADD COLUMN company_logo_path VARCHAR(255) NULL AFTER company_name");
            }
            $colLink = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'link_url'")->fetch();
            if (!$colLink) {
                $pdo->exec("ALTER TABLE website_members ADD COLUMN link_url VARCHAR(500) NULL AFTER company_name");
            }
            $colLinkType = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'link_type'")->fetch();
            if (!$colLinkType) {
                $pdo->exec("ALTER TABLE website_members ADD COLUMN link_type VARCHAR(50) NULL DEFAULT 'auto' AFTER link_url");
            }
            $colLinksJson = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'links_json'")->fetch();
            if (!$colLinksJson) {
                $pdo->exec("ALTER TABLE website_members ADD COLUMN links_json TEXT NULL AFTER link_type");
            }
            // Auto-clean any project photos mistakenly set as member profile avatar
            $pdo->exec("UPDATE website_members SET photo_path = NULL WHERE photo_path LIKE '%proj_%'");
        }
        $newsTableCheck = $pdo->query("SHOW TABLES LIKE 'news_announcements'")->fetch();
        if ($newsTableCheck) {
            $colNewsImg = $pdo->query("SHOW COLUMNS FROM news_announcements LIKE 'image_path'")->fetch();
            if (!$colNewsImg) {
                $pdo->exec("ALTER TABLE news_announcements ADD COLUMN image_path VARCHAR(255) NULL AFTER summary");
            }
            $colNewsActive = $pdo->query("SHOW COLUMNS FROM news_announcements LIKE 'is_active'")->fetch();
            if (!$colNewsActive) {
                $pdo->exec("ALTER TABLE news_announcements ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER is_published");
            }
            $colNewsDate = $pdo->query("SHOW COLUMNS FROM news_announcements LIKE 'date_posted'")->fetch();
            if (!$colNewsDate) {
                $pdo->exec("ALTER TABLE news_announcements ADD COLUMN date_posted VARCHAR(50) NULL AFTER date_display");
            }
        }

        $daTableCheck = $pdo->query("SHOW TABLES LIKE 'directory_applications'")->fetch();
        if ($daTableCheck) {
            // Auto-prune orphaned directory applications or website members where user was deleted
            try {
                $pdo->exec("DELETE FROM directory_applications WHERE user_id NOT IN (SELECT id FROM users)");
                $pdo->exec("DELETE FROM website_members WHERE user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)");
            } catch (Throwable $e) {}

            $colMemDue = $pdo->query("SHOW COLUMNS FROM directory_applications LIKE 'member_due_id'")->fetch();
            if (!$colMemDue) {
                try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN member_due_id INT NULL AFTER fee_amount"); } catch (Throwable $e) {}
            }
            $colNotes = $pdo->query("SHOW COLUMNS FROM directory_applications LIKE 'notes'")->fetch();
            if (!$colNotes) {
                try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN notes TEXT NULL AFTER member_due_id"); } catch (Throwable $e) {}
            }
            $colUpdatedAt = $pdo->query("SHOW COLUMNS FROM directory_applications LIKE 'updated_at'")->fetch();
            if (!$colUpdatedAt) {
                try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"); } catch (Throwable $e) {}
            }
            $colRej = $pdo->query("SHOW COLUMNS FROM directory_applications LIKE 'rejected_at'")->fetch();
            if (!$colRej) {
                try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN reapply_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER notes"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN reapply_after DATE NULL AFTER reapply_allowed"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN dismissed_notification TINYINT(1) NOT NULL DEFAULT 0 AFTER reapply_after"); } catch (Throwable $e) {}
                try { $pdo->exec("ALTER TABLE directory_applications ADD COLUMN rejected_at TIMESTAMP NULL AFTER dismissed_notification"); } catch (Throwable $e) {}
            }
            try {
                $pdo->exec("ALTER TABLE directory_applications MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending_fee'");
            } catch (Throwable $e) {}
        }
    } catch (Throwable $e) {}
}

function detect_social_link_type($url, $selectedType = 'auto') {
    if ($selectedType && $selectedType !== 'auto') {
        return $selectedType;
    }
    if (!$url) return 'website';
    $u = strtolower(trim($url));
    if (str_contains($u, 'facebook.com') || str_contains($u, 'fb.com') || str_contains($u, 'fb.me')) return 'facebook';
    if (str_contains($u, 'instagram.com') || str_contains($u, 'instagr.am')) return 'instagram';
    if (str_contains($u, 'linkedin.com')) return 'linkedin';
    if (str_contains($u, 'youtube.com') || str_contains($u, 'youtu.be')) return 'youtube';
    if (str_contains($u, 't.me') || str_contains($u, 'telegram.me') || str_contains($u, 'telegram.org')) return 'telegram';
    return 'website';
}

function require_login() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    if (isset($pdo)) {
        ensure_user_profile_photo_column($pdo);
    }
}

function require_admin() {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
        header('Location: ' . BASE_URL . '/member/dashboard.php');
        exit;
    }
}

function require_super_admin() {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        if (($_SESSION['role'] ?? '') === 'member') {
            header('Location: ' . BASE_URL . '/member/dashboard.php');
            exit;
        }
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

function require_member() {
    require_login();
    if ($_SESSION['role'] !== 'member') {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function is_good_member($pdo, $userId) {
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }

    try {
        $userStmt = $pdo->prepare("SELECT status, COALESCE(good_standing_override, 'auto') as good_standing_override FROM users WHERE id = ? AND role = 'member'");
        $userStmt->execute([$userId]);
        $userData = $userStmt->fetch();
        if (!$userData || $userData['status'] !== 'approved') {
            return false;
        }

        // Administrative Overrides
        if ($userData['good_standing_override'] === 'revoked') {
            return false; // Explicitly revoked by administrator
        }
        if ($userData['good_standing_override'] === 'granted') {
            return true; // Explicitly granted by administrator
        }
    } catch (Throwable $e) {
        // Fallback query if columns not yet migrated
        $fallbackStmt = $pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'member'");
        $fallbackStmt->execute([$userId]);
        $status = $fallbackStmt->fetchColumn();
        if ($status !== 'approved') {
            return false;
        }
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM member_dues WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $totalDues = (int) $countStmt->fetchColumn();

    if ($totalDues === 0) {
        return true;
    }

    // A member loses good standing ONLY IF they have an overdue/expired unpaid due.
    // Dues expire if:
    // 1) The specified due date has passed: COALESCE(md.custom_due_date, d.due_date) < CURDATE()
    // 2) OR 7 days have passed since the due was created: DATE_ADD(COALESCE(d.created_at, CURDATE()), INTERVAL 7 DAY) < CURDATE()
    // While within the 7-day grace period (and before due date), the member retains good standing & directory access.
    $expiredUnpaidStmt = $pdo->prepare("SELECT COUNT(*)
        FROM member_dues md
        JOIN dues d ON d.id = md.due_id
        WHERE md.user_id = ?
          AND md.total_paid < COALESCE(md.custom_amount, d.amount)
          AND (
              (COALESCE(md.custom_due_date, d.due_date) IS NOT NULL AND COALESCE(md.custom_due_date, d.due_date) < CURDATE())
              OR DATE_ADD(COALESCE(d.created_at, CURDATE()), INTERVAL 7 DAY) < CURDATE()
          )");
    $expiredUnpaidStmt->execute([$userId]);
    $expiredCount = (int) $expiredUnpaidStmt->fetchColumn();

    return $expiredCount === 0;
}

function get_member_standing_details($pdo, $userId) {
    $userId = (int) $userId;
    if ($userId <= 0) {
        return [
            'is_good' => false,
            'override' => 'auto',
            'is_revoked' => false,
            'is_granted' => false,
            'reason' => null,
            'updated_at' => null,
            'label' => 'Invalid User',
            'badge_class' => 'badge-unpaid'
        ];
    }

    try {
        $stmt = $pdo->prepare("SELECT status, COALESCE(good_standing_override, 'auto') as good_standing_override, good_standing_reason, good_standing_updated_at FROM users WHERE id = ? AND role = 'member'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    } catch (Throwable $e) {
        $user = null;
    }

    if (!$user) {
        return [
            'is_good' => false,
            'override' => 'auto',
            'is_revoked' => false,
            'is_granted' => false,
            'reason' => null,
            'updated_at' => null,
            'label' => 'Unknown',
            'badge_class' => 'badge-unpaid'
        ];
    }

    if ($user['status'] !== 'approved') {
        return [
            'is_good' => false,
            'override' => $user['good_standing_override'] ?? 'auto',
            'is_revoked' => false,
            'is_granted' => false,
            'reason' => null,
            'updated_at' => $user['good_standing_updated_at'] ?? null,
            'label' => 'Pending Approval',
            'badge_class' => 'badge-pending'
        ];
    }

    $override = $user['good_standing_override'] ?? 'auto';
    $reason = $user['good_standing_reason'] ?? null;
    $updatedAt = $user['good_standing_updated_at'] ?? null;

    if ($override === 'revoked') {
        return [
            'is_good' => false,
            'override' => 'revoked',
            'is_revoked' => true,
            'is_granted' => false,
            'reason' => $reason,
            'updated_at' => $updatedAt,
            'label' => 'Standing Revoked',
            'badge_class' => 'badge-unpaid'
        ];
    }

    if ($override === 'granted') {
        return [
            'is_good' => true,
            'override' => 'granted',
            'is_revoked' => false,
            'is_granted' => true,
            'reason' => $reason,
            'updated_at' => $updatedAt,
            'label' => 'Good Standing',
            'badge_class' => 'badge-paid'
        ];
    }

    $isGood = is_good_member($pdo, $userId);
    return [
        'is_good' => $isGood,
        'override' => 'auto',
        'is_revoked' => false,
        'is_granted' => false,
        'reason' => null,
        'updated_at' => null,
        'label' => $isGood ? 'Good Standing' : 'Pending Settlement',
        'badge_class' => $isGood ? 'badge-paid' : 'badge-pending'
    ];
}

function set_member_good_standing($pdo, $userId, $override = 'auto', $reason = null) {
    $userId = (int) $userId;
    if ($userId <= 0) return false;
    if (!in_array($override, ['auto', 'revoked', 'granted'], true)) {
        $override = 'auto';
    }
    ensure_user_profile_photo_column($pdo);
    $stmt = $pdo->prepare("UPDATE users SET good_standing_override = ?, good_standing_reason = ?, good_standing_updated_at = NOW() WHERE id = ? AND role = 'member'");
    return $stmt->execute([$override, $reason !== null && trim($reason) !== '' ? trim($reason) : null, $userId]);
}

function get_directory_application($pdo, $userId) {
    $userId = (int) $userId;
    if ($userId <= 0) return null;
    try {
        $stmt = $pdo->prepare("SELECT da.*, md.status as payment_status, md.total_paid, md.id as member_due_id_val,
                               COALESCE(md.custom_amount, d.amount) as due_amount
                               FROM directory_applications da
                               LEFT JOIN member_dues md ON da.member_due_id = md.id
                               LEFT JOIN dues d ON md.due_id = d.id
                               WHERE da.user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function has_unlocked_website_directory($pdo, $userId) {
    $app = get_directory_application($pdo, $userId);
    if (!$app) return false;
    // Unlocked if application status is paid, or linked member due is paid
    if ($app['status'] === 'paid' || ($app['payment_status'] ?? '') === 'paid') {
        return true;
    }
    return false;
}



function get_site_logo($pdo) {
    static $cached = null;
    if ($cached !== null) return $cached ?: 'public/logo.jpg';

    $cacheKey = 'site_setting:logo';
    if (function_exists('cache_get')) {
        $cached = cache_get($cacheKey);
        if ($cached !== null && $cached !== '') {
            return $cached;
        }
    }

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'logo'");
        $stmt->execute();
        $row = $stmt->fetch();
        $cached = ($row && !empty($row['setting_value'])) ? $row['setting_value'] : 'public/logo.jpg';
        if (function_exists('cache_set')) {
            cache_set($cacheKey, $cached);
        }
        return $cached;
    } catch (Exception $e) {
        return 'public/logo.jpg';
    }
}
