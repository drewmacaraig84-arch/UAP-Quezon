<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/attendance_helper.php';
require_admin();

$fy = trim($_GET['fy'] ?? get_active_fiscal_year());
$filterCategory = trim($_GET['category'] ?? 'all');
$viewMode = trim($_GET['view'] ?? 'grouped'); // 'grouped' or 'table'

// Chapter Site Settings
$settingsRows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$chapterLogo = $settingsRows['logo'] ?? 'public/logo.jpg';
$chapterName = $settingsRows['org_name'] ?? 'United Architects of the Philippines - Quezon Province Chapter';
$currentDate = date('F j, Y');
$currentTime = date('h:i A');

// Fetch All Members
try {
    $membersQuery = $pdo->query("
        SELECT u.id, u.name, u.id_number, u.status, u.email, u.phone,
            COALESCE(u.good_standing_override, 'auto') as good_standing_override,
            u.good_standing_reason,
            u.good_standing_updated_at,
            SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN md.status = 'partial' THEN 1 ELSE 0 END) as partial_count,
            SUM(CASE WHEN md.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN md.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
            COUNT(md.id) as total_dues,
            SUM(COALESCE(md.custom_amount, d.amount)) as total_amount,
            SUM(md.total_paid) as total_paid_sum,
            SUM(COALESCE(md.custom_amount, d.amount)) - SUM(md.total_paid) as remaining_balance
        FROM users u
        LEFT JOIN member_dues md ON md.user_id = u.id
        LEFT JOIN dues d ON md.due_id = d.id
        WHERE u.role = 'member'
        GROUP BY u.id, u.name, u.id_number, u.status, u.email, u.phone, u.good_standing_override, u.good_standing_reason, u.good_standing_updated_at
        ORDER BY u.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $membersQuery = $pdo->query("
        SELECT u.id, u.name, u.id_number, u.status, u.email, u.phone,
            'auto' as good_standing_override,
            NULL as good_standing_reason,
            NULL as good_standing_updated_at,
            0 as paid_count, 0 as partial_count, 0 as pending_count, 0 as unpaid_count,
            0 as total_dues, 0 as total_amount, 0 as total_paid_sum, 0 as remaining_balance
        FROM users u
        WHERE u.role = 'member'
        ORDER BY u.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Process and enrich member data
$processedMembers = [];
$totalGoodStanding = 0;
$totalNotGoodStanding = 0;
$totalActive = 0;
$totalInactive = 0;
$totalFullyPaid = 0;

$groupActiveGood = [];
$groupInactiveGood = [];
$groupOtherStanding = [];

foreach ($membersQuery as $m) {
    $standing = get_member_standing_details($pdo, (int)$m['id']);
    $mAtt = get_or_create_member_attendance($pdo, (int)$m['id'], $fy);
    $ac = $mAtt['computed'] ?? [];

    $isGood = !empty($standing['is_good']);
    $isRevoked = !empty($standing['is_revoked']);
    $activityLabel = strtoupper($ac['badge_label'] ?? 'INACTIVE');
    $isActive = ($activityLabel === 'ACTIVE');
    $attendedCount = (int)($ac['total_attended'] ?? 0);
    $remBalance = max(0, (float)($m['remaining_balance'] ?? 0));
    $isPaid = ($m['total_dues'] > 0 && $m['paid_count'] == $m['total_dues']);

    if ($isGood) $totalGoodStanding++;
    else $totalNotGoodStanding++;

    if ($isActive) $totalActive++;
    else $totalInactive++;

    if ($isPaid) $totalFullyPaid++;

    // Categorization
    $categoryKey = '';
    $categoryLabel = '';
    if ($isGood && $isActive) {
        $categoryKey = 'active_good';
        $categoryLabel = 'Active in Good Standing';
    } elseif ($isGood && !$isActive) {
        $categoryKey = 'inactive_good';
        $categoryLabel = 'Good Standing (Inactive Attendance)';
    } else {
        $categoryKey = 'non_good';
        $categoryLabel = $isRevoked ? 'Standing Revoked / Hold' : 'Pending / Not in Good Standing';
    }

    $item = array_merge($m, [
        'standing' => $standing,
        'attendance' => $ac,
        'is_good' => $isGood,
        'is_revoked' => $isRevoked,
        'activity_label' => $activityLabel,
        'is_active' => $isActive,
        'attended_count' => $attendedCount,
        'remaining_balance' => $remBalance,
        'is_paid' => $isPaid,
        'category_key' => $categoryKey,
        'category_label' => $categoryLabel
    ]);

    // Apply Filter if selected
    if ($filterCategory === 'good_standing' && !$isGood) continue;
    if ($filterCategory === 'not_good_standing' && $isGood) continue;
    if ($filterCategory === 'active' && !$isActive) continue;
    if ($filterCategory === 'inactive' && $isActive) continue;

    $processedMembers[] = $item;

    if ($categoryKey === 'active_good') {
        $groupActiveGood[] = $item;
    } elseif ($categoryKey === 'inactive_good') {
        $groupInactiveGood[] = $item;
    } else {
        $groupOtherStanding[] = $item;
    }
}

$totalDisplayCount = count($processedMembers);
$adminUser = current_user();
$adminName = $adminUser['name'] ?? 'Chapter Administrator';
$pdfFilename = "UAP_Quezon_Members_Masterlist_" . date('Ymd');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Members Masterlist • UAP Quezon Province Chapter</title>
    <link rel="icon" type="image/jpeg" href="<?php echo BASE_URL; ?>/public/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <style>
        *, *:before, *:after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #0b1120;
            color: #1e293b;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.45;
            padding: 24px 16px 60px;
        }

        /* SCREEN-ONLY TOOLBAR */
        .pdf-toolbar {
            max-width: 1040px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #131d33;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            gap: 12px;
            flex-wrap: wrap;
        }

        .pdf-toolbar-title {
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pdf-btn-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pdf-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82rem;
            font-weight: 700;
            padding: 7px 14px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
        }

        .pdf-btn-primary {
            background: #f59e0b;
            color: #0b1120;
        }
        .pdf-btn-primary:hover {
            background: #fbbf24;
            transform: translateY(-1px);
        }

        .pdf-btn-danger {
            background: rgba(239, 68, 68, 0.18);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.35);
        }
        .pdf-btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .pdf-btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .pdf-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #f59e0b;
        }

        /* Filter Pills */
        .filter-pills {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .filter-pill {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            color: #cbd5e1;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.2s;
        }
        .filter-pill:hover {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border-color: rgba(245, 158, 11, 0.3);
        }
        .filter-pill.active {
            background: #f59e0b;
            color: #0f172a;
            border-color: #f59e0b;
        }

        /* PRINTABLE SHEET CONTAINER (Landscape-optimized, High-Fidelity) */
        .pdf-sheet-wrapper {
            max-width: 1040px;
            margin: 0 auto;
        }

        .pdf-sheet {
            background: #ffffff;
            color: #0f172a;
            width: 100%;
            min-height: 800px;
            padding: 40px 45px 50px;
            border-radius: 6px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6);
            position: relative;
            box-sizing: border-box;
        }

        /* Header Layout */
        .pdf-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 2.5px solid #0f172a;
            margin-bottom: 22px;
            gap: 20px;
        }

        .pdf-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .pdf-seal {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #b45309;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            flex-shrink: 0;
        }

        .pdf-seal-fallback {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: #0f172a;
            color: #fbbf24;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 16px;
            border: 2px solid #d97706;
            flex-shrink: 0;
        }

        .pdf-title-block h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 19px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 3px 0;
            line-height: 1.2;
        }

        .pdf-title-block h2 {
            font-size: 11.5px;
            font-weight: 700;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 0 0 4px 0;
        }

        .pdf-title-block p {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            margin: 0;
        }

        .pdf-meta-box {
            text-align: right;
            border-left: 2px solid #e2e8f0;
            padding-left: 18px;
            min-width: 170px;
        }

        .pdf-meta-label {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 2px;
        }

        .pdf-meta-val {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        /* KPI Summary Grid */
        .pdf-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .pdf-kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
        }

        .pdf-kpi-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .pdf-kpi-num {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            font-family: 'Space Grotesk', sans-serif;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .pdf-kpi-sub {
            font-size: 10.5px;
            font-weight: 600;
            color: #64748b;
        }

        /* Section Header */
        .category-header {
            background: #f1f5f9;
            border-left: 4px solid #f59e0b;
            padding: 8px 14px;
            margin: 20px 0 10px;
            border-radius: 0 6px 6px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .category-header h3 {
            font-size: 12.5px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .category-badge {
            font-size: 10.5px;
            font-weight: 700;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 2px 8px;
            border-radius: 12px;
            color: #475569;
        }

        /* Masterlist Table */
        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
            margin-bottom: 18px;
        }

        .pdf-table th {
            background: #0f172a;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.6px;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #0f172a;
        }

        .pdf-table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            vertical-align: middle;
        }

        .pdf-table tbody tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Badges inside PDF */
        .pdf-badge {
            display: inline-block;
            font-size: 9.5px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            white-space: nowrap;
            line-height: 1.2;
        }

        .pdf-badge-good {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .pdf-badge-warn {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .pdf-badge-active {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #6ee7b7;
        }

        .pdf-badge-inactive {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }

        .pdf-badge-paid {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .pdf-badge-partial {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        /* Signatures block */
        .pdf-signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 36px;
            padding-top: 24px;
            border-top: 1.5px solid #e2e8f0;
            page-break-inside: avoid;
        }

        .pdf-sig-box {
            text-align: center;
        }

        .pdf-sig-role {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 34px;
        }

        .pdf-sig-line {
            width: 80%;
            margin: 0 auto 6px;
            border-bottom: 1.5px solid #0f172a;
        }

        .pdf-sig-name {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }

        .pdf-sig-title {
            font-size: 10px;
            color: #64748b;
        }

        /* Legal / Verification Disclaimer */
        .pdf-footer-note {
            margin-top: 28px;
            font-size: 9.5px;
            color: #64748b;
            text-align: center;
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
            line-height: 1.4;
        }

        /* PRINT MEDIA STYLES */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
            }

            .pdf-toolbar {
                display: none !important;
            }

            .pdf-sheet-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
            }

            .pdf-sheet {
                box-shadow: none !important;
                padding: 20px 24px !important;
                border-radius: 0 !important;
            }

            @page {
                size: A4 landscape;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

    <!-- SCREEN-ONLY CONTROL TOOLBAR -->
    <div class="pdf-toolbar">
        <div class="pdf-toolbar-title">
            <span>Official Chapter Masterlist • Export &amp; Download</span>
        </div>

        <div class="filter-pills">
            <a href="?category=all&view=<?php echo urlencode($viewMode); ?>" class="filter-pill <?php echo $filterCategory === 'all' ? 'active' : ''; ?>">
                All Members (<?php echo count($membersQuery); ?>)
            </a>
            <a href="?category=active&view=<?php echo urlencode($viewMode); ?>" class="filter-pill <?php echo $filterCategory === 'active' ? 'active' : ''; ?>">
                Active Only (<?php echo $totalActive; ?>)
            </a>
            <a href="?category=inactive&view=<?php echo urlencode($viewMode); ?>" class="filter-pill <?php echo $filterCategory === 'inactive' ? 'active' : ''; ?>">
                Inactive Only (<?php echo $totalInactive; ?>)
            </a>
            <a href="?category=good_standing&view=<?php echo urlencode($viewMode); ?>" class="filter-pill <?php echo $filterCategory === 'good_standing' ? 'active' : ''; ?>">
                Good Standing (<?php echo $totalGoodStanding; ?>)
            </a>
        </div>

        <div class="pdf-btn-group">
            <a href="?category=<?php echo urlencode($filterCategory); ?>&view=<?php echo $viewMode === 'grouped' ? 'table' : 'grouped'; ?>" class="pdf-btn pdf-btn-secondary" title="Toggle Category Grouping">
                <?php echo $viewMode === 'grouped' ? '📋 Unified Table' : '📑 Grouped by Category'; ?>
            </a>
            <button type="button" onclick="downloadAsPdf()" class="pdf-btn pdf-btn-primary" title="Download direct PDF File">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                <span>Download PDF File</span>
            </button>
            <button type="button" onclick="window.print()" class="pdf-btn pdf-btn-secondary" title="Print Masterlist">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                <span>Print</span>
            </button>
            <a href="members.php" class="pdf-btn pdf-btn-danger">
                <span>&larr; Back to Portal</span>
            </a>
        </div>
    </div>

    <!-- PRINTABLE SHEET CONTAINER -->
    <div class="pdf-sheet-wrapper">
        <div id="membersPdfSheet" class="pdf-sheet">
            
            <!-- OFFICIAL HEADER -->
            <div class="pdf-header">
                <div class="pdf-brand">
                    <?php if (!empty($chapterLogo) && file_exists(__DIR__ . '/../' . $chapterLogo)): ?>
                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($chapterLogo); ?>" alt="UAP Logo" class="pdf-seal">
                    <?php else: ?>
                        <div class="pdf-seal-fallback">UAP</div>
                    <?php endif; ?>
                    <div class="pdf-title-block">
                        <h2>United Architects of the Philippines</h2>
                        <h1>Quezon Province Chapter</h1>
                        <p>The Integrated and Accredited Professional Organization of Architects (IAPOA) • Area B • District B4</p>
                        <p style="font-weight: 700; color: #0f172a; margin-top: 3px; font-size: 11.5px;">
                            OFFICIAL MEMBERS MASTERLIST &amp; COMPLIANCE ROSTER
                        </p>
                    </div>
                </div>

                <div class="pdf-meta-box">
                    <div class="pdf-meta-label">Fiscal Year</div>
                    <div class="pdf-meta-val">FY <?php echo htmlspecialchars($fy); ?></div>
                    <div class="pdf-meta-label">Date Generated</div>
                    <div class="pdf-meta-val" style="font-size: 11.5px;"><?php echo $currentDate; ?></div>
                    <div class="pdf-meta-label">Total Roster</div>
                    <div class="pdf-meta-val" style="color: #b45309;"><?php echo $totalDisplayCount; ?> Architects</div>
                </div>
            </div>

            <!-- KPI SUMMARY BANNER -->
            <div class="pdf-kpi-grid">
                <div class="pdf-kpi-card">
                    <div class="pdf-kpi-title">Total Registered</div>
                    <div class="pdf-kpi-num"><?php echo count($membersQuery); ?> <span class="pdf-kpi-sub">Architects</span></div>
                </div>
                <div class="pdf-kpi-card" style="border-left: 3px solid #10b981;">
                    <div class="pdf-kpi-title">In Good Standing</div>
                    <div class="pdf-kpi-num" style="color: #059669;"><?php echo $totalGoodStanding; ?> <span class="pdf-kpi-sub">Certified (<?php echo count($membersQuery) > 0 ? round(($totalGoodStanding/count($membersQuery))*100) : 0; ?>%)</span></div>
                </div>
                <div class="pdf-kpi-card" style="border-left: 3px solid #3b82f6;">
                    <div class="pdf-kpi-title">Active Attendance</div>
                    <div class="pdf-kpi-num" style="color: #2563eb;"><?php echo $totalActive; ?> <span class="pdf-kpi-sub">Compliant</span></div>
                </div>
                <div class="pdf-kpi-card" style="border-left: 3px solid #f59e0b;">
                    <div class="pdf-kpi-title">Inactive Attendance</div>
                    <div class="pdf-kpi-num" style="color: #d97706;"><?php echo $totalInactive; ?> <span class="pdf-kpi-sub">Members</span></div>
                </div>
            </div>

            <?php if ($viewMode === 'grouped' && $filterCategory === 'all'): ?>
                <!-- ================= SECTION 1: ACTIVE IN GOOD STANDING ================= -->
                <div class="category-header" style="border-left-color: #10b981;">
                    <h3>⭐ Category A: Active Members in Good Standing (Compliant)</h3>
                    <span class="category-badge" style="color: #059669; font-weight: 800;"><?php echo count($groupActiveGood); ?> Architects</span>
                </div>
                <?php render_pdf_members_table($groupActiveGood, 1); ?>

                <!-- ================= SECTION 2: GOOD STANDING BUT INACTIVE ================= -->
                <div class="category-header" style="border-left-color: #f59e0b; margin-top: 26px;">
                    <h3>⏳ Category B: Good Standing (Inactive Attendance Roster)</h3>
                    <span class="category-badge" style="color: #d97706; font-weight: 800;"><?php echo count($groupInactiveGood); ?> Architects</span>
                </div>
                <?php render_pdf_members_table($groupInactiveGood, count($groupActiveGood) + 1); ?>

                <!-- ================= SECTION 3: OTHER / REVOKED / PENDING ================= -->
                <?php if (count($groupOtherStanding) > 0): ?>
                    <div class="category-header" style="border-left-color: #ef4444; margin-top: 26px;">
                        <h3>⚠️ Category C: Standing Revoked / Pending Settlement</h3>
                        <span class="category-badge" style="color: #dc2626; font-weight: 800;"><?php echo count($groupOtherStanding); ?> Architects</span>
                    </div>
                    <?php render_pdf_members_table($groupOtherStanding, count($groupActiveGood) + count($groupInactiveGood) + 1); ?>
                <?php endif; ?>

            <?php else: ?>
                <!-- UNIFIED FULL TABLE -->
                <div class="category-header" style="border-left-color: #3b82f6;">
                    <h3>
                        <?php 
                        if ($filterCategory === 'active') echo 'Active Attendance Compliant Members';
                        elseif ($filterCategory === 'inactive') echo 'Inactive Attendance Chapter Members';
                        elseif ($filterCategory === 'good_standing') echo 'Certified Good Standing Members';
                        elseif ($filterCategory === 'not_good_standing') echo 'Pending / Non-Standing Members';
                        else echo 'Chapter Members Directory Masterlist';
                        ?>
                    </h3>
                    <span class="category-badge"><?php echo count($processedMembers); ?> Total Listed</span>
                </div>
                <?php render_pdf_members_table($processedMembers, 1); ?>
            <?php endif; ?>

            <!-- OFFICIAL SIGNATURES BLOCK -->
            <div class="pdf-signatures">
                <div class="pdf-sig-box">
                    <div class="pdf-sig-role">Prepared &amp; Attested By</div>
                    <div class="pdf-sig-line"></div>
                    <div class="pdf-sig-name">Chapter Secretariat</div>
                    <div class="pdf-sig-title">Records &amp; Membership Committee</div>
                </div>

                <div class="pdf-sig-box">
                    <div class="pdf-sig-role">Financial Clearance By</div>
                    <div class="pdf-sig-line"></div>
                    <div class="pdf-sig-name">Chapter Treasurer</div>
                    <div class="pdf-sig-title">Finance &amp; Dues Verification</div>
                </div>

                <div class="pdf-sig-box">
                    <div class="pdf-sig-role">Certified &amp; Approved By</div>
                    <div class="pdf-sig-line"></div>
                    <div class="pdf-sig-name">Ar. Heingilbert F. Demin, UAP</div>
                    <div class="pdf-sig-title">Chapter President, FY <?php echo htmlspecialchars($fy); ?></div>
                </div>
            </div>

            <!-- LEGAL & VALIDATION NOTE -->
            <div class="pdf-footer-note">
                <strong>Official Document:</strong> This document is generated from the certified database of the United Architects of the Philippines – Quezon Province Chapter.<br>
                Valid for official IAPOA chapter reporting, Area B assembly accreditation, election voter eligibility, and PRC CPD program participation.
            </div>

        </div>
    </div>

    <!-- html2pdf.js Bundle for direct client-side PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    function downloadAsPdf() {
        const element = document.getElementById('membersPdfSheet');
        const filename = '<?php echo addslashes($pdfFilename); ?>.pdf';

        if (typeof html2pdf !== 'undefined') {
            const opt = {
                margin:       [8, 8, 10, 8],
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        } else {
            window.print();
        }
    }

    // Auto-download if requested via URL
    (function() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('download') === '1') {
            setTimeout(downloadAsPdf, 500);
        } else if (params.get('print') === '1') {
            setTimeout(function() { window.print(); }, 400);
        }
    })();
    </script>
</body>
</html>

<?php
/**
 * Helper table rendering function
 */
function render_pdf_members_table(array $list, int $startIndex = 1): void {
    if (empty($list)) {
        echo '<div style="padding: 16px; text-align: center; color: #64748b; font-size: 11px; font-style: italic; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; margin-bottom: 16px;">No members recorded in this category.</div>';
        return;
    }
    ?>
    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 38px; text-align: center;">#</th>
                <th style="width: 220px;">Architect Full Name</th>
                <th style="width: 100px;">PRC ID No.</th>
                <th style="width: 150px; text-align: center;">Good Standing Status</th>
                <th style="width: 140px; text-align: center;">Activity Status</th>
                <th style="width: 120px;">Dues Settlement</th>
                <th style="width: 110px; text-align: center;">Membership</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = $startIndex;
            foreach ($list as $m): 
                $standing = $m['standing'];
                $ac = $m['attendance'];
                $isGood = $m['is_good'];
                $isRevoked = $m['is_revoked'];
                $isActive = $m['is_active'];
                $attended = $m['attended_count'];
                $remBalance = $m['remaining_balance'];
                $isPaid = $m['is_paid'];
            ?>
            <tr>
                <td style="text-align: center; font-weight: 700; color: #64748b;"><?php echo $i++; ?></td>
                <td>
                    <strong style="font-size: 12px; color: #0f172a; display: block;">
                        <?php echo htmlspecialchars($m['name']); ?>
                    </strong>
                    <?php if (!empty($m['email'])): ?>
                        <span style="font-size: 10px; color: #64748b;"><?php echo htmlspecialchars($m['email']); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <code style="font-size: 11.5px; font-weight: 700; color: #1e293b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                        <?php echo htmlspecialchars($m['id_number']); ?>
                    </code>
                </td>
                <td style="text-align: center;">
                    <?php if ($isRevoked): ?>
                        <span class="pdf-badge pdf-badge-warn">⚠️ Standing Revoked</span>
                    <?php elseif ($isGood): ?>
                        <span class="pdf-badge pdf-badge-good">⭐ Good Standing</span>
                    <?php else: ?>
                        <span class="pdf-badge pdf-badge-warn">❌ Not in Good Standing</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;">
                    <?php if ($isActive): ?>
                        <span class="pdf-badge pdf-badge-active">ACTIVE (<?php echo $attended; ?> Events)</span>
                    <?php else: ?>
                        <span class="pdf-badge pdf-badge-inactive">INACTIVE (<?php echo $attended; ?> Events)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isPaid): ?>
                        <span class="pdf-badge pdf-badge-paid">Fully Cleared</span>
                    <?php elseif ($remBalance > 0): ?>
                        <span class="pdf-badge pdf-badge-partial">Bal: ₱<?php echo number_format($remBalance, 2); ?></span>
                    <?php else: ?>
                        <span style="font-size: 10.5px; color: #64748b;">No Active Dues</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;">
                    <span style="font-size: 10.5px; font-weight: 700; color: #059669;">
                        Active Member
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
?>
