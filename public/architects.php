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

    $activeFy = function_exists('get_active_fiscal_year') ? get_active_fiscal_year() : '2026-2027';

    // Direct SQL enforcement: Exclude members with Inactive Attendance (<= 2 events)
    // unless an administrative override has explicitly granted good standing
    $sql = "SELECT wm.*, 
                   COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path,
                   u.email as user_email,
                   u.phone as user_phone,
                   u.good_standing_override,
                   COALESCE(ma.activity_status, 'inactive') as activity_status
            FROM website_members wm 
            JOIN users u ON wm.user_id = u.id 
            LEFT JOIN member_attendance ma ON ma.user_id = u.id AND ma.fiscal_year = ?
            WHERE wm.is_published = 1 
              AND wm.deleted_at IS NULL 
              AND u.deleted_at IS NULL 
              AND u.status = 'approved'
              AND (
                  COALESCE(u.good_standing_override, 'auto') = 'granted'
                  OR COALESCE(ma.activity_status, 'inactive') != 'inactive'
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

    $sql .= " ORDER BY wm.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $wm) {
        if (!empty($wm['user_id']) && function_exists('is_good_member')) {
            if (!is_good_member($pdo, (int)$wm['user_id'])) {
                continue;
            }
        }
        $members[] = [
            'id' => $wm['id'],
            'name' => $wm['name'],
            'role' => $wm['role_title'] ?: 'Architect',
            'specialty' => $wm['specialty'] ?: 'Professional Architect',
            'id_number' => $wm['id_number'] ?: '—',
            'location' => $wm['location'] ?: 'Quezon Province',
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/public_theme.css?v=2.2">
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
        .architect-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .arch-profile-card {
            background: var(--quezon-card);
            border: 1px solid var(--quezon-border);
            border-radius: 14px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s;
        }
        .arch-profile-card:hover {
            transform: translateY(-4px);
            border-color: var(--quezon-border-gold);
            box-shadow: 0 10px 24px rgba(0,0,0,0.4);
        }
        .arch-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 1rem;
        }
        .arch-avatar {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: rgba(229, 138, 19, 0.15);
            border: 2px solid var(--quezon-border-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--quezon-gold);
            overflow: hidden;
            flex-shrink: 0;
        }
        .arch-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        @media (max-width: 768px) {
            .page-hero { padding: 3.5rem 1rem 2.2rem; }
            .search-container { padding: 1.1rem 0.85rem; }
            .search-bar-wrap { flex-direction: column; gap: 8px; }
            .search-bar-wrap button { width: 100%; justify-content: center; }
            .spec-filter-chips { gap: 6px; }
            .spec-chip { font-size: 0.78rem; padding: 5px 12px; }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../includes/public_nav.php'; ?>

    <!-- HERO -->
    <section class="page-hero">
        <div class="hero-badge">
            <?php echo icon('members', '', 14); ?> Over 300 Registered Professionals • PRC Accredited
        </div>
        <h1>Arkitektong Quezonian: <span class="highlight-gold">Architects Directory</span></h1>
        <p class="lead" style="max-width: 680px; margin: 16px auto 0;">
            Browse and connect with verified, licensed architects of the UAP Quezon Province Chapter practicing across residential, commercial, interior, and civic heritage spaces.
        </p>
    </section>

    <div class="page-container" style="max-width: 1240px;">
        <!-- SEARCH & FILTER TOOLBAR -->
        <div class="search-container">
            <form method="GET" class="search-bar-wrap">
                <input
                    type="text"
                    name="q"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search by architect name, company, municipality, or PRC license number..."
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
            <div class="architect-card-grid">
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
                    ?>
                    <div class="arch-profile-card">
                        <div class="arch-header">
                            <div class="arch-avatar">
                                <?php 
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
                                <?php if (!empty($photoSrc)): ?>
                                    <img src="<?php echo htmlspecialchars($photoSrc); ?>" alt="" onerror="this.remove(); var el=document.getElementById('arch-init-<?php echo $m['id']; ?>'); if(el) el.style.display='flex';">
                                    <div id="arch-init-<?php echo $m['id']; ?>" class="arch-fallback-initials" style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 1.1rem; color: var(--quezon-gold); font-weight: 700;"><?php echo htmlspecialchars($initials); ?></div>
                                <?php else: ?>
                                    <div class="arch-fallback-initials" style="display: flex; width: 100%; height: 100%; align-items: center; justify-content: center; font-size: 1.1rem; color: var(--quezon-gold); font-weight: 700;"><?php echo htmlspecialchars($initials); ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 style="font-size: 1.1rem; color: #ffffff; font-weight: 700;"><?php echo htmlspecialchars($m['name']); ?></h3>
                                <div style="font-size: 0.8rem; color: var(--quezon-gold); font-weight: 600;"><?php echo htmlspecialchars($m['role']); ?></div>
                            </div>
                        </div>

                        <?php if ($m['company']): ?>
                            <div style="font-size: 0.85rem; color: #cbd5e1; margin-bottom: 8px; font-weight: 500;">
                                <?php echo htmlspecialchars($m['company']); ?>
                            </div>
                        <?php endif; ?>

                        <div style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 12px; flex-grow: 1;">
                            <?php echo htmlspecialchars($m['specialty']); ?>
                        </div>

                        <?php if (!empty($m['email']) || !empty($m['phone'])): ?>
                            <div style="display: flex; flex-direction: column; gap: 7px; margin-bottom: 14px; padding-top: 10px; border-top: 1px dashed rgba(255,255,255,0.08); font-size: 0.8rem;">
                                <?php if (!empty($m['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($m['email']); ?>" style="color: #cbd5e1; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="Email <?php echo htmlspecialchars($m['name']); ?>">
                                        <span style="color: var(--quezon-gold); display: inline-flex; flex-shrink: 0;"><?php echo icon('mail', '', 14); ?></span>
                                        <span style="overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($m['email']); ?></span>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($m['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $m['phone'])); ?>" style="color: #cbd5e1; display: inline-flex; align-items: center; gap: 7px; text-decoration: none;" title="Call <?php echo htmlspecialchars($m['name']); ?>">
                                        <span style="color: #34d399; display: inline-flex; flex-shrink: 0;"><?php echo icon('phone', '', 14); ?></span>
                                        <span><?php echo htmlspecialchars($m['phone']); ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--quezon-border); padding-top: 12px; font-size: 0.82rem;">
                            <span style="color: var(--quezon-gold); font-size: 0.8rem; font-weight: 600;"><?php echo htmlspecialchars($m['id_number']); ?></span>
                            <span style="color: var(--text-muted); font-size: 0.8rem; display: inline-flex; align-items: center; gap: 4px;">
                                <?php echo icon('map_pin', '', 12); ?>
                                <span><?php echo htmlspecialchars($m['location']); ?></span>
                            </span>
                        </div>

                        <div style="margin-top: 12px;">
                            <a href="<?php echo BASE_URL; ?>/public/member_profile.php?id=<?php echo $m['id']; ?>" class="btn-outline-quezon" style="width: 100%; justify-content: center; font-size: 0.82rem; padding: 7px 12px; display: inline-flex;">
                                View Verified Profile &rarr;
                            </a>
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

