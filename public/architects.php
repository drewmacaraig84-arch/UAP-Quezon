<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");


require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

$pageTitle = 'Chapter Architects Directory | UAP Quezon Province Chapter';
$activePage = 'architects';

$search = trim($_GET['q'] ?? '');
$filterSpec = trim($_GET['specialty'] ?? '');

// Load active chapter officer positions to badge directory members
$officerMap = [];
try {
    $offRows = $pdo->query("SELECT user_id, name, position FROM chapter_officers WHERE deleted_at IS NULL AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($offRows as $orow) {
        if (!empty($orow['user_id'])) {
            $officerMap['uid_' . $orow['user_id']] = $orow['position'];
        }
        $cleanedName = strtolower(trim(preg_replace('/^(Ar\.|Architect)\s+/i', '', $orow['name'])));
        $cleanedName = trim(preg_replace('/,\s*uap$/i', '', $cleanedName));
        $officerMap['name_' . $cleanedName] = $orow['position'];
    }
} catch (Throwable $e) {}

$members = [];
try {
    // Auto-synchronize and unpublish directory listings of deleted or unapproved accounts
    $pdo->exec("
        UPDATE website_members 
        SET is_published = 0, deleted_at = NOW() 
        WHERE deleted_at IS NULL 
          AND user_id IS NOT NULL 
          AND user_id NOT IN (SELECT id FROM users WHERE deleted_at IS NULL AND status = 'approved')
    ");

    // Auto-cleanup any duplicate website_members rows across the system
    $pdo->exec("
        DELETE wm1 FROM website_members wm1
        INNER JOIN website_members wm2 
        WHERE wm1.id < wm2.id 
          AND wm1.user_id = wm2.user_id 
          AND wm1.user_id IS NOT NULL
    ");

    $activeFy = function_exists('get_active_fiscal_year') ? get_active_fiscal_year() : '2026-2027';

    // Rule:
    // Tier 1: Free Active + Good Standing members are included (with badges).
    // Tier 2: Inactive or Non-Good Standing members who paid the directory fee are included (without badges).
    $sql = "SELECT wm.*, 
                   COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path,
                   u.email as user_email,
                   u.phone as user_phone,
                   u.good_standing_override,
                   COALESCE(ma.activity_status, 'inactive') as activity_status,
                   da.status as dir_app_status,
                   da.fee_amount as dir_fee_amount,
                   md.status as dir_payment_status
            FROM website_members wm 
            JOIN users u ON wm.user_id = u.id 
            LEFT JOIN member_attendance ma ON ma.user_id = u.id AND ma.fiscal_year = ?
            LEFT JOIN directory_applications da ON da.user_id = u.id
            LEFT JOIN member_dues md ON da.member_due_id = md.id
            WHERE wm.is_published = 1 
              AND wm.deleted_at IS NULL 
              AND u.deleted_at IS NULL 
              AND u.status = 'approved'
              AND (
                  COALESCE(u.good_standing_override, 'auto') = 'granted'
                  OR COALESCE(ma.activity_status, 'inactive') != 'inactive'
                  OR da.status = 'paid'
                  OR md.status = 'paid'
              )";
    $params = [$activeFy];

    if ($search !== '') {
        $sql .= " AND (wm.name LIKE ? OR wm.specialty LIKE ? OR wm.company_name LIKE ? OR wm.location LIKE ? OR wm.id_number LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $term = "%$search%";
        $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
    }

    if ($filterSpec !== '' && $filterSpec !== 'All') {
        $sql .= " AND wm.specialty LIKE ?";
        $params[] = "%$filterSpec%";
    }

    $sql .= " GROUP BY wm.user_id ORDER BY wm.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $wm) {
        $uId = (int)($wm['user_id'] ?? 0);
        $isPaidDir = ($wm['dir_app_status'] === 'paid' || ($wm['dir_payment_status'] ?? '') === 'paid');
        $isGood = function_exists('is_good_member') ? is_good_member($pdo, $uId) : false;
        $isActive = ($wm['activity_status'] ?? 'inactive') !== 'inactive';
        $hasGoodOverride = (($wm['good_standing_override'] ?? 'auto') === 'granted');

        $isCompliantActive = ($isActive && ($isGood || $hasGoodOverride));

        // Skip only if member is NEITHER compliant active NOR paid directory
        if (!$isCompliantActive && !$isPaidDir) {
            continue;
        }

        // Rule 2: Inactive/Non-Good Standing members who paid the directory fee:
        // "don't put any tag no badge"
        $showBadges = $isCompliantActive;

        $officerPos = null;
        if ($showBadges) {
            $officerPos = (function() use ($wm, $officerMap) {
                if (!empty($wm['user_id']) && isset($officerMap['uid_' . $wm['user_id']])) {
                    return $officerMap['uid_' . $wm['user_id']];
                }
                $cName = strtolower(trim(preg_replace('/^(Ar\.|Architect)\s+/i', '', $wm['name'])));
                $cName = trim(preg_replace('/,\s*uap$/i', '', $cName));
                return $officerMap['name_' . $cName] ?? null;
            })();
        }

        $members[] = [
            'id' => $wm['id'],
            'user_id' => $uId,
            'name' => $wm['name'],
            'role' => $wm['role_title'] ?: 'Architect',
            'specialty' => $wm['specialty'] ?: 'Professional Architect',
            'id_number' => $wm['id_number'] ?: '—',
            'location' => $wm['location'] ?: 'Quezon Province',
            'officer_position' => $officerPos,
            'show_badges' => $showBadges,
            'company' => $wm['company_name'] ?: '',
            'photo_path' => $wm['photo_path'] ?? '',
            'email' => $wm['user_email'] ?? '',
            'phone' => $wm['user_phone'] ?? ''
        ];
    }
} catch (Exception $e) {}

$logoSrc = BASE_URL . '/public/logo.jpg';
$specialties = ['All', 'Residential', 'Commercial', 'Heritage', 'Sustainable', 'Interior', 'Urban Planning'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/jpeg" href="<?php echo $logoSrc; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/public_theme.css?v=2.5">
    <style>
        .page-hero {
            padding: 5rem 1.5rem 3.5rem;
            text-align: center;
            background: radial-gradient(circle at 50% 20%, rgba(229, 138, 19, 0.12) 0%, rgba(21, 128, 61, 0.08) 50%, var(--quezon-dark) 80%);
            border-bottom: 1px solid var(--quezon-border);
        }
        .page-hero h1 {
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-family: var(--font-heading);
            color: #ffffff;
            margin-top: 10px;
        }
        .search-container {
            background: var(--quezon-card);
            border: 1px solid var(--quezon-border-gold);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .search-bar-wrap {
            display: flex;
            gap: 10px;
        }
        .search-bar-input {
            flex-grow: 1;
            background: var(--quezon-card-inner);
            border: 1px solid var(--quezon-border);
            color: #ffffff;
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .search-bar-input:focus {
            outline: none;
            border-color: var(--quezon-gold);
        }
        .spec-filter-chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .spec-chip {
            padding: 6px 14px;
            border-radius: 9999px;
            border: 1px solid var(--quezon-border);
            background: var(--quezon-card-inner);
            color: #cbd5e1;
            font-size: 0.82rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .spec-chip.active, .spec-chip:hover {
            border-color: var(--quezon-gold);
            background: rgba(229, 138, 19, 0.15);
            color: #ffffff;
        }
        /* Architect Directory Cards - Large Top Image / Bottom Text Format (Matching Officer Layout) */
        .architect-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.75rem;
            margin-top: 1.8rem;
        }
        .arch-profile-card {
            background: var(--quezon-card-inner, #0f172a);
            border: 1px solid var(--quezon-border, rgba(255,255,255,0.12));
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 4px 18px rgba(0,0,0,0.35);
            transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
            padding: 0;
        }
        .arch-profile-card:hover {
            transform: translateY(-5px);
            border-color: var(--quezon-border-gold, #e58a13);
            box-shadow: 0 14px 34px rgba(229,138,19,0.22);
        }
        .arch-president-card {
            border-color: rgba(229,138,19,0.55);
            background: linear-gradient(180deg, rgba(229,138,19,0.06) 0%, var(--quezon-card-inner, #0f172a) 100%);
            box-shadow: 0 8px 26px rgba(229,138,19,0.25);
        }
        /* Top Box: Image */
        .arch-img-box {
            width: 100%;
            height: 310px;
            max-height: 330px;
            background: #090e17;
            position: relative;
            overflow: hidden;
            border-bottom: 2px solid rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .arch-president-card .arch-img-box {
            border-bottom-color: var(--quezon-gold, #e58a13);
        }
        .arch-top-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
            display: block;
            transition: transform 0.35s ease;
        }
        .arch-profile-card:hover .arch-top-photo {
            transform: scale(1.04);
        }
        .arch-top-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 50% 40%, rgba(229,138,19,0.15) 0%, #090e17 80%);
            padding: 1.5rem;
            text-align: center;
        }
        .arch-fallback-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(229,138,19,0.15);
            border: 2px solid var(--quezon-gold, #e58a13);
            color: var(--quezon-gold, #e58a13);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            margin-bottom: 8px;
        }
        .arch-floating-lead-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--quezon-gold, #e58a13);
            color: #111827;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.5);
            z-index: 2;
        }
        /* Bottom Box: Text Info */
        .arch-text-box {
            padding: 1.25rem 1.15rem 1.35rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            flex: 1;
            width: 100%;
            box-sizing: border-box;
        }
        .arch-position-tag {
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(34,197,94,0.14);
            color: var(--quezon-green-light, #22c55e);
            border: 1px solid rgba(34,197,94,0.35);
            margin-bottom: 0.55rem;
            line-height: 1.25;
            display: inline-block;
        }
        .arch-position-tag.tag-president {
            background: rgba(229,138,19,0.2);
            color: var(--quezon-gold, #f59e0b);
            border-color: rgba(229,138,19,0.55);
        }
        .arch-name {
            font-size: 1.1rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 0.4rem 0;
            line-height: 1.3;
        }
        .arch-specialty-text {
            font-size: 0.84rem;
            color: var(--text-muted, #94a3b8);
            line-height: 1.4;
            margin-bottom: 0.45rem;
        }
        .arch-company-text {
            font-size: 0.82rem;
            color: #cbd5e1;
            margin-bottom: 0.6rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .arch-contact-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin: 0.4rem 0 0.85rem;
            padding-top: 0.65rem;
            border-top: 1px dashed rgba(255,255,255,0.08);
            width: 100%;
            font-size: 0.8rem;
        }
        .arch-contact-link {
            color: #cbd5e1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            transition: color 0.2s ease;
        }
        .arch-contact-link:hover {
            color: #ffffff;
        }
        .arch-meta-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            border-top: 1px solid var(--quezon-border);
            padding-top: 10px;
            font-size: 0.8rem;
            margin-top: auto;
            margin-bottom: 12px;
        }
        .arch-action-wrap {
            width: 100%;
        }
        @media (max-width: 768px) {
            .page-hero { padding: 3.5rem 1rem 2.2rem; }
            .search-container { padding: 1.1rem 0.85rem; }
            .search-bar-wrap { flex-direction: column; gap: 8px; }
            .search-bar-wrap button { width: 100%; justify-content: center; }
            .spec-filter-chips { gap: 6px; }
            .spec-chip { font-size: 0.78rem; padding: 5px 12px; }
            .architect-card-grid {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }
            .arch-img-box {
                height: 290px;
            }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../includes/public_nav.php'; ?>

    <!-- HERO -->
    <section class="page-hero">
        <div class="hero-badge">
            <?php echo icon('members', '', 14); ?> Over 300 Registered Professionals • Officially Accredited
        </div>
        <h1>Arkitektong Quezonian: <span class="highlight-gold">Architects Directory</span></h1>
        <p class="lead" style="max-width: 680px; margin: 16px auto 0;">
            Browse and connect with verified, licensed architects of the UAP Quezon Province Chapter practicing across residential, commercial, interior, and civic heritage spaces.
        </p>
    </section>

    <div class="page-container" style="max-width: 1360px;">
        <!-- SEARCH & FILTER TOOLBAR -->
        <div class="search-container reveal-pop">
            <form method="GET" class="search-bar-wrap">
                <input
                    type="text"
                    name="q"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search by architect name, company, or municipality..."
                    class="search-bar-input"
                >
                <?php if ($filterSpec): ?>
                    <input type="hidden" name="specialty" value="<?php echo htmlspecialchars($filterSpec); ?>">
                <?php endif; ?>
                <button type="submit" class="btn-primary-quezon" style="border: none; cursor: pointer; padding: 0.75rem 1.6rem; font-size: 0.88rem; font-weight: 700; border-radius: 8px;">
                    <?php echo icon('dashboard', '', 16); ?> Search
                </button>
            </form>

            <div class="spec-filter-chips">
                <span style="font-size: 0.82rem; color: var(--text-muted); align-self: center; margin-right: 4px;">Filter by:</span>
                <?php foreach ($specialties as $sp): ?>
                    <?php
                        $isActive = ($filterSpec === $sp) || ($sp === 'All' && empty($filterSpec));
                        $url = BASE_URL . '/public/architects.php?' . http_build_query([
                            'q' => $search,
                            'specialty' => $sp === 'All' ? '' : $sp
                        ]);
                    ?>
                    <a href="<?php echo htmlspecialchars($url); ?>" class="spec-chip <?php echo $isActive ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($sp); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- DIRECTORY LISTINGS -->
        <?php if (empty($members)): ?>
            <div class="section-box" style="text-align: center; padding: 4rem 1rem;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(229,138,19,0.12); color: var(--quezon-gold); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                    <?php echo icon('members', '', 32); ?>
                </div>
                <h3 style="color: #ffffff; font-size: 1.3rem; margin-bottom: 8px;">No Architects Found</h3>
                <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 20px;">
                    No profiles matched your search criteria. Try a different search term or view all chapter members.
                </p>
                <a href="<?php echo BASE_URL; ?>/public/architects.php" class="btn-outline-quezon">
                    Reset Search Filters
                </a>
            </div>
        <?php else: ?>
            <div class="architect-card-grid reveal-stagger">
                <?php foreach ($members as $m): ?>
                    <?php
                        $initials = '';
                        $parts = explode(' ', $m['name']);
                        foreach ($parts as $p) {
                            if (!empty($p) && !in_array(strtolower($p), ['ar.', 'arch.', 'uap', 'fuap'])) {
                                $initials .= strtoupper($p[0]);
                                if (strlen($initials) >= 2) break;
                            }
                        }
                        if (empty($initials)) $initials = 'AR';

                        $isPresident = (!empty($m['officer_position']) && stripos($m['officer_position'], 'president') !== false);
                        $isExecutive = (!empty($m['officer_position']) && (stripos($m['officer_position'], 'vice') !== false || stripos($m['officer_position'], 'vp') !== false));
                        $tagRole = (!empty($m['show_badges']) && !empty($m['officer_position'])) ? $m['officer_position'] : ($m['role'] ?: 'Architect');

                        $photoSrc = '';
                        if (!empty($m['photo_path'])) {
                            if (str_starts_with($m['photo_path'], 'http')) {
                                $photoSrc = $m['photo_path'];
                            } else {
                                $cleanPath = ltrim($m['photo_path'], '/');
                                $localFs = __DIR__ . '/../' . $cleanPath;
                                if (file_exists($localFs)) {
                                    $photoSrc = BASE_URL . '/' . $cleanPath;
                                }
                            }
                        }
                    ?>
                    <div class="arch-profile-card <?php echo $isPresident ? 'arch-president-card' : ''; ?>">
                        <!-- Top Box: Large Image (Officer Showcase Format) -->
                        <div class="arch-img-box">
                            <?php if (!empty($photoSrc)): ?>
                                <img src="<?php echo htmlspecialchars($photoSrc); ?>" 
                                     alt="<?php echo htmlspecialchars($m['name']); ?>" 
                                     class="arch-top-photo" 
                                     loading="lazy" 
                                     onerror="this.style.display='none'; var fb=document.getElementById('arch-fb-<?php echo $m['id']; ?>'); if(fb) fb.style.display='flex';">
                                <div id="arch-fb-<?php echo $m['id']; ?>" class="arch-top-fallback" style="display: none;">
                                    <div class="arch-fallback-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                    <span style="font-size: 0.76rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">UAP Quezon Architect</span>
                                </div>
                            <?php else: ?>
                                <div class="arch-top-fallback">
                                    <div class="arch-fallback-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                    <span style="font-size: 0.76rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">UAP Quezon Architect</span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($m['show_badges']) && !empty($m['officer_position'])): ?>
                                <div class="arch-floating-lead-badge">
                                    <?php echo icon('award', '', 12); ?>
                                    <span><?php echo $isPresident ? 'PRESIDENT' : ($isExecutive ? 'EXECUTIVE' : htmlspecialchars($m['officer_position'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Bottom Box: Text Info (Officer Showcase Format) -->
                        <div class="arch-text-box">
                            <span class="arch-position-tag <?php echo $isPresident ? 'tag-president' : ''; ?>">
                                <?php echo htmlspecialchars($tagRole); ?>
                            </span>

                            <h3 class="arch-name"><?php echo htmlspecialchars($m['name']); ?></h3>

                            <div class="arch-specialty-text"><?php echo htmlspecialchars($m['specialty']); ?></div>

                            <?php if (!empty($m['company'])): ?>
                                <div class="arch-company-text">
                                    <span style="color: var(--quezon-gold);"><?php echo icon('building', '', 12); ?></span>
                                    <span><?php echo htmlspecialchars($m['company']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($m['email']) || !empty($m['phone'])): ?>
                                <div class="arch-contact-list">
                                    <?php if (!empty($m['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($m['email']); ?>" class="arch-contact-link" title="Email <?php echo htmlspecialchars($m['name']); ?>">
                                            <span style="color: var(--quezon-gold); flex-shrink: 0;"><?php echo icon('mail', '', 13); ?></span>
                                            <span style="overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($m['email']); ?></span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($m['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $m['phone'])); ?>" class="arch-contact-link" title="Call <?php echo htmlspecialchars($m['name']); ?>">
                                            <span style="color: #34d399; flex-shrink: 0;"><?php echo icon('phone', '', 13); ?></span>
                                            <span><?php echo htmlspecialchars($m['phone']); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="arch-meta-footer">
                                <span style="color: var(--quezon-gold); font-size: 0.78rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                    <?php echo icon('landmark', '', 12); ?> UAP Chapter 121
                                </span>
                                <span style="color: var(--text-muted); font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;">
                                    <?php echo icon('map_pin', '', 12); ?>
                                    <span><?php echo htmlspecialchars($m['location']); ?></span>
                                </span>
                            </div>

                            <div class="arch-action-wrap">
                                <a href="<?php echo BASE_URL; ?>/public/member_profile.php?id=<?php echo $m['id']; ?>" class="btn-outline-quezon" style="width: 100%; justify-content: center; font-size: 0.82rem; padding: 8px 12px; display: inline-flex;">
                                    View Verified Profile &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- MEMBER JOIN CTA -->
        <div class="section-box" style="margin-top: 3rem; text-align: center; padding: 2.5rem 1.5rem; background: linear-gradient(135deg, rgba(25, 37, 66, 0.8) 0%, rgba(11, 17, 32, 0.9) 100%);">
            <h3 style="color: #ffffff; font-size: 1.3rem; margin-bottom: 8px;">Are You a Licensed Architect in Quezon Province?</h3>
            <p style="color: var(--text-muted); max-width: 540px; margin: 0 auto 20px; font-size: 0.92rem;">
                Join over 300 registered architects of UAP Quezon Province Chapter. Access CPD seminars, verify good standing, and list your architectural practice.
            </p>
            <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn-primary-quezon">
                    Register as Member &rarr;
                </a>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>

