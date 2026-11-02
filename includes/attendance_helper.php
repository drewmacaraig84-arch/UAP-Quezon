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
                    event_summer_outing TINYINT(1) NOT NULL DEFAULT 0,
                    event_csr TINYINT(1) NOT NULL DEFAULT 0,
                    total_f2f INT NOT NULL DEFAULT 0,
                    total_zoom INT NOT NULL DEFAULT 0,
                    total_attended INT NOT NULL DEFAULT 0,
                    activity_status ENUM('active','inactive','probationary') NOT NULL DEFAULT 'inactive',
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_user_fy (user_id, fiscal_year),
                    KEY idx_user_id (user_id),
                    KEY idx_activity_status (activity_status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {}
    }
}

if (isset($pdo) && $pdo instanceof PDO) {
    ensure_member_attendance_table($pdo);
}

if (!function_exists('get_active_fiscal_year')) {
    function get_active_fiscal_year(): string {
        return '2026-2027';
    }
}

if (!function_exists('get_member_attendance_months')) {
    function get_member_attendance_months(): array {
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
    function get_major_events_list(): array {
        return [
            'event_induction'       => 'Chapter Induction of Officers & New Members',
            'event_anniversary'     => 'UAP Quezon Province Chapter Founding Anniversary',
            'event_christmas_party' => 'Annual Architects Fellowship & Christmas Party',
        ];
    }
}

if (!function_exists('get_special_events_list')) {
    function get_special_events_list(): array {
        return [
            'event_wad'           => 'WAD (World Architecture Day)',
            'event_nca'           => 'NCA (National Conference of Architects)',
            'event_pafnam'        => 'PAFNAM / Area Assembly Convention',
            'event_natcon'        => 'NATCON (UAP National Convention)',
            'event_summer_outing' => 'Summer Outing & Sports Fellowship',
            'event_csr'           => 'CSR (Corporate Social Responsibility & Community Project)',
        ];
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
        $months = get_member_attendance_months();
        $monthlyF2F = 0;
        $monthlyZoom = 0;

        foreach ($months as $key => $meta) {
            $val = strtolower($row['month_' . $key] ?? 'absent');
            if ($val === 'f2f') {
                $monthlyF2F++;
            } elseif ($val === 'zoom') {
                $monthlyZoom++;
            }
        }

        $majorEvents = get_major_events_list();
        $majorCount = 0;
        foreach ($majorEvents as $col => $name) {
            if (!empty($row[$col])) {
                $majorCount++;
            }
        }

        $specialEvents = get_special_events_list();
        $specialCount = 0;
        foreach ($specialEvents as $col => $name) {
            if (!empty($row[$col])) {
                $specialCount++;
            }
        }

        // Count custom scheduled meetings attended from event_attendance_logs
        $customMeetingsCount = 0;
        try {
            $cStmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM event_attendance_logs l
                JOIN chapter_events ce ON l.event_key = CONCAT('ce_', ce.id)
                WHERE l.user_id = ? AND l.fiscal_year = ? AND ce.counts_for_standing = 1 AND ce.deleted_at IS NULL
            ");
            $cStmt->execute([$row['user_id'], $row['fiscal_year']]);
            $customMeetingsCount = (int)$cStmt->fetchColumn();
        } catch (Throwable $e) {}

        $monthlyTotal = $monthlyF2F + $monthlyZoom;
        $totalAttended = $monthlyTotal + $majorCount + $specialCount + $customMeetingsCount;
        $totalF2F = $monthlyF2F + $majorCount + $specialCount + $customMeetingsCount;
        $totalZoom = $monthlyZoom;

        // Determine Status based on client rule:
        // >= 7 => ACTIVE
        // <= 2 => INACTIVE
        // 3 to 6 => PARTIALLY ACTIVE (In progress to 7)
        if ($totalAttended >= 7) {
            $status = 'active';
            $badgeColor = '#10b981';
            $badgeBg = 'rgba(16, 185, 129, 0.15)';
            $badgeBorder = '#10b981';
            $badgeLabel = 'ACTIVE';
            $statusClass = 'badge-paid';
            $description = 'Active Member (7+ meetings/events attended)';
        } elseif ($totalAttended <= 2) {
            $status = 'inactive';
            $badgeColor = '#ef4444';
            $badgeBg = 'rgba(239, 68, 68, 0.15)';
            $badgeBorder = '#ef4444';
            $badgeLabel = 'INACTIVE';
            $statusClass = 'badge-unpaid';
            $description = 'Inactive Member (Less than 2 meetings/events attended)';
        } else {
            $status = 'probationary';
            $badgeColor = '#f59e0b';
            $badgeBg = 'rgba(245, 158, 11, 0.15)';
            $badgeBorder = '#f59e0b';
            $badgeLabel = 'PARTIALLY ACTIVE';
            $statusClass = 'badge-partial';
            $description = "$totalAttended of 7 events attended (Needs " . (7 - $totalAttended) . " more to achieve Active status)";
        }

        // Sync to DB if cached values differ
        if (
            (int)$row['total_f2f'] !== $totalF2F ||
            (int)$row['total_zoom'] !== $totalZoom ||
            (int)$row['total_attended'] !== $totalAttended ||
            $row['activity_status'] !== $status
        ) {
            $upd = $pdo->prepare("
                UPDATE member_attendance 
                SET total_f2f = ?, total_zoom = ?, total_attended = ?, activity_status = ? 
                WHERE id = ?
            ");
            $upd->execute([$totalF2F, $totalZoom, $totalAttended, $status, $row['id']]);

            $row['total_f2f'] = $totalF2F;
            $row['total_zoom'] = $totalZoom;
            $row['total_attended'] = $totalAttended;
            $row['activity_status'] = $status;
        }

        $row['computed'] = [
            'monthly_f2f'     => $monthlyF2F,
            'monthly_zoom'    => $monthlyZoom,
            'monthly_total'   => $monthlyTotal,
            'major_count'     => $majorCount,
            'special_count'   => $specialCount,
            'total_attended'  => $totalAttended,
            'total_f2f'       => $totalF2F,
            'total_zoom'      => $totalZoom,
            'status'          => $status,
            'badge_label'     => $badgeLabel,
            'badge_color'     => $badgeColor,
            'badge_bg'        => $badgeBg,
            'badge_border'    => $badgeBorder,
            'status_class'    => $statusClass,
            'description'     => $description,
            'is_active'       => ($status === 'active'),
            'is_inactive'     => ($status === 'inactive'),
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
        $desc = $c['description'] ?? '';

        $html = '<span class="badge" style="background: ' . $bg . '; color: ' . $color . '; border: 1px solid ' . $border . '; font-weight: 800; font-size: 11px; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;" title="' . htmlspecialchars($desc) . '">';
        if (function_exists('icon')) {
            $html .= icon($icon, '', 12);
        }
        $html .= '<span>' . htmlspecialchars($label) . '</span>';
        $html .= '</span>';

        if ($showDetails) {
            $html .= '<span class="muted" style="font-size: 12px; margin-left: 6px;">(' . ($c['total_attended'] ?? 0) . ' events attended: ' . ($c['total_f2f'] ?? 0) . ' F2F, ' . ($c['total_zoom'] ?? 0) . ' Zoom)</span>';
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
                'title' => 'General Membership Meetings (GMM)',
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
                'title' => 'National & Special Programs',
                'events' => [
                    'event_wad'           => ['label' => 'World Architecture Day (WAD)', 'month' => 10, 'year' => 2026],
                    'event_nca'           => ['label' => 'National Conference of Architects (NCA)', 'month' => 10, 'year' => 2026],
                    'event_pafnam'        => ['label' => 'PAFNAM / Area Assembly Convention', 'month' => 2, 'year' => 2027],
                    'event_natcon'        => ['label' => 'NATCON (UAP National Convention)', 'month' => 4, 'year' => 2027],
                    'event_summer_outing' => ['label' => 'Summer Outing & Sports Fellowship', 'month' => 5, 'year' => 2027],
                    'event_csr'           => ['label' => 'Community Service Project / CSR', 'month' => 3, 'year' => 2027],
                ]
            ]
        ];

        // Dynamically include scheduled chapter meetings from chapter_events table
        if ($conn instanceof PDO) {
            try {
                $ceEvents = $conn->query("
                    SELECT id, title, event_type, event_date, start_time, location_type, venue_name, status, counts_for_standing
                    FROM chapter_events
                    WHERE deleted_at IS NULL
                    ORDER BY event_date DESC, start_time DESC
                    LIMIT 25
                ")->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($ceEvents)) {
                    $schedList = [];
                    foreach ($ceEvents as $ce) {
                        $key = 'ce_' . $ce['id'];
                        $dateStr = date('M j, Y', strtotime($ce['event_date']));
                        $typeMap = [
                            'gmm' => 'GMM', 'board_meeting' => 'BOARD', 'cpd_seminar' => 'CPD',
                            'fellowship' => 'FELLOWSHIP', 'assembly' => 'ASSEMBLY', 'major' => 'MAJOR', 'special' => 'SPECIAL'
                        ];
                        $typeBadge = $typeMap[$ce['event_type']] ?? strtoupper($ce['event_type']);
                        $label = "[{$typeBadge}] {$ce['title']} ({$dateStr})";
                        $schedList[$key] = [
                            'label'         => $label,
                            'title'         => $ce['title'],
                            'id'            => (int)$ce['id'],
                            'event_date'    => $ce['event_date'],
                            'location_type' => $ce['location_type'],
                            'venue_name'    => $ce['venue_name'],
                            'status'        => $ce['status'],
                            'counts_for_standing' => (int)$ce['counts_for_standing']
                        ];
                    }
                    $categories = array_merge([
                        'scheduled' => [
                            'title' => 'Scheduled Chapter Meetings & Assemblies',
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
    function get_default_event_key(): string {
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
    function get_event_title_by_key(string $key): string {
        $cats = get_available_chapter_events();
        foreach ($cats as $cat) {
            if (isset($cat['events'][$key])) {
                return $cat['events'][$key]['label'];
            }
        }

        // Direct database lookup if ce_
        if (str_starts_with($key, 'ce_') && isset($GLOBALS['pdo'])) {
            try {
                $ceId = (int)substr($key, 3);
                $stmt = $GLOBALS['pdo']->prepare("SELECT title, event_date FROM chapter_events WHERE id = ?");
                $stmt->execute([$ceId]);
                $row = $stmt->fetch();
                if ($row) {
                    return $row['title'] . ' (' . date('M j, Y', strtotime($row['event_date'])) . ')';
                }
            } catch (Throwable $e) {}
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
    function record_event_scan_attendance(PDO $pdo, int $userId, string $eventKey, int $adminId, string $mode = 'f2f', string $method = 'qr_scan', string $notes = ''): array {
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

        $eventTitle = get_event_title_by_key($eventKey);

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

        // 3. Insert into event_attendance_logs
        $logStmt = $pdo->prepare("
            INSERT INTO event_attendance_logs 
            (user_id, fiscal_year, event_key, event_title, attendance_mode, scanned_at, scanned_by, verification_method, notes)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)
        ");
        $logStmt->execute([$userId, $fy, $eventKey, $eventTitle, $mode, $adminId, $method, $notes]);
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
