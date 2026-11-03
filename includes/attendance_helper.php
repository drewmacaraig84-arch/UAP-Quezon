<?php
/**
 * UAP Quezon - Attendance & Activity Status Helper
 * 
 * Logic:
 * - >= 7 meetings / events attended => ACTIVE (Green badge)
 * - <= 2 meetings / events attended => INACTIVE (Red badge)
 * - 3 to 6 meetings / events attended => PARTIALLY ACTIVE (Amber badge)
 */

if (!function_exists('ensure_member_attendance_table')) {
    function ensure_member_attendance_table(PDO $pdo) {
        static $checked = false;
        if ($checked || !$pdo) return;
        $checked = true;
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS member_attendance (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    fiscal_year VARCHAR(20) NOT NULL DEFAULT '2026-2027',
                    month_july VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_august VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_september VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_october VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_november VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_december VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_january VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_february VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_march VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_april VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_may VARCHAR(20) NOT NULL DEFAULT 'absent',
                    month_june VARCHAR(20) NOT NULL DEFAULT 'absent',
                    event_induction TINYINT(1) NOT NULL DEFAULT 0,
                    event_anniversary TINYINT(1) NOT NULL DEFAULT 0,
                    event_christmas_party TINYINT(1) NOT NULL DEFAULT 0,
                    event_wad TINYINT(1) NOT NULL DEFAULT 0,
                    event_nca TINYINT(1) NOT NULL DEFAULT 0,
                    event_pafnam TINYINT(1) NOT NULL DEFAULT 0,
                    event_natcon TINYINT(1) NOT NULL DEFAULT 0,
                    event_aba TINYINT(1) NOT NULL DEFAULT 0,
                    event_summer_outing TINYINT(1) NOT NULL DEFAULT 0,
                    event_csr TINYINT(1) NOT NULL DEFAULT 0,
                    cpd_seminars_count INT NOT NULL DEFAULT 0,
                    total_f2f INT NOT NULL DEFAULT 0,
                    total_zoom INT NOT NULL DEFAULT 0,
                    total_attended INT NOT NULL DEFAULT 0,
                    special_points DECIMAL(4,1) NOT NULL DEFAULT 0.0,
                    points_used DECIMAL(4,1) NOT NULL DEFAULT 0.0,
                    scheme_qualified VARCHAR(50) NULL DEFAULT NULL,
                    activity_status ENUM('active','inactive','probationary') NOT NULL DEFAULT 'inactive',
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_user_fy (user_id, fiscal_year),
                    KEY idx_user_id (user_id),
                    KEY idx_activity_status (activity_status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Self-healing migration for existing databases
            $existingCols = [];
            $colsQuery = $pdo->query("DESCRIBE member_attendance");
            if ($colsQuery) {
                while ($c = $colsQuery->fetch(PDO::FETCH_ASSOC)) {
                    $existingCols[$c['Field']] = true;
                }
            }
            if (!isset($existingCols['event_aba'])) {
                $pdo->exec("ALTER TABLE member_attendance ADD COLUMN event_aba TINYINT(1) NOT NULL DEFAULT 0 AFTER event_natcon");
            }
            if (!isset($existingCols['cpd_seminars_count'])) {
                $pdo->exec("ALTER TABLE member_attendance ADD COLUMN cpd_seminars_count INT NOT NULL DEFAULT 0 AFTER event_csr");
            }
            if (!isset($existingCols['special_points'])) {
                $pdo->exec("ALTER TABLE member_attendance ADD COLUMN special_points DECIMAL(4,1) NOT NULL DEFAULT 0.0 AFTER total_attended");
            }
            if (!isset($existingCols['points_used'])) {
                $pdo->exec("ALTER TABLE member_attendance ADD COLUMN points_used DECIMAL(4,1) NOT NULL DEFAULT 0.0 AFTER special_points");
            }
            if (!isset($existingCols['scheme_qualified'])) {
                $pdo->exec("ALTER TABLE member_attendance ADD COLUMN scheme_qualified VARCHAR(50) NULL DEFAULT NULL AFTER activity_status");
            }
            if (!isset($existingCols['custom_events_json'])) {
                $pdo->exec("ALTER TABLE member_attendance ADD COLUMN custom_events_json LONGTEXT NULL AFTER cpd_seminars_count");
            }
        } catch (Throwable $e) {}
    }
}

if (!function_exists('ensure_compliance_activities_schema')) {
    function ensure_compliance_activities_schema(PDO $pdo): void {
        static $checked = false;
        if ($checked || !$pdo) return;
        $checked = true;

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS chapter_compliance_activities (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    fiscal_year VARCHAR(20) NOT NULL DEFAULT '2026-2027',
                    activity_type ENUM('monthly_gmm', 'major_event', 'special_event') NOT NULL,
                    activity_key VARCHAR(60) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    short_label VARCHAR(50) NULL,
                    category VARCHAR(100) NULL,
                    point_value DECIMAL(4,1) NOT NULL DEFAULT 1.0,
                    event_date DATE NULL,
                    venue_or_platform VARCHAR(255) NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_act_fy_key (fiscal_year, activity_key),
                    KEY idx_act_type (activity_type),
                    KEY idx_is_active (is_active),
                    KEY idx_sort_order (sort_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Seed default items if table is empty
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM chapter_compliance_activities")->fetchColumn();
            if ($cnt === 0) {
                $fy = '2026-2027';
                $stmt = $pdo->prepare("
                    INSERT INTO chapter_compliance_activities 
                    (fiscal_year, activity_type, activity_key, title, short_label, category, point_value, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");

                // 1. 12 Monthly GMMs
                $months = [
                    ['july',      'July 2026',      'Jul 26', 'General Membership Meeting', 1.0, 1],
                    ['august',    'August 2026',    'Aug 26', 'General Membership Meeting', 1.0, 2],
                    ['september', 'September 2026', 'Sep 26', 'General Membership Meeting', 1.0, 3],
                    ['october',   'October 2026',   'Oct 26', 'General Membership Meeting', 1.0, 4],
                    ['november',  'November 2026',  'Nov 26', 'General Membership Meeting', 1.0, 5],
                    ['december',  'December 2026',  'Dec 26', 'General Membership Meeting', 1.0, 6],
                    ['january',   'January 2027',   'Jan 27', 'General Membership Meeting', 1.0, 7],
                    ['february',  'February 2027',  'Feb 27', 'General Membership Meeting', 1.0, 8],
                    ['march',     'March 2027',     'Mar 27', 'General Membership Meeting', 1.0, 9],
                    ['april',     'April 2027',     'Apr 27', 'General Membership Meeting', 1.0, 10],
                    ['may',       'May 2027',       'May 27', 'General Membership Meeting', 1.0, 11],
                    ['june',      'June 2027',      'Jun 27', 'General Membership Meeting', 1.0, 12],
                ];
                foreach ($months as $m) {
                    $stmt->execute([$fy, 'monthly_gmm', $m[0], $m[1], $m[2], $m[3], $m[4], $m[5]]);
                }

                // 2. 3 Major Events
                $majors = [
                    ['event_induction',       'Chapter Induction of Officers & New Members',           'Induction',       'Annual Official Chapter Event', 1.0, 1],
                    ['event_anniversary',     'UAP Quezon Province Chapter Founding Anniversary',      'Anniversary',     'Annual Official Chapter Event', 1.0, 2],
                    ['event_christmas_party', 'Annual Architects Fellowship & Christmas Party',        'Christmas Party', 'Annual Official Chapter Event', 1.0, 3],
                ];
                foreach ($majors as $maj) {
                    $stmt->execute([$fy, 'major_event', $maj[0], $maj[1], $maj[2], $maj[3], $maj[4], $maj[5]]);
                }

                // 3. 7 Special Events
                $specials = [
                    ['event_natcon',        'NATCON (National Convention)',                                         'NATCON',        'National Convention', 3.0, 1],
                    ['event_aba',           'ABA (Area-B Assembly)',                                                'ABA',           'Regional Assembly',   2.0, 2],
                    ['event_nca',           'NCA (National Conference of Architects)',                              'NCA',           'National Conference', 2.0, 3],
                    ['event_pafnam',        'PAFNAM (Philippine Architecture Festival National Architecture Month)','PAFNAM',        'National Festival',   2.0, 4],
                    ['event_wad',           'WAD (World Architecture Day)',                                         'WAD',           'Global Observance',   1.0, 5],
                    ['event_csr',           'CSR Projects',                                                         'CSR Projects',  'Community Service',   1.0, 6],
                    ['event_summer_outing', 'Summer Outing & Sports Fellowship',                                    'Summer Outing', 'Chapter Fellowship',  1.0, 7],
                ];
                foreach ($specials as $sp) {
                    $stmt->execute([$fy, 'special_event', $sp[0], $sp[1], $sp[2], $sp[3], $sp[4], $sp[5]]);
                }
            }
        } catch (Throwable $e) {}
    }
}

if (!function_exists('ensure_chapter_events_schema')) {
    function ensure_chapter_events_schema(PDO $pdo) {
        static $checked = false;
        if ($checked || !$pdo) return;
        $checked = true;

        try {
            $chk = $pdo->query("SHOW TABLES LIKE 'chapter_events'")->fetch();
            if (!$chk) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS chapter_events (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        event_type ENUM('gmm', 'board_meeting', 'cpd_seminar', 'fellowship', 'assembly', 'major', 'special') NOT NULL DEFAULT 'gmm',
                        fiscal_year VARCHAR(20) NOT NULL DEFAULT '2026-2027',
                        event_date DATE NOT NULL,
                        start_time TIME NULL,
                        end_time TIME NULL,
                        location_type ENUM('f2f', 'zoom', 'hybrid') NOT NULL DEFAULT 'f2f',
                        venue_name VARCHAR(255) NULL,
                        meeting_link VARCHAR(500) NULL,
                        description TEXT NULL,
                        banner_image VARCHAR(255) NULL,
                        cpd_units DECIMAL(4,1) DEFAULT 0.0,
                        counts_for_standing TINYINT(1) NOT NULL DEFAULT 1,
                        is_published TINYINT(1) NOT NULL DEFAULT 1,
                        status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
                        created_by INT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        deleted_at TIMESTAMP NULL DEFAULT NULL,
                        deleted_by INT NULL DEFAULT NULL,
                        KEY idx_event_date (event_date),
                        KEY idx_event_type (event_type),
                        KEY idx_status (status),
                        KEY idx_fiscal_year (fiscal_year)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM chapter_events")->fetchColumn();
                if ($cnt === 0) {
                    $pdo->exec("
                        INSERT INTO chapter_events 
                        (title, event_type, fiscal_year, event_date, start_time, end_time, location_type, venue_name, description, cpd_units, counts_for_standing, is_published, status)
                        VALUES 
                        ('Special GMM: Architectural Heritage Preservation in Quezon', 'gmm', '2026-2027', '2026-11-20', '18:00:00', '21:00:00', 'hybrid', 'Quezon Premier Hotel & Convention Center, Lucena City', 'Discussion on historical preservation guidelines and chapter milestones.', 1.5, 1, 1, 'upcoming'),
                        ('CPD Masterclass: Climate-Resilient Tropical Architecture', 'cpd_seminar', '2026-2027', '2026-12-10', '13:00:00', '17:00:00', 'hybrid', 'Lucena Grand Central Terminal Pavilion', 'Specialized Continuing Professional Development seminar on sustainable provincial developments.', 3.0, 1, 1, 'upcoming')
                    ");
                }
            }

            $chk2 = $pdo->query("SHOW TABLES LIKE 'event_attendance_logs'")->fetch();
            if (!$chk2) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS event_attendance_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        fiscal_year VARCHAR(20) NOT NULL DEFAULT '2026-2027',
                        event_key VARCHAR(50) NOT NULL,
                        event_title VARCHAR(255) NOT NULL,
                        attendance_mode ENUM('f2f', 'zoom') NOT NULL DEFAULT 'f2f',
                        scanned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        scanned_by INT NULL,
                        verification_method VARCHAR(50) NOT NULL DEFAULT 'qr_scan',
                        notes VARCHAR(255) NULL,
                        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uniq_user_event_fy (user_id, fiscal_year, event_key),
                        KEY idx_event_key (event_key),
                        KEY idx_fiscal_year (fiscal_year),
                        KEY idx_scanned_at (scanned_at),
                        KEY idx_user_id (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            // One-time self-healing repair for any attendance records saved before timezone synchronization
            try {
                $pdo->exec("
                    UPDATE event_attendance_logs 
                    SET scanned_at = DATE_ADD(scanned_at, INTERVAL 16 HOUR) 
                    WHERE scanned_at >= '2026-11-02 17:00:00' AND scanned_at < '2026-11-03 00:00:00'
                ");
            } catch (Throwable $eTz) {}
        } catch (Throwable $e) {}
    }
}

if (isset($pdo) && $pdo instanceof PDO) {
    ensure_member_attendance_table($pdo);
    ensure_compliance_activities_schema($pdo);
    ensure_chapter_events_schema($pdo);
}

if (!function_exists('get_active_fiscal_year')) {
    function get_active_fiscal_year(): string {
        return '2026-2027';
    }
}

if (!function_exists('get_member_attendance_months')) {
    function get_member_attendance_months(?PDO $db = null): array {
        global $pdo;
        $conn = $db ?: ($pdo ?? null);
        $fy = get_active_fiscal_year();

        if ($conn instanceof PDO) {
            try {
                $stmt = $conn->prepare("
                    SELECT id, activity_key, title, short_label, event_date, venue_or_platform
                    FROM chapter_compliance_activities
                    WHERE fiscal_year = ? AND activity_type = 'monthly_gmm' AND is_active = 1
                    ORDER BY sort_order ASC, id ASC
                ");
                $stmt->execute([$fy]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $res = [];
                    foreach ($rows as $r) {
                        $key = $r['activity_key'];
                        $res[$key] = [
                            'id'         => (int)$r['id'],
                            'key'        => $key,
                            'label'      => $r['title'],
                            'short'      => $r['short_label'] ?: substr($r['title'], 0, 6),
                            'date'       => $r['event_date'],
                            'venue'      => $r['venue_or_platform'],
                        ];
                    }
                    return $res;
                }
            } catch (Throwable $e) {}
        }

        return [
            'july'      => ['label' => 'July 2026',      'short' => 'Jul 26'],
            'august'    => ['label' => 'August 2026',    'short' => 'Aug 26'],
            'september' => ['label' => 'September 2026', 'short' => 'Sep 26'],
            'october'   => ['label' => 'October 2026',   'short' => 'Oct 26'],
            'november'  => ['label' => 'November 2026',  'short' => 'Nov 26'],
            'december'  => ['label' => 'December 2026',  'short' => 'Dec 26'],
            'january'   => ['label' => 'January 2027',   'short' => 'Jan 27'],
            'february'  => ['label' => 'February 2027',  'short' => 'Feb 27'],
            'march'     => ['label' => 'March 2027',     'short' => 'Mar 27'],
            'april'     => ['label' => 'April 2027',     'short' => 'Apr 27'],
            'may'       => ['label' => 'May 2027',       'short' => 'May 27'],
            'june'      => ['label' => 'June 2027',      'short' => 'Jun 27'],
        ];
    }
}

if (!function_exists('get_major_events_list')) {
    function get_major_events_list(?PDO $db = null): array {
        global $pdo;
        $conn = $db ?: ($pdo ?? null);
        $fy = get_active_fiscal_year();

        if ($conn instanceof PDO) {
            try {
                $stmt = $conn->prepare("
                    SELECT id, activity_key, title, short_label, category, event_date, venue_or_platform
                    FROM chapter_compliance_activities
                    WHERE fiscal_year = ? AND activity_type = 'major_event' AND is_active = 1
                    ORDER BY sort_order ASC, id ASC
                ");
                $stmt->execute([$fy]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $res = [];
                    foreach ($rows as $r) {
                        $res[$r['activity_key']] = $r['title'];
                    }
                    return $res;
                }
            } catch (Throwable $e) {}
        }

        return [
            'event_induction'       => 'Chapter Induction of Officers & New Members',
            'event_anniversary'     => 'UAP Quezon Province Chapter Founding Anniversary',
            'event_christmas_party' => 'Annual Architects Fellowship & Christmas Party',
        ];
    }
}

if (!function_exists('get_major_events_metadata')) {
    function get_major_events_metadata(?PDO $db = null): array {
        global $pdo;
        $conn = $db ?: ($pdo ?? null);
        $fy = get_active_fiscal_year();

        if ($conn instanceof PDO) {
            try {
                $stmt = $conn->prepare("
                    SELECT id, activity_key, title, short_label, category, point_value, event_date, venue_or_platform, is_active, sort_order
                    FROM chapter_compliance_activities
                    WHERE fiscal_year = ? AND activity_type = 'major_event'
                    ORDER BY sort_order ASC, id ASC
                ");
                $stmt->execute([$fy]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {}
        }
        return [];
    }
}

if (!function_exists('get_special_events_list')) {
    function get_special_events_list(?PDO $db = null): array {
        $points = get_resolution_special_event_points($db);
        $res = [];
        foreach ($points as $k => $item) {
            $pts = number_format((float)$item['points'], 1);
            $res[$k] = "{$item['title']} - {$pts} pts";
        }
        return $res;
    }
}

if (!function_exists('get_resolution_special_event_points')) {
    function get_resolution_special_event_points(?PDO $db = null): array {
        global $pdo;
        $conn = $db ?: ($pdo ?? null);
        $fy = get_active_fiscal_year();

        if ($conn instanceof PDO) {
            try {
                $stmt = $conn->prepare("
                    SELECT id, activity_key, title, short_label, category, point_value, event_date, venue_or_platform
                    FROM chapter_compliance_activities
                    WHERE fiscal_year = ? AND activity_type = 'special_event' AND is_active = 1
                    ORDER BY sort_order ASC, id ASC
                ");
                $stmt->execute([$fy]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $res = [];
                    foreach ($rows as $r) {
                        $key = $r['activity_key'];
                        $res[$key] = [
                            'id'          => (int)$r['id'],
                            'col'         => $key,
                            'title'       => $r['title'],
                            'acronym'     => $r['short_label'] ?: $r['title'],
                            'points'      => (float)$r['point_value'],
                            'category'    => $r['category'] ?: 'Special Activity',
                            'event_date'  => $r['event_date'],
                            'venue'       => $r['venue_or_platform'],
                            'icon'        => 'sparkles',
                            'badge_color' => '#f59e0b',
                        ];
                    }
                    return $res;
                }
            } catch (Throwable $e) {}
        }

        return [
            'event_natcon' => [
                'col'         => 'event_natcon',
                'title'       => 'NATCON (National Convention)',
                'acronym'     => 'NATCON',
                'points'      => 3.0,
                'category'    => 'National Convention',
                'icon'        => 'users',
                'badge_color' => '#3b82f6',
            ],
            'event_aba' => [
                'col'         => 'event_aba',
                'title'       => 'ABA (Area-B Assembly)',
                'acronym'     => 'ABA',
                'points'      => 2.0,
                'category'    => 'Regional Assembly',
                'icon'        => 'user_check',
                'badge_color' => '#8b5cf6',
            ],
            'event_nca' => [
                'col'         => 'event_nca',
                'title'       => 'NCA (National Conference of Architects)',
                'acronym'     => 'NCA',
                'points'      => 2.0,
                'category'    => 'National Conference',
                'icon'        => 'building',
                'badge_color' => '#06b6d4',
            ],
            'event_pafnam' => [
                'col'         => 'event_pafnam',
                'title'       => 'PAFNAM (Philippine Architecture Festival National Architecture Month)',
                'acronym'     => 'PAFNAM',
                'points'      => 2.0,
                'category'    => 'National Festival',
                'icon'        => 'sparkles',
                'badge_color' => '#ec4899',
            ],
            'event_wad' => [
                'col'         => 'event_wad',
                'title'       => 'WAD (World Architecture Day)',
                'acronym'     => 'WAD',
                'points'      => 1.0,
                'category'    => 'Global Observance',
                'icon'        => 'globe',
                'badge_color' => '#10b981',
            ],
            'event_csr' => [
                'col'         => 'event_csr',
                'title'       => 'CSR Projects',
                'acronym'     => 'CSR Projects',
                'points'      => 1.0,
                'category'    => 'Community Service',
                'icon'        => 'heart',
                'badge_color' => '#14b8a6',
            ],
            'event_summer_outing' => [
                'col'         => 'event_summer_outing',
                'title'       => 'Summer Outing & Sports Fellowship',
                'acronym'     => 'Summer Outing',
                'points'      => 1.0,
                'category'    => 'Chapter Fellowship',
                'icon'        => 'sun',
                'badge_color' => '#f59e0b',
            ],
        ];
    }
}

if (!function_exists('recalculate_all_members_attendance')) {
    function recalculate_all_members_attendance(PDO $pdo, string $fiscalYear = '2026-2027'): int {
        ensure_compliance_activities_schema($pdo);
        $stmt = $pdo->prepare("SELECT * FROM member_attendance WHERE fiscal_year = ?");
        $stmt->execute([$fiscalYear]);
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            calculate_and_sync_activity_status($pdo, $row);
            $count++;
        }
        return $count;
    }
}

if (!function_exists('get_or_create_member_attendance')) {
    function get_or_create_member_attendance(PDO $pdo, int $userId, string $fiscalYear = '2026-2027'): array {
        $stmt = $pdo->prepare("SELECT * FROM member_attendance WHERE user_id = ? AND fiscal_year = ? LIMIT 1");
        $stmt->execute([$userId, $fiscalYear]);
        $row = $stmt->fetch();

        if (!$row) {
            $insert = $pdo->prepare("
                INSERT INTO member_attendance (user_id, fiscal_year, activity_status) 
                VALUES (?, ?, 'inactive')
            ");
            $insert->execute([$userId, $fiscalYear]);
            $stmt->execute([$userId, $fiscalYear]);
            $row = $stmt->fetch();
        }

        return calculate_and_sync_activity_status($pdo, $row);
    }
}

if (!function_exists('calculate_and_sync_activity_status')) {
    function calculate_and_sync_activity_status(PDO $pdo, array|int $rowOrUserId): array {
        if (is_int($rowOrUserId)) {
            return get_or_create_member_attendance($pdo, $rowOrUserId);
        }
        $row = $rowOrUserId;
        $userId = (int)($row['user_id'] ?? 0);
        $fy = $row['fiscal_year'] ?? get_active_fiscal_year();

        // Decode custom events JSON if present
        $customEvents = !empty($row['custom_events_json']) ? json_decode($row['custom_events_json'], true) : [];
        if (!is_array($customEvents)) {
            $customEvents = [];
        }

        // 1. Monthly General Membership Meetings (GMMs)
        $months = get_member_attendance_months($pdo);
        $monthlyF2F = 0;
        $monthlyZoom = 0;

        foreach ($months as $key => $meta) {
            $val = 'absent';
            if (isset($row['month_' . $key])) {
                $val = strtolower($row['month_' . $key] ?? 'absent');
            } elseif (isset($customEvents['month_' . $key])) {
                $val = strtolower($customEvents['month_' . $key] ?? 'absent');
            } elseif (isset($customEvents[$key])) {
                $val = is_array($customEvents[$key]) ? strtolower($customEvents[$key]['mode'] ?? 'absent') : strtolower($customEvents[$key]);
            }

            if ($val === 'f2f') {
                $monthlyF2F++;
            } elseif ($val === 'zoom') {
                $monthlyZoom++;
            }
        }

        // 2. Custom chapter events & meetings attended from event_attendance_logs
        $customGmmF2F = 0;
        $customGmmZoom = 0;
        $customMajorF2F = 0;
        $customCpdCount = 0;
        $customSpecialCount = 0;

        if ($userId > 0) {
            try {
                $cStmt = $pdo->prepare("
                    SELECT ce.id, ce.title, ce.event_type, ce.cpd_units, l.attendance_mode
                    FROM event_attendance_logs l
                    JOIN chapter_events ce ON l.event_key = CONCAT('ce_', ce.id)
                    WHERE l.user_id = ? AND l.fiscal_year = ? AND ce.counts_for_standing = 1 AND ce.deleted_at IS NULL
                ");
                $cStmt->execute([$userId, $fy]);
                while ($cRow = $cStmt->fetch(PDO::FETCH_ASSOC)) {
                    $evType = $cRow['event_type'] ?? 'gmm';
                    $mode = $cRow['attendance_mode'] ?? 'f2f';

                    if ($evType === 'gmm') {
                        if ($mode === 'zoom') {
                            $customGmmZoom++;
                        } else {
                            $customGmmF2F++;
                        }
                    } elseif ($evType === 'major') {
                        $customMajorF2F++;
                    } elseif ($evType === 'cpd_seminar' || (float)($cRow['cpd_units'] ?? 0) > 0) {
                        $customCpdCount++;
                    } elseif ($evType === 'special') {
                        $customSpecialCount++;
                    }
                }
            } catch (Throwable $e) {}
        }

        $totalGmmF2F = $monthlyF2F + $customGmmF2F;
        $totalGmmZoom = $monthlyZoom + $customGmmZoom;
        $totalGmmAttended = $totalGmmF2F + $totalGmmZoom;

        // 3. Major Chapter Events (Board Reso. Sec. 2 & 3)
        $majorEvents = get_major_events_list($pdo);
        $majorDirectCount = 0;
        $majorBreakdown = [];
        foreach ($majorEvents as $col => $title) {
            $isAtt = false;
            if (isset($row[$col]) && !empty($row[$col])) {
                $isAtt = true;
            } elseif (!empty($customEvents[$col])) {
                $isAtt = true;
            }
            if ($isAtt) {
                $majorDirectCount++;
            }
            $majorBreakdown[$col] = [
                'title'    => $title,
                'attended' => $isAtt
            ];
        }
        $totalMajorEvents = min(max(3, count($majorEvents)), $majorDirectCount + $customMajorF2F);

        // 4. Special Events Points & CPD Seminars (Sec. 5)
        $specialConfig = get_resolution_special_event_points($pdo);
        $specialPointsFromEvents = 0.0;
        $specialEventsAttendedCount = 0;
        $specialBreakdown = [];

        foreach ($specialConfig as $col => $meta) {
            $isAttended = false;
            if (isset($row[$col]) && !empty($row[$col])) {
                $isAttended = true;
            } elseif (!empty($customEvents[$col])) {
                $isAttended = true;
            }
            $pts = (float)$meta['points'];
            if ($isAttended) {
                $specialEventsAttendedCount++;
                $specialPointsFromEvents += $pts;
            }
            $specialBreakdown[$col] = [
                'meta'     => $meta,
                'attended' => $isAttended,
                'points'   => $isAttended ? $pts : 0.0,
            ];
        }

        // CPD Seminars & Tours: 1.0 point each (Sec. 5)
        $manualCpd = (int)($row['cpd_seminars_count'] ?? 0);
        $totalCpdCount = max($manualCpd, $customCpdCount);
        $cpdPoints = $totalCpdCount * 1.0;

        $totalSpecialPoints = round($specialPointsFromEvents + $cpdPoints, 1);

        // 5. Evaluate Schemes (Board Reso. Sec. 2, 3, 4, 5)
        // Scheme 1: Face-to-Face Scheme (Sec. 2)
        // Requires: >= 7 In-Person GMMs AND >= 2 Major Chapter Events
        $defGmmF2F = max(0, 7 - $totalGmmF2F);
        $defMajorF2F = max(0, 2 - $totalMajorEvents);
        $pointsNeededF2F = ($defGmmF2F * 5) + ($defMajorF2F * 7);
        $isF2FDirect = ($defGmmF2F === 0 && $defMajorF2F === 0);
        $isF2FCompensated = (!$isF2FDirect && $totalSpecialPoints >= $pointsNeededF2F);
        $isF2FQualified = ($isF2FDirect || $isF2FCompensated);

        // Scheme 2: Hybrid Attendance Scheme (Sec. 3)
        // Requires: >= 4 In-Person GMMs, >= 6 Zoom GMMs, AND >= 2 Major Chapter Events
        $defF2FHybrid = max(0, 4 - $totalGmmF2F);
        $defZoomHybrid = max(0, 6 - $totalGmmZoom);
        $defMajorHybrid = max(0, 2 - $totalMajorEvents);
        $defGmmHybrid = $defF2FHybrid + $defZoomHybrid;
        $pointsNeededHybrid = ($defGmmHybrid * 5) + ($defMajorHybrid * 7);
        $isHybridDirect = ($defF2FHybrid === 0 && $defZoomHybrid === 0 && $defMajorHybrid === 0);
        $isHybridCompensated = (!$isHybridDirect && $totalSpecialPoints >= $pointsNeededHybrid);
        $isHybridQualified = ($isHybridDirect || $isHybridCompensated);

        // Totals for general activity display
        $totalAttended = $totalGmmAttended + $totalMajorEvents + $specialEventsAttendedCount + $totalCpdCount + $customSpecialCount;
        $totalF2F = $totalGmmF2F + $totalMajorEvents + $specialEventsAttendedCount + $totalCpdCount + $customSpecialCount;
        $totalZoom = $totalGmmZoom;

        // Qualification Determination
        $schemeQualified = null;
        $schemeLabel = 'Not Qualified';
        $pointsUsed = 0.0;
        $qualificationNote = '';
        $status = 'inactive';
        $badgeColor = '#ef4444';
        $badgeBg = 'rgba(239, 68, 68, 0.15)';
        $badgeBorder = '#ef4444';
        $badgeLabel = 'INACTIVE';
        $statusClass = 'badge-unpaid';

        if ($isF2FDirect) {
            $schemeQualified = 'f2f_scheme';
            $schemeLabel = 'Face-to-Face Scheme';
            $status = 'active';
            $badgeColor = '#10b981';
            $badgeBg = 'rgba(16, 185, 129, 0.15)';
            $badgeBorder = '#10b981';
            $badgeLabel = 'ACTIVE';
            $statusClass = 'badge-paid';
            $pointsUsed = 0.0;
            $qualificationNote = 'Fully compliant under Reso. Sec. 2 (7+ In-Person GMMs & 2 Major Events attended).';
        } elseif ($isHybridDirect) {
            $schemeQualified = 'hybrid_scheme';
            $schemeLabel = 'Hybrid Attendance Scheme';
            $status = 'active';
            $badgeColor = '#10b981';
            $badgeBg = 'rgba(16, 185, 129, 0.15)';
            $badgeBorder = '#10b981';
            $badgeLabel = 'ACTIVE';
            $statusClass = 'badge-paid';
            $pointsUsed = 0.0;
            $qualificationNote = 'Fully compliant under Reso. Sec. 3 (4+ In-Person GMMs, 6+ Zoom GMMs & 2 Major Events attended).';
        } elseif ($isF2FCompensated && (!$isHybridCompensated || $pointsNeededF2F <= $pointsNeededHybrid)) {
            $schemeQualified = 'f2f_compensated';
            $schemeLabel = 'Face-to-Face Scheme (Compensated)';
            $status = 'active';
            $badgeColor = '#10b981';
            $badgeBg = 'rgba(16, 185, 129, 0.15)';
            $badgeBorder = '#10b981';
            $badgeLabel = 'ACTIVE';
            $statusClass = 'badge-paid';
            $pointsUsed = (float)$pointsNeededF2F;
            $qualificationNote = "Active via Point Substitution (Reso. Sec. 4 & 5): {$pointsNeededF2F} of {$totalSpecialPoints} Special Points applied for missing attendances.";
        } elseif ($isHybridCompensated) {
            $schemeQualified = 'hybrid_compensated';
            $schemeLabel = 'Hybrid Scheme (Compensated)';
            $status = 'active';
            $badgeColor = '#10b981';
            $badgeBg = 'rgba(16, 185, 129, 0.15)';
            $badgeBorder = '#10b981';
            $badgeLabel = 'ACTIVE';
            $statusClass = 'badge-paid';
            $pointsUsed = (float)$pointsNeededHybrid;
            $qualificationNote = "Active via Point Substitution (Reso. Sec. 4 & 5): {$pointsNeededHybrid} of {$totalSpecialPoints} Special Points applied for missing attendances.";
        } else {
            // Check closer scheme
            $closerScheme = ($pointsNeededF2F <= $pointsNeededHybrid) ? 'f2f' : 'hybrid';
            $deficit = ($closerScheme === 'f2f') ? max(0, $pointsNeededF2F - $totalSpecialPoints) : max(0, $pointsNeededHybrid - $totalSpecialPoints);
            $totalAttendedAny = $totalGmmAttended + $totalMajorEvents + $specialEventsAttendedCount + $totalCpdCount;

            if ($totalAttendedAny > 0 || $totalSpecialPoints > 0) {
                $status = 'probationary';
                $badgeColor = '#f59e0b';
                $badgeBg = 'rgba(245, 158, 11, 0.15)';
                $badgeBorder = '#f59e0b';
                $badgeLabel = 'PARTIALLY ACTIVE';
                $statusClass = 'badge-partial';
                $schemeLabel = 'In Progress';
                $qualificationNote = "In Progress: Deficient by {$deficit} points (or attend remaining GMMs / Major Events) to qualify for Active Status.";
            } else {
                $status = 'inactive';
                $badgeColor = '#ef4444';
                $badgeBg = 'rgba(239, 68, 68, 0.15)';
                $badgeBorder = '#ef4444';
                $badgeLabel = 'INACTIVE';
                $statusClass = 'badge-unpaid';
                $schemeLabel = 'Inactive';
                $qualificationNote = "Inactive: No attendance recorded yet for FY {$fy}. Requires 7 F2F GMMs + 2 Major Events (or 4 F2F + 6 Zoom + 2 Major Events).";
            }
        }

        $remainingSpecialPoints = max(0, round($totalSpecialPoints - $pointsUsed, 1));

        // Sync to DB if cached values differ
        if (
            (int)($row['total_f2f'] ?? 0) !== $totalF2F ||
            (int)($row['total_zoom'] ?? 0) !== $totalZoom ||
            (int)($row['total_attended'] ?? 0) !== $totalAttended ||
            (float)($row['special_points'] ?? 0) !== (float)$totalSpecialPoints ||
            (float)($row['points_used'] ?? 0) !== (float)$pointsUsed ||
            ($row['scheme_qualified'] ?? null) !== $schemeQualified ||
            ($row['activity_status'] ?? '') !== $status
        ) {
            try {
                $upd = $pdo->prepare("
                    UPDATE member_attendance 
                    SET total_f2f = ?, total_zoom = ?, total_attended = ?, special_points = ?, points_used = ?, scheme_qualified = ?, activity_status = ? 
                    WHERE id = ?
                ");
                $upd->execute([$totalF2F, $totalZoom, $totalAttended, $totalSpecialPoints, $pointsUsed, $schemeQualified, $status, $row['id']]);
            } catch (Throwable $eSync) {}

            $row['total_f2f'] = $totalF2F;
            $row['total_zoom'] = $totalZoom;
            $row['total_attended'] = $totalAttended;
            $row['special_points'] = $totalSpecialPoints;
            $row['points_used'] = $pointsUsed;
            $row['scheme_qualified'] = $schemeQualified;
            $row['activity_status'] = $status;
        }

        $row['computed'] = [
            'monthly_f2f'            => $monthlyF2F,
            'monthly_zoom'           => $monthlyZoom,
            'monthly_total'          => $monthlyF2F + $monthlyZoom,
            'custom_gmm_f2f'         => $customGmmF2F,
            'custom_gmm_zoom'        => $customGmmZoom,
            'total_gmm_f2f'          => $totalGmmF2F,
            'total_gmm_zoom'         => $totalGmmZoom,
            'total_gmm_attended'     => $totalGmmAttended,
            'major_count'            => $totalMajorEvents,
            'major_breakdown'        => $majorBreakdown,
            'special_count'          => $specialEventsAttendedCount,
            'cpd_count'              => $totalCpdCount,
            'special_points'         => $totalSpecialPoints,
            'points_used'            => $pointsUsed,
            'remaining_points'       => $remainingSpecialPoints,
            'total_attended'         => $totalAttended,
            'total_f2f'              => $totalF2F,
            'total_zoom'             => $totalZoom,
            'status'                 => $status,
            'scheme_qualified'       => $schemeQualified,
            'scheme_label'           => $schemeLabel,
            'qualification_note'     => $qualificationNote,
            'badge_label'            => $badgeLabel,
            'badge_color'            => $badgeColor,
            'badge_bg'               => $badgeBg,
            'badge_border'           => $badgeBorder,
            'status_class'           => $statusClass,
            'description'            => $qualificationNote,
            'is_active'              => ($status === 'active'),
            'is_inactive'            => ($status === 'inactive'),
            'is_probationary'        => ($status === 'probationary'),
            'special_breakdown'      => $specialBreakdown,
            'f2f_scheme'             => [
                'gmm_f2f'            => $totalGmmF2F,
                'gmm_req'            => 7,
                'major_events'       => $totalMajorEvents,
                'major_req'          => 2,
                'def_gmm'            => $defGmmF2F,
                'def_major'          => $defMajorF2F,
                'points_needed'      => $pointsNeededF2F,
                'is_direct'          => $isF2FDirect,
                'is_compensated'     => $isF2FCompensated,
                'is_qualified'       => $isF2FQualified,
            ],
            'hybrid_scheme'          => [
                'gmm_f2f'            => $totalGmmF2F,
                'f2f_req'            => 4,
                'gmm_zoom'           => $totalGmmZoom,
                'zoom_req'           => 6,
                'major_events'       => $totalMajorEvents,
                'major_req'          => 2,
                'def_f2f'            => $defF2FHybrid,
                'def_zoom'           => $defZoomHybrid,
                'def_gmm'            => $defGmmHybrid,
                'def_major'          => $defMajorHybrid,
                'points_needed'      => $pointsNeededHybrid,
                'is_direct'          => $isHybridDirect,
                'is_compensated'     => $isHybridCompensated,
                'is_qualified'       => $isHybridQualified,
            ],
        ];

        return $row;
    }
}

if (!function_exists('render_activity_badge')) {
    function render_activity_badge(array $activity, bool $showDetails = false): string {
        $c = $activity['computed'] ?? [];
        $label = $c['badge_label'] ?? 'INACTIVE';
        $color = $c['badge_color'] ?? '#ef4444';
        $bg = $c['badge_bg'] ?? 'rgba(239, 68, 68, 0.15)';
        $border = $c['badge_border'] ?? '#ef4444';
        $icon = ($c['status'] ?? '') === 'active' ? 'check' : (($c['status'] ?? '') === 'inactive' ? 'alert' : 'clock');
        $desc = $c['qualification_note'] ?? ($c['description'] ?? '');
        $schemeLabel = $c['scheme_label'] ?? '';

        $html = '<span class="badge" style="background: ' . $bg . '; color: ' . $color . '; border: 1px solid ' . $border . '; font-weight: 800; font-size: 11px; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;" title="' . htmlspecialchars($desc) . '">';
        if (function_exists('icon')) {
            $html .= icon($icon, '', 12);
        }
        $html .= '<span>' . htmlspecialchars($label) . '</span>';
        $html .= '</span>';

        if ($showDetails) {
            $extra = '';
            if (!empty($schemeLabel) && $schemeLabel !== 'Inactive' && $schemeLabel !== 'In Progress') {
                $extra = htmlspecialchars($schemeLabel) . ' &bull; ';
            }
            $html .= '<span class="muted" style="font-size: 12px; margin-left: 6px;">(' . $extra . ($c['total_attended'] ?? 0) . ' events, ' . ($c['special_points'] ?? 0) . ' pts)</span>';
        }

        return $html;
    }
}


// ==============================================================================
// ADVANCED EVENT ATTENDANCE & ROLLING QR ENGINE
// ==============================================================================

if (!function_exists('get_attendance_secret_key')) {
    function get_attendance_secret_key(): string {
        $key = getenv('APP_KEY') ?: (defined('DB_PASS') ? DB_PASS : '');
        return hash('sha256', 'UAP_QUEZON_ATTENDANCE_SALT_2026_' . $key);
    }
}

if (!function_exists('get_available_chapter_events')) {
    function get_available_chapter_events(?PDO $db = null): array {
        global $pdo;
        $conn = $db ?: ($pdo ?? null);

        $categories = [
            'monthly' => [
                'title' => 'General Membership Meetings (GMM Defaults)',
                'events' => [
                    'month_july'      => ['label' => 'July 2026 GMM',      'month' => 7,  'year' => 2026],
                    'month_august'    => ['label' => 'August 2026 GMM',    'month' => 8,  'year' => 2026],
                    'month_september' => ['label' => 'September 2026 GMM', 'month' => 9,  'year' => 2026],
                    'month_october'   => ['label' => 'October 2026 GMM',   'month' => 10, 'year' => 2026],
                    'month_november'  => ['label' => 'November 2026 GMM',  'month' => 11, 'year' => 2026],
                    'month_december'  => ['label' => 'December 2026 GMM',  'month' => 12, 'year' => 2026],
                    'month_january'   => ['label' => 'January 2027 GMM',   'month' => 1,  'year' => 2027],
                    'month_february'  => ['label' => 'February 2027 GMM',  'month' => 2,  'year' => 2027],
                    'month_march'     => ['label' => 'March 2027 GMM',     'month' => 3,  'year' => 2027],
                    'month_april'     => ['label' => 'April 2027 GMM',     'month' => 4,  'year' => 2027],
                    'month_may'       => ['label' => 'May 2027 GMM',       'month' => 5,  'year' => 2027],
                    'month_june'      => ['label' => 'June 2027 GMM',      'month' => 6,  'year' => 2027],
                ]
            ],
            'major' => [
                'title' => 'Major Chapter Events & Milestones',
                'events' => [
                    'event_induction'       => ['label' => 'Chapter Induction of Officers & New Members', 'month' => 8, 'year' => 2026],
                    'event_anniversary'     => ['label' => 'UAP Quezon 49th Founding Anniversary',        'month' => 11, 'year' => 2026],
                    'event_christmas_party' => ['label' => 'Annual Architects Fellowship & Christmas Party','month' => 12, 'year' => 2026],
                ]
            ],
            'special' => [
                'title' => 'National & Special Programs (Board Reso. Sec. 5)',
                'events' => [
                    'event_natcon'        => ['label' => 'NATCON (National Convention) [3.0 pts]', 'month' => 4, 'year' => 2027],
                    'event_aba'           => ['label' => 'ABA (Area-B Assembly) [2.0 pts]', 'month' => 1, 'year' => 2027],
                    'event_nca'           => ['label' => 'National Conference of Architects (NCA) [2.0 pts]', 'month' => 10, 'year' => 2026],
                    'event_pafnam'        => ['label' => 'PAFNAM / National Architecture Month [2.0 pts]', 'month' => 2, 'year' => 2027],
                    'event_wad'           => ['label' => 'World Architecture Day (WAD) [1.0 pt]', 'month' => 10, 'year' => 2026],
                    'event_csr'           => ['label' => 'Community Service Project / CSR [1.0 pt]', 'month' => 3, 'year' => 2027],
                    'event_summer_outing' => ['label' => 'Summer Outing & Sports Fellowship [1.0 pt]', 'month' => 5, 'year' => 2027],
                ]
            ]
        ];

        // Dynamically include scheduled chapter meetings from chapter_events table
        if ($conn instanceof PDO) {
            try {
                $ceEvents = $conn->query("
                    SELECT id, title, event_type, event_date, start_time, end_time, location_type, venue_name, status, counts_for_standing,
                           CASE 
                               WHEN event_date = CURDATE() THEN 0 
                               WHEN event_date > CURDATE() THEN 1 
                               ELSE 2 
                           END as date_group
                    FROM chapter_events
                    WHERE deleted_at IS NULL
                    ORDER BY date_group ASC, 
                             CASE WHEN event_date >= CURDATE() THEN event_date END ASC,
                             event_date DESC,
                             start_time ASC
                    LIMIT 40
                ")->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($ceEvents)) {
                    $schedList = [];
                    $todayDate = date('Y-m-d');
                    foreach ($ceEvents as $ce) {
                        $key = 'ce_' . $ce['id'];
                        $dateStr = date('M j, Y', strtotime($ce['event_date']));
                        $typeMap = [
                            'gmm' => 'GMM', 'board_meeting' => 'BOARD', 'cpd_seminar' => 'CPD',
                            'fellowship' => 'FELLOWSHIP', 'assembly' => 'ASSEMBLY', 'major' => 'MAJOR', 'special' => 'SPECIAL'
                        ];
                        $typeBadge = $typeMap[$ce['event_type']] ?? strtoupper($ce['event_type']);
                        $isToday = ($ce['event_date'] === $todayDate);
                        $prefix = $isToday ? '[TODAY] ' : '';
                        $timeStr = !empty($ce['start_time']) ? ' @ ' . date('h:i A', strtotime($ce['start_time'])) : '';
                        $statusSuffix = ($ce['status'] === 'completed') ? ' [Completed]' : '';

                        $label = "{$prefix}[{$typeBadge}] {$ce['title']} ({$dateStr}{$timeStr}){$statusSuffix}";
                        $schedList[$key] = [
                            'label'         => $label,
                            'title'         => $ce['title'],
                            'id'            => (int)$ce['id'],
                            'event_date'    => $ce['event_date'],
                            'start_time'    => $ce['start_time'],
                            'end_time'      => $ce['end_time'] ?? null,
                            'location_type' => $ce['location_type'],
                            'venue_name'    => $ce['venue_name'],
                            'status'        => $ce['status'],
                            'is_today'      => $isToday,
                            'counts_for_standing' => (int)$ce['counts_for_standing']
                        ];
                    }
                    $categories = array_merge([
                        'scheduled' => [
                            'title' => 'Scheduled Chapter Meetings & Assemblies (Active / Admin Created)',
                            'events' => $schedList
                        ]
                    ], $categories);
                }
            } catch (Throwable $e) {}

            try {
                $newsEvents = $conn->query("
                    SELECT id, title, date_posted, date_display 
                    FROM news_announcements 
                    WHERE is_published = 1 AND deleted_at IS NULL 
                    ORDER BY date_posted DESC 
                    LIMIT 15
                ")->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($newsEvents)) {
                    $customList = [];
                    foreach ($newsEvents as $ne) {
                        $key = 'news_' . $ne['id'];
                        $dateStr = $ne['date_display'] ?: ($ne['date_posted'] ? date('M j, Y', strtotime($ne['date_posted'])) : '');
                        $label = $ne['title'] . ($dateStr ? " ($dateStr)" : '');
                        $customList[$key] = [
                            'label' => $label,
                            'title' => $ne['title'],
                            'id'    => (int)$ne['id']
                        ];
                    }
                    $categories['published'] = [
                        'title' => 'Published Seminars & Chapter Articles',
                        'events' => $customList
                    ];
                }
            } catch (Throwable $e) {}
        }

        return $categories;
    }
}

if (!function_exists('get_default_event_key')) {
    function get_default_event_key(?PDO $db = null): string {
        global $pdo;
        $conn = $db ?: ($pdo ?? null);

        if ($conn instanceof PDO) {
            try {
                // 1. Prioritize meeting scheduled for TODAY (not cancelled or deleted)
                $stmtToday = $conn->prepare("
                    SELECT id FROM chapter_events 
                    WHERE deleted_at IS NULL 
                      AND event_date = CURDATE()
                      AND status IN ('upcoming', 'ongoing')
                    ORDER BY start_time ASC, id ASC 
                    LIMIT 1
                ");
                $stmtToday->execute();
                $todayId = $stmtToday->fetchColumn();
                if ($todayId) {
                    return 'ce_' . $todayId;
                }

                // 2. If no event today, check if there is an upcoming event within the next 30 days
                $stmtUpcoming = $conn->prepare("
                    SELECT id FROM chapter_events 
                    WHERE deleted_at IS NULL 
                      AND event_date >= CURDATE()
                      AND status IN ('upcoming', 'ongoing')
                    ORDER BY event_date ASC, start_time ASC, id ASC 
                    LIMIT 1
                ");
                $stmtUpcoming->execute();
                $upcomingId = $stmtUpcoming->fetchColumn();
                if ($upcomingId) {
                    return 'ce_' . $upcomingId;
                }
            } catch (Throwable $e) {}
        }

        $curMonth = (int)date('n');
        $monthMap = [
            7 => 'month_july', 8 => 'month_august', 9 => 'month_september',
            10 => 'month_october', 11 => 'month_november', 12 => 'month_december',
            1 => 'month_january', 2 => 'month_february', 3 => 'month_march',
            4 => 'month_april', 5 => 'month_may', 6 => 'month_june'
        ];

        return $monthMap[$curMonth] ?? 'month_november';
    }
}

if (!function_exists('get_event_title_by_key')) {
    function get_event_title_by_key(string $key, ?PDO $pdo = null): string {
        global $pdo;
        $conn = $pdo ?: ($GLOBALS['pdo'] ?? null);

        if (str_starts_with($key, 'ce_') && $conn instanceof PDO) {
            try {
                $ceId = (int)substr($key, 3);
                $stmt = $conn->prepare("SELECT title, event_type, event_date, start_time FROM chapter_events WHERE id = ?");
                $stmt->execute([$ceId]);
                $row = $stmt->fetch();
                if ($row) {
                    $typeMap = [
                        'gmm' => 'GMM', 'board_meeting' => 'BOARD', 'cpd_seminar' => 'CPD',
                        'fellowship' => 'FELLOWSHIP', 'assembly' => 'ASSEMBLY', 'major' => 'MAJOR', 'special' => 'SPECIAL'
                    ];
                    $badge = $typeMap[$row['event_type']] ?? strtoupper($row['event_type']);
                    $timeStr = !empty($row['start_time']) ? ' @ ' . date('h:i A', strtotime($row['start_time'])) : '';
                    return "[{$badge}] {$row['title']} (" . date('M j, Y', strtotime($row['event_date'])) . "{$timeStr})";
                }
            } catch (Throwable $e) {}
        }

        $cats = get_available_chapter_events($conn);
        foreach ($cats as $cat) {
            if (isset($cat['events'][$key])) {
                return $cat['events'][$key]['label'];
            }
        }

        return ucwords(str_replace(['_', 'event', 'month', 'ce '], [' ', '', '', 'Meeting '], $key));
    }
}

if (!function_exists('generate_rolling_attendance_token')) {
    function generate_rolling_attendance_token(int $userId): array {
        $secret = get_attendance_secret_key();
        $timestamp = time();
        $salt = bin2hex(random_bytes(4));
        $payload = "UAPQ|v1|{$userId}|{$timestamp}|{$salt}";
        $sig = substr(hash_hmac('sha256', $payload, $secret), 0, 20);
        $token = "UAPQ:v1:{$userId}:{$timestamp}:{$salt}:{$sig}";

        return [
            'token'      => $token,
            'user_id'    => $userId,
            'timestamp'  => $timestamp,
            'expires_in' => 60,
            'expires_at' => $timestamp + 60,
            'server_time'=> $timestamp,
        ];
    }
}

if (!function_exists('verify_rolling_attendance_token')) {
    function verify_rolling_attendance_token(string $raw): array {
        $token = trim($raw);
        $parts = explode(':', $token);

        if (count($parts) !== 6 || $parts[0] !== 'UAPQ' || $parts[1] !== 'v1') {
            // Also support static member ID or profile URL fallback if scanned
            if (preg_match('/^(\d+)$/', $token, $m)) {
                return [
                    'valid'   => true,
                    'user_id' => (int)$m[1],
                    'is_static' => true,
                    'warning' => 'Static Member ID scanned (Rolling QR was not used).'
                ];
            }
            if (preg_match('#/profile/(\d+)#', $token, $m)) {
                return [
                    'valid'   => true,
                    'user_id' => (int)$m[1],
                    'is_static' => true,
                    'warning' => 'Public Profile QR scanned.'
                ];
            }

            return ['valid' => false, 'error' => 'Invalid QR Code format. Please present an official UAP Quezon pass.'];
        }

        $userId = (int)$parts[2];
        $timestamp = (int)$parts[3];
        $salt = $parts[4];
        $providedSig = $parts[5];

        if ($userId <= 0 || $timestamp <= 0) {
            return ['valid' => false, 'error' => 'Corrupt or unreadable QR token data.'];
        }

        $secret = get_attendance_secret_key();
        $payload = "UAPQ|v1|{$userId}|{$timestamp}|{$salt}";
        $expectedSig = substr(hash_hmac('sha256', $payload, $secret), 0, 20);

        if (!hash_equals($expectedSig, $providedSig)) {
            return ['valid' => false, 'error' => 'Counterfeit or tampered QR code rejected.'];
        }

        $now = time();
        $age = $now - $timestamp;

        // Tolerance: 75 seconds (60s active duration + 15s grace period)
        if ($age > 75) {
            return [
                'valid' => false,
                'expired' => true,
                'error' => "QR Pass has expired (generated " . $age . "s ago). Please present the live, auto-refreshing screen."
            ];
        }

        if ($age < -30) {
            return ['valid' => false, 'error' => 'Invalid device time detected.'];
        }

        return [
            'valid'   => true,
            'user_id' => $userId,
            'age_sec' => $age,
            'is_static' => false
        ];
    }
}

if (!function_exists('record_event_scan_attendance')) {
    function record_event_scan_attendance(PDO $pdo, int $userId, string $eventKey, ?int $adminId = null, string $mode = 'f2f', string $method = 'qr_scan', string $notes = ''): array {
        ensure_member_attendance_table($pdo);
        $fy = get_active_fiscal_year();

        // 1. Fetch member credentials & photo
        $uStmt = $pdo->prepare("SELECT id, name, email, id_number, prc_number, profile_photo, status FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $member = $uStmt->fetch();

        if (!$member) {
            return ['success' => false, 'error' => "Architect Member ID #$userId not found in database."];
        }

        // Try to fetch directory photo if users.profile_photo is empty
        $photo = $member['profile_photo'];
        try {
            $wmStmt = $pdo->prepare("SELECT photo_path FROM website_members WHERE user_id = ?");
            $wmStmt->execute([$userId]);
            $wmPhoto = $wmStmt->fetchColumn();
            if ($wmPhoto && (!$photo || !file_exists(__DIR__ . '/../' . ltrim($photo, '/')))) {
                $photo = $wmPhoto;
            }
        } catch (Throwable $e) {}

        $memberData = [
            'id'         => (int)$member['id'],
            'name'       => $member['name'],
            'email'      => $member['email'],
            'id_number'  => $member['id_number'] ?: '—',
            'prc_number' => $member['prc_number'] ?: '—',
            'photo'      => $photo ? (str_starts_with($photo, 'http') ? $photo : BASE_URL . '/' . ltrim($photo, '/')) : null,
        ];

        $eventTitle = get_event_title_by_key($eventKey, $pdo);

        // 2. Check for duplicate scan
        $chk = $pdo->prepare("SELECT * FROM event_attendance_logs WHERE user_id = ? AND fiscal_year = ? AND event_key = ? LIMIT 1");
        $chk->execute([$userId, $fy, $eventKey]);
        $existingLog = $chk->fetch();

        if ($existingLog) {
            $scannedAtFormatted = date('h:i:s A', strtotime($existingLog['scanned_at']));
            return [
                'success'      => false,
                'duplicate'    => true,
                'error'        => "ALREADY CHECKED IN at {$scannedAtFormatted}",
                'scanned_at'   => $existingLog['scanned_at'],
                'scanned_time' => $scannedAtFormatted,
                'member'       => $memberData,
                'event_title'  => $eventTitle,
            ];
        }

        // 3. Insert into event_attendance_logs (using explicit Philippine Standard Time)
        $scannedAtNow = date('Y-m-d H:i:s');
        $logStmt = $pdo->prepare("
            INSERT INTO event_attendance_logs 
            (user_id, fiscal_year, event_key, event_title, attendance_mode, scanned_at, scanned_by, verification_method, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $logStmt->execute([$userId, $fy, $eventKey, $eventTitle, $mode, $scannedAtNow, $adminId, $method, $notes]);
        $newLogId = (int)$pdo->lastInsertId();

        // 4. Update official member_attendance compliance record
        $rec = get_or_create_member_attendance($pdo, $userId, $fy);

        if (str_starts_with($eventKey, 'month_')) {
            $upd = $pdo->prepare("UPDATE member_attendance SET {$eventKey} = ? WHERE id = ?");
            $upd->execute([$mode, $rec['id']]);
        } elseif (str_starts_with($eventKey, 'event_')) {
            $upd = $pdo->prepare("UPDATE member_attendance SET {$eventKey} = 1 WHERE id = ?");
            $upd->execute([$rec['id']]);
        }

        // 5. Recalculate standing
        $updatedRec = get_or_create_member_attendance($pdo, $userId, $fy);
        $computed = $updatedRec['computed'];

        // 6. Audit log
        if (function_exists('audit_log')) {
            audit_log('ATTENDANCE_SCAN', 'ATTENDANCE', "Scanned check-in for Ar. {$member['name']} at {$eventTitle}. Standing: {$computed['status']}", $adminId);
        }

        return [
            'success'      => true,
            'duplicate'    => false,
            'log_id'       => $newLogId,
            'message'      => 'ATTENDANCE CONFIRMED',
            'scanned_at'   => date('Y-m-d H:i:s'),
            'scanned_time' => date('h:i:s A'),
            'member'       => $memberData,
            'event_key'    => $eventKey,
            'event_title'  => $eventTitle,
            'activity'     => $computed,
        ];
    }
}

if (!function_exists('get_event_attendance_logs')) {
    function get_event_attendance_logs(PDO $pdo, string $eventKey = '', string $fiscalYear = '2026-2027', int $limit = 50): array {
        $sql = "
            SELECT l.*, u.name, u.prc_number, u.id_number, u.profile_photo,
                   admin_u.name as admin_name
            FROM event_attendance_logs l
            JOIN users u ON l.user_id = u.id
            LEFT JOIN users admin_u ON l.scanned_by = admin_u.id
            WHERE l.fiscal_year = ?
        ";
        $params = [$fiscalYear];

        if ($eventKey !== '') {
            $sql .= " AND l.event_key = ?";
            $params[] = $eventKey;
        }

        $sql .= " ORDER BY l.scanned_at DESC LIMIT " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
