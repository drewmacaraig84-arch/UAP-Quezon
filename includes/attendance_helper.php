<?php
/**
 * UAP Quezon - Attendance & Activity Status Helper
 * 
 * Logic:
 * - >= 7 meetings / events attended => ACTIVE (Green badge)
 * - <= 2 meetings / events attended => INACTIVE (Red badge)
 * - 3 to 6 meetings / events attended => PARTIALLY ACTIVE (Amber badge)
 */

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
            'event_anniversary'     => 'UAP Quezon Chapter Founding Anniversary',
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
    function calculate_and_sync_activity_status(PDO $pdo, array $row): array {
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

        $monthlyTotal = $monthlyF2F + $monthlyZoom;
        $totalAttended = $monthlyTotal + $majorCount + $specialCount;
        $totalF2F = $monthlyF2F + $majorCount + $specialCount;
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
