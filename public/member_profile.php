<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

$memberId = (int)($_GET['id'] ?? 0);
$prcNumber = trim($_GET['prc'] ?? '');
$nameQuery = trim($_GET['name'] ?? '');

$member = null;

$activeFy = function_exists('get_active_fiscal_year') ? get_active_fiscal_year() : '2026-2027';

// 1. Fetch from database (enforcing attendance compliance: inactive members <= 2 events are hidden)
if ($memberId > 0) {
    $stmt = $pdo->prepare("SELECT wm.*, 
                                  COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path,
                                  u.email as user_email,
                                  u.phone as user_phone,
                                  u.address as user_address,
                                  u.good_standing_override,
                                  COALESCE(ma.activity_status, 'inactive') as activity_status,
                                  da.status as dir_app_status,
                                  md.status as dir_payment_status
                           FROM website_members wm 
                           JOIN users u ON wm.user_id = u.id 
                           LEFT JOIN member_attendance ma ON ma.user_id = u.id AND ma.fiscal_year = ?
                           LEFT JOIN directory_applications da ON da.user_id = u.id
                           LEFT JOIN member_dues md ON da.member_due_id = md.id
                           WHERE (wm.id = ? OR wm.user_id = ?) 
                             AND wm.is_published = 1 
                             AND wm.deleted_at IS NULL
                             AND u.deleted_at IS NULL
                             AND u.status = 'approved'
                             AND (
                                 COALESCE(u.good_standing_override, 'auto') = 'granted'
                                 OR COALESCE(ma.activity_status, 'inactive') != 'inactive'
                                 OR da.status = 'paid'
                                 OR md.status = 'paid'
                             )");
    $stmt->execute([$activeFy, $memberId, $memberId]);
    $member = $stmt->fetch();
} elseif ($prcNumber !== '') {
    $stmt = $pdo->prepare("SELECT wm.*, 
                                  COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path,
                                  u.email as user_email,
                                  u.phone as user_phone,
                                  u.address as user_address,
                                  u.good_standing_override,
                                  COALESCE(ma.activity_status, 'inactive') as activity_status
                           FROM website_members wm 
                           JOIN users u ON wm.user_id = u.id 
                           LEFT JOIN member_attendance ma ON ma.user_id = u.id AND ma.fiscal_year = ?
                           WHERE (wm.prc_number = ? OR wm.id_number = ?) 
                             AND wm.is_published = 1 
                             AND wm.deleted_at IS NULL
                             AND u.deleted_at IS NULL
                             AND u.status = 'approved'
                             AND (
                                 COALESCE(u.good_standing_override, 'auto') = 'granted'
                                 OR COALESCE(ma.activity_status, 'inactive') != 'inactive'
                             )");
    $stmt->execute([$activeFy, $prcNumber, $prcNumber]);
    $member = $stmt->fetch();
} elseif ($nameQuery !== '') {
    $stmt = $pdo->prepare("SELECT wm.*, 
                                  COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path,
                                  u.email as user_email,
                                  u.phone as user_phone,
                                  u.address as user_address,
                                  u.good_standing_override,
                                  COALESCE(ma.activity_status, 'inactive') as activity_status
                           FROM website_members wm 
                           JOIN users u ON wm.user_id = u.id 
                           LEFT JOIN member_attendance ma ON ma.user_id = u.id AND ma.fiscal_year = ?
                           WHERE wm.name = ? 
                             AND wm.is_published = 1 
                             AND wm.deleted_at IS NULL
                             AND u.deleted_at IS NULL
                             AND u.status = 'approved'
                             AND (
                                 COALESCE(u.good_standing_override, 'auto') = 'granted'
                                 OR COALESCE(ma.activity_status, 'inactive') != 'inactive'
                             )");
    $stmt->execute([$activeFy, $nameQuery]);
    $member = $stmt->fetch();
}

// 2. Check if member is in good standing
if ($member && !empty($member['user_id'])) {
    if (function_exists('is_good_member') && !is_good_member($pdo, (int)$member['user_id'])) {
        $member = null; // Do not show if standing is revoked
    }
}

// Decode multiple project gallery photos & structured Completed Works
$gallery = [];
if ($member) {
    if (!empty($member['gallery_json'])) {
        $decoded = json_decode($member['gallery_json'], true);
        if (is_array($decoded)) {
            $gallery = $decoded;
        }
    } elseif (!empty($member['photo_path'])) {
        $gallery[] = [
            'path' => $member['photo_path'],
            'description' => $member['photo_description'] ?? ''
        ];
    }
}

$projects = [];
if ($member) {
    if (!empty($member['projects_json'])) {
        $decodedProjects = json_decode($member['projects_json'], true);
        if (is_array($decodedProjects)) {
            $projects = $decodedProjects;
        }
    }

    // Backward compatibility fallback: If projects_json is empty, adapt gallery photos into completed works cards
    if (empty($projects) && !empty($gallery)) {
        foreach ($gallery as $gIdx => $gItem) {
            $gPath = $gItem['path'] ?? '';
            if (empty($gPath)) continue;
            $gTitle = !empty(trim($gItem['description'] ?? '')) ? trim($gItem['description']) : 'Completed Architectural Work ' . ($gIdx + 1);
            $projects[] = [
                'id' => 'legacy_' . ($gIdx + 1),
                'title' => $gTitle,
                'category' => 'RESIDENTIAL',
                'location' => !empty($member['location']) ? $member['location'] : 'Quezon Province',
                'description' => !empty(trim($gItem['description'] ?? '')) ? trim($gItem['description']) : 'Architectural design, space planning, and construction documentation executed with high standards of design excellence.',
                'project_team' => $member['name'] ?? '',
                'cover_photo' => $gPath,
                'photos' => [ $gPath ]
            ];
        }
    }
}

// Client-side structured projects for the interactive modal
$clientProjects = [];
if (!empty($projects)) {
    foreach ($projects as $proj) {
        $rawPhotos = [];
        if (!empty($proj['cover_photo'])) {
            $rawPhotos[] = (str_starts_with($proj['cover_photo'], 'http://') || str_starts_with($proj['cover_photo'], 'https://')) 
                ? $proj['cover_photo'] 
                : (BASE_URL . '/' . ltrim($proj['cover_photo'], '/'));
        }
        if (!empty($proj['photos']) && is_array($proj['photos'])) {
            foreach ($proj['photos'] as $ph) {
                if (!empty($ph)) {
                    $u = (str_starts_with($ph, 'http://') || str_starts_with($ph, 'https://')) 
                        ? $ph 
                        : (BASE_URL . '/' . ltrim($ph, '/'));
                    if (!in_array($u, $rawPhotos, true)) {
                        $rawPhotos[] = $u;
                    }
                }
            }
        }
        if (empty($rawPhotos)) {
            $rawPhotos[] = BASE_URL . '/public/logo.jpg';
        }
        
        $teamList = [];
        if (!empty($proj['project_team'])) {
            $lines = preg_split('/\r?\n/', $proj['project_team']);
            foreach ($lines as $ln) {
                $t = trim($ln);
                if ($t !== '') $teamList[] = $t;
            }
        }
        if (empty($teamList) && !empty($member['name'])) {
            $teamList[] = $member['name'];
        }

        $clientProjects[] = [
            'id' => $proj['id'] ?? '',
            'title' => $proj['title'] ?? 'Completed Architectural Work',
            'category' => !empty($proj['category']) ? strtoupper($proj['category']) : 'RESIDENTIAL',
            'location' => $proj['location'] ?? '',
            'description' => $proj['description'] ?? '',
            'photos' => $rawPhotos,
            'team' => $teamList
        ];
    }
}

$pageTitle = $member ? htmlspecialchars($member['name']) . ' • Verified Architect Profile | UAP Quezon' : 'Member Profile Not Found • UAP Quezon Province Chapter';
$activePage = 'architects';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/public_theme.css?v=2.4">
    <link rel="icon" type="image/jpeg" href="<?php echo BASE_URL; ?>/public/logo.jpg">
    <style>
        .profile-container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 4rem;
        }
        .profile-hero {
            display: flex;
            align-items: center;
            gap: 2rem;
            padding: 2.2rem;
            background: linear-gradient(135deg, rgba(19, 29, 51, 0.95) 0%, rgba(25, 37, 66, 0.85) 100%);
            border: 1px solid var(--quezon-border);
            border-top: 3px solid var(--quezon-gold);
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.35);
            margin-bottom: 2rem;
            position: relative;
        }
        .profile-avatar-box {
            position: relative;
            width: 130px;
            height: 130px;
            flex-shrink: 0;
            border-radius: 16px;
            overflow: hidden;
            border: 3px solid var(--quezon-gold);
            background: #090d19;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }
        .profile-avatar-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-avatar-initials {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--quezon-gold);
            letter-spacing: 1px;
        }
        .verified-badge-floating {
            position: absolute;
            bottom: -6px;
            right: -6px;
            background: #10b981;
            color: #ffffff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 3px solid #131d33;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.4);
        }
        .profile-details {
            flex: 1;
        }
        .profile-name {
            font-size: 1.85rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }
        .profile-role {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--quezon-gold);
            margin-bottom: 12px;
        }
        .profile-badges-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .badge-verified-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .badge-gold-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            background: rgba(245, 158, 11, 0.15);
            color: var(--quezon-gold);
            border: 1px solid var(--quezon-border-gold);
        }
        .credentials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.2rem;
            margin-top: 1rem;
        }
        .cred-item {
            background: var(--quezon-card-inner);
            border: 1px solid var(--quezon-border);
            border-radius: 12px;
            padding: 1.1rem 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .cred-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            font-weight: 600;
        }
        .cred-value {
            font-size: 0.98rem;
            color: #ffffff;
            font-weight: 700;
            line-height: 1.35;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.2rem;
        }
        .gallery-card {
            background: var(--quezon-card-inner);
            border: 1px solid var(--quezon-border);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 6px 18px rgba(0,0,0,0.3);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .gallery-card:hover {
            transform: translateY(-4px);
            border-color: var(--quezon-gold);
        }
        .gallery-card-img-wrap {
            width: 100%;
            height: 220px;
            overflow: hidden;
            background: #090d19;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gallery-card-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .gallery-card:hover .gallery-card-img-wrap img {
            transform: scale(1.05);
        }
        .gallery-card-desc {
            padding: 1rem 1.2rem;
            font-size: 0.88rem;
            color: #cbd5e1;
            line-height: 1.5;
            border-top: 1px solid var(--quezon-border);
        }
        .section-header-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid var(--quezon-border);
            padding-bottom: 12px;
        }
        .section-header-row h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .text-narrative {
            color: #cbd5e1;
            font-size: 0.95rem;
            line-height: 1.7;
            padding: 0.5rem 0;
        }
        @media (max-width: 768px) {
            .profile-hero {
                flex-direction: column;
                text-align: center;
                gap: 1.2rem;
                padding: 1.8rem 1.2rem;
            }
            .profile-badges-row {
                justify-content: center;
            }
            .profile-name {
                font-size: 1.5rem;
            }
            .credentials-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../includes/public_nav.php'; ?>

    <div class="profile-container">

        <!-- BREADCRUMB / BACK ACTION -->
        <div style="margin-bottom: 1.5rem;">
            <a href="<?php echo BASE_URL; ?>/public/architects.php" class="btn-outline-quezon" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; font-size: 0.85rem; border-radius: 8px;">
                <?php echo icon('arrow_left', '', 14); ?>
                <span>Back to Architects Directory</span>
            </a>
        </div>

        <?php if (!$member): ?>
            <!-- NOT FOUND STATE -->
            <div class="section-box" style="text-align: center; padding: 4rem 1.5rem;">
                <div style="width: 64px; height: 64px; border-radius: 16px; background: rgba(229,138,19,0.12); color: var(--quezon-gold); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 18px;">
                    <?php echo icon('landmark', '', 32); ?>
                </div>
                <h2 style="color: #ffffff; font-size: 1.4rem; margin-bottom: 10px;">Member Profile Not Found or Restricted</h2>
                <p style="color: var(--text-muted); max-width: 520px; margin: 0 auto 24px; font-size: 0.95rem; line-height: 1.6;">
                    The requested architect profile is either not published or is currently under verification by Chapter Administration.
                </p>
                <a href="<?php echo BASE_URL; ?>/public/architects.php" class="btn-primary-quezon" style="display: inline-flex; padding: 0.7rem 1.4rem; font-size: 0.85rem;">
                    <span>Return to Chapter Directory</span> &rarr;
                </a>
            </div>
        <?php else: ?>
            <?php
                // Initials
                $initials = '';
                $parts = explode(' ', preg_replace('/^(Ar\.|Arch\.|Architect)\s+/i', '', $member['name']));
                foreach ($parts as $p) {
                    if (!empty($p)) $initials .= strtoupper($p[0]);
                }
                $initials = substr($initials, 0, 2) ?: 'AR';

                $cleanSpecialty = (!empty($member['specialty']) && strcasecmp(trim($member['specialty']), 'none') !== 0) ? $member['specialty'] : 'General Practice';
                $cleanLocation = (!empty($member['location']) && strcasecmp(trim($member['location']), 'none') !== 0) ? $member['location'] : 'Quezon Province, Philippines';
                $cleanAchievements = (!empty($member['achievements']) && strcasecmp(trim($member['achievements']), 'none') !== 0) ? $member['achievements'] : 'Registered Architect actively practicing in Quezon Province under UAP Chapter 121.';
                $cleanAwards = (!empty($member['awards']) && strcasecmp(trim($member['awards']), 'none') !== 0) ? $member['awards'] : 'Active professional member in certified good standing under UAP Quezon Province Chapter.';
                $prcNumber = !empty($member['prc_number']) ? $member['prc_number'] : '—';
                $chapterId = !empty($member['id_number']) ? $member['id_number'] : '—';
            ?>

            <!-- HERO PROFILE CARD -->
            <div class="profile-hero">
                <div class="profile-avatar-box">
                    <?php 
                        $profilePhotoSrc = '';
                        if (!empty($member['photo_path'])) {
                            if (str_starts_with($member['photo_path'], 'http')) {
                                $profilePhotoSrc = $member['photo_path'];
                            } else {
                                $cleanPhoto = ltrim($member['photo_path'], '/');
                                $localFs = __DIR__ . '/../' . $cleanPhoto;
                                if (file_exists($localFs)) {
                                    $profilePhotoSrc = BASE_URL . '/' . $cleanPhoto;
                                }
                            }
                        }
                    ?>
                    <?php if (!empty($profilePhotoSrc)): ?>
                        <img src="<?php echo htmlspecialchars($profilePhotoSrc); ?>" alt="" onerror="this.remove(); var el=document.getElementById('prof-init-fallback'); if(el) el.style.display='flex';">
                        <div id="prof-init-fallback" class="profile-avatar-initials" style="display: none;"><?php echo htmlspecialchars($initials); ?></div>
                    <?php else: ?>
                        <div class="profile-avatar-initials"><?php echo htmlspecialchars($initials); ?></div>
                    <?php endif; ?>
                    <div class="verified-badge-floating" title="Verified Chapter Registered Architect">
                        <?php echo icon('check', '', 16); ?>
                    </div>
                </div>

                <div class="profile-details">
                    <h1 class="profile-name"><?php echo htmlspecialchars($member['name']); ?></h1>
                    <div class="profile-role"><?php echo htmlspecialchars($member['role_title'] ?: 'Architect'); ?></div>

                    <div class="profile-badges-row">
                        <?php
                            $standingDetails = function_exists('get_member_standing_details') ? get_member_standing_details($pdo, (int)$member['user_id']) : ['is_good' => true, 'label' => 'Good Standing'];
                            $isActiveMemberProf = ($member['activity_status'] ?? 'inactive') !== 'inactive';
                            $isGoodProf = !empty($standingDetails['is_good']) || (($member['good_standing_override'] ?? 'auto') === 'granted');
                            $isCompliantActiveProf = ($isActiveMemberProf && $isGoodProf);
                        ?>
                        <?php if ($isCompliantActiveProf): ?>
                            <span class="badge-verified-pill" style="background: rgba(16,185,129,0.15); border-color: #10b981; color: #10b981;">
                                <?php echo icon('shield_check', '', 14); ?>
                                <span>Good Standing (Verified)</span>
                            </span>
                        <?php endif; ?>
                        <span class="badge-gold-pill">
                            <?php echo icon('landmark', '', 14); ?>
                            <span>UAP Chapter 121</span>
                        </span>
                        <span class="badge-gold-pill">
                            <?php echo icon('map_pin', '', 14); ?>
                            <span><?php echo htmlspecialchars($cleanLocation); ?></span>
                        </span>
                    </div>

                    <?php if (!empty($member['user_email']) || !empty($member['user_phone'])): ?>
                    <div style="margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php if (!empty($member['user_email'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars($member['user_email']); ?>" class="btn-primary-quezon" style="font-size: 0.82rem; padding: 8px 16px; text-decoration: none; display: inline-flex; align-items: center; gap: 7px; border-radius: 8px;">
                                <?php echo icon('mail', '', 15); ?>
                                <span>Email Architect</span>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($member['user_phone'])): ?>
                            <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $member['user_phone'])); ?>" class="btn-outline-quezon" style="font-size: 0.82rem; padding: 8px 16px; text-decoration: none; display: inline-flex; align-items: center; gap: 7px;">
                                <?php echo icon('phone', '', 15); ?>
                                <span>Call <?php echo htmlspecialchars($member['user_phone']); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 1. PROFESSIONAL CREDENTIALS -->
            <div class="section-box">
                <div class="section-header-row">
                    <h2>
                        <span style="color: var(--quezon-gold);"><?php echo icon('landmark', '', 20); ?></span>
                        <span>Professional Credentials &amp; Practice Information</span>
                    </h2>
                </div>
                <div class="credentials-grid">
                    <div class="cred-item">
                        <span class="cred-label">Chapter Affiliation</span>
                        <span class="cred-value">UAP Quezon Province Chapter (121)</span>
                    </div>

                    <div class="cred-item">
                        <span class="cred-label">Architectural Specialization</span>
                        <span class="cred-value"><?php echo htmlspecialchars($cleanSpecialty); ?></span>
                    </div>

                    <?php if (!empty($member['user_email'])): ?>
                    <div class="cred-item">
                        <span class="cred-label">Official Email Address</span>
                        <span class="cred-value">
                            <a href="mailto:<?php echo htmlspecialchars($member['user_email']); ?>" style="color: var(--quezon-gold); display: inline-flex; align-items: center; gap: 6px; text-decoration: underline; text-underline-offset: 3px;">
                                <?php echo icon('mail', '', 14); ?>
                                <span><?php echo htmlspecialchars($member['user_email']); ?></span>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($member['user_phone'])): ?>
                    <div class="cred-item">
                        <span class="cred-label">Contact / Mobile Number</span>
                        <span class="cred-value">
                            <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $member['user_phone'])); ?>" style="color: #34d399; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                                <?php echo icon('phone', '', 14); ?>
                                <span><?php echo htmlspecialchars($member['user_phone']); ?></span>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($member['user_address'])): ?>
                    <div class="cred-item" style="grid-column: 1 / -1;">
                        <span class="cred-label">Registered Office / Practice Address</span>
                        <span class="cred-value" style="display: flex; align-items: center; gap: 6px; color: #cbd5e1;">
                            <span style="color: var(--quezon-gold); flex-shrink: 0;"><?php echo icon('map_pin', '', 14); ?></span>
                            <span><?php echo htmlspecialchars($member['user_address']); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($member['company_name'])): ?>
                    <div class="cred-item">
                        <span class="cred-label">Architectural Firm / Practice</span>
                        <span class="cred-value" style="display: flex; align-items: center; gap: 6px;">
                            <span style="color: var(--quezon-gold);"><?php echo icon('briefcase', '', 14); ?></span>
                            <span><?php echo htmlspecialchars($member['company_name']); ?></span>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($member['link_url'])): 
                        $linkPlatform = function_exists('detect_social_link_type') ? detect_social_link_type($member['link_url'], $member['link_type'] ?? 'auto') : 'website';
                    ?>
                    <div class="cred-item">
                        <span class="cred-label">Official Link (<?php echo ucfirst($linkPlatform); ?>)</span>
                        <span class="cred-value">
                            <a href="<?php echo htmlspecialchars($member['link_url']); ?>" target="_blank" rel="noopener noreferrer" style="color: var(--quezon-gold); display: inline-flex; align-items: center; gap: 6px; text-decoration: underline; text-underline-offset: 3px;">
                                <?php echo icon($linkPlatform === 'website' ? 'globe' : $linkPlatform, '', 14); ?>
                                <span><?php echo htmlspecialchars($member['link_url']); ?> &rarr;</span>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="cred-item">
                        <span class="cred-label">Base Practice Municipality</span>
                        <span class="cred-value"><?php echo htmlspecialchars($cleanLocation); ?></span>
                    </div>

                    <div class="cred-item">
                        <span class="cred-label">Public Directory Status</span>
                        <span class="cred-value">
                            <?php if (!empty($isCompliantActiveProf)): ?>
                                <span style="color: #10b981; font-weight: 700;">Active Verified Chapter Member</span>
                            <?php else: ?>
                                <span style="color: var(--quezon-gold); font-weight: 700;">Verified Chapter Directory Member</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- 2. PORTFOLIO & ARCHITECTURAL PRACTICE / COMPLETED WORKS -->
            <?php if (!empty($projects)): ?>
            <div class="profile-completed-works-section reveal-pop">
                <div class="completed-works-header">
                    <p class="completed-works-eyebrow">PORTFOLIO &amp; ARCHITECTURAL PRACTICE</p>
                    <h2 class="completed-works-title">
                        COMPLETED <span style="color: var(--quezon-gold);">WORKS</span>
                    </h2>
                </div>
                <div class="completed-works-grid reveal-stagger">
                    <?php foreach ($projects as $pIdx => $proj): ?>
                        <?php
                            $coverUrl = !empty($proj['cover_photo']) ? $proj['cover_photo'] : (!empty($proj['photos'][0]) ? $proj['photos'][0] : '');
                            $resolvedCover = '';
                            if (!empty($coverUrl)) {
                                $resolvedCover = (str_starts_with($coverUrl, 'http://') || str_starts_with($coverUrl, 'https://')) ? $coverUrl : (BASE_URL . '/' . ltrim($coverUrl, '/'));
                            } else {
                                $resolvedCover = BASE_URL . '/public/logo.jpg';
                            }
                        ?>
                        <div class="completed-work-card" 
                             onclick="openProjectModal(<?php echo $pIdx; ?>)" 
                             role="button" 
                             tabindex="0"
                             aria-label="View details for <?php echo htmlspecialchars($proj['title'] ?? 'Architectural Project'); ?>">
                            <div class="completed-work-photo-wrap">
                                <img src="<?php echo htmlspecialchars($resolvedCover); ?>"
                                     alt="<?php echo htmlspecialchars($proj['title'] ?? 'Architectural Project'); ?>"
                                     loading="lazy"
                                     onerror="this.onerror=null;this.src='<?php echo BASE_URL; ?>/public/logo.jpg';">
                                <div class="completed-work-hover-overlay">
                                    <div class="completed-work-hover-content">
                                        <?php if (!empty($proj['category'])): ?>
                                            <span class="completed-work-hover-cat"><?php echo htmlspecialchars($proj['category']); ?></span>
                                        <?php endif; ?>
                                        <h3 class="completed-work-hover-title"><?php echo htmlspecialchars($proj['title'] ?? 'Completed Architectural Work'); ?></h3>
                                        <?php if (!empty($proj['location'])): ?>
                                            <p class="completed-work-hover-loc">
                                                <?php echo icon('map_pin', '', 12); ?>
                                                <span><?php echo htmlspecialchars($proj['location']); ?></span>
                                            </p>
                                        <?php endif; ?>
                                        <div class="completed-work-hover-action">
                                            <span>View Full Project Details</span>
                                            <?php echo icon('arrow_right', '', 14); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 3. CAREER ACHIEVEMENTS & PRACTICE -->
            <div class="section-box">
                <div class="section-header-row">
                    <h2>
                        <span style="color: var(--quezon-gold);"><?php echo icon('trophy', '', 20); ?></span>
                        <span>Career Achievements &amp; Architectural Practice</span>
                    </h2>
                </div>
                <div class="text-narrative">
                    <?php echo nl2br(htmlspecialchars($cleanAchievements)); ?>
                </div>
            </div>

            <!-- 4. HONORS, DISTINCTIONS & AWARDS -->
            <div class="section-box">
                <div class="section-header-row">
                    <h2>
                        <span style="color: var(--quezon-gold);"><?php echo icon('award', '', 20); ?></span>
                        <span>Honors, Distinctions &amp; Chapter Recognition</span>
                    </h2>
                </div>
                <div class="text-narrative">
                    <?php echo nl2br(htmlspecialchars($cleanAwards)); ?>
                </div>
            </div>

            <!-- 5. CHAPTER VERIFICATION & SEAL -->
            <div class="section-box" style="text-align: center; padding: 2.5rem 1.5rem;">
                <div style="width: 54px; height: 54px; border-radius: 50%; background: rgba(16, 185, 129, 0.12); color: #10b981; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px;">
                    <?php echo icon('shield_check', '', 28); ?>
                </div>
                <h3 style="color: #ffffff; font-size: 1.25rem; font-weight: 700; margin-bottom: 6px;">Certified Chapter Member Registration</h3>
                <p style="color: var(--text-muted); max-width: 580px; margin: 0 auto 18px; font-size: 0.92rem; line-height: 1.6;">
                    This professional profile is certified and publicly verified under the United Architects of the Philippines &ndash; Quezon Province Chapter (IAPOA Chapter 121).
                </p>

                <?php 
                    $qrCandidate = !empty($member['qr_image_path']) ? $member['qr_image_path'] : (!empty($member['qr_code_path']) ? $member['qr_code_path'] : '');
                    $qrValid = false;
                    $qrSrc = '';
                    if (!empty($qrCandidate)) {
                        if (str_starts_with($qrCandidate, 'http')) {
                            $qrValid = true;
                            $qrSrc = $qrCandidate;
                        } else {
                            $cleanQr = ltrim($qrCandidate, '/');
                            $localQrFs = __DIR__ . '/../' . $cleanQr;
                            if (file_exists($localQrFs)) {
                                $qrValid = true;
                                $qrSrc = BASE_URL . '/' . $cleanQr;
                            }
                        }
                    }
                ?>
                <?php if ($qrValid): ?>
                    <div style="margin: 0 auto 10px; width: 140px; height: 140px; padding: 8px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
                        <img src="<?php echo htmlspecialchars($qrSrc); ?>" alt="Architect Verification QR" style="width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Scan to verify official chapter credentials</span>
                <?php else: ?>
                    <div style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 9999px; background: rgba(245, 158, 11, 0.1); border: 1px solid var(--quezon-border-gold); color: var(--quezon-gold); font-size: 0.85rem; font-weight: 700;">
                        <?php echo icon('landmark', '', 14); ?>
                        <span>Verified IAPOA Chapter 121 Member Record</span>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>

    <?php if (!empty($clientProjects)): ?>
    <!-- INTERACTIVE PROJECT DETAILS MODAL & SLIDESHOW LIGHTBOX -->
    <div id="projectModal" class="project-modal" aria-hidden="true" role="dialog" aria-modal="true" onclick="if(event.target === this) closeProjectModal()">
        <div class="project-modal-dialog">
            <button type="button" class="project-modal-close" onclick="closeProjectModal()" aria-label="Close Project Modal">
                <?php echo icon('x', '', 20); ?>
            </button>
            
            <div class="project-modal-topbar">
                <div class="project-modal-back-btn" onclick="closeProjectModal()">
                    <?php echo icon('arrow_left', '', 16); ?>
                    <span>Back to <?php echo htmlspecialchars($member['name']); ?>'s Profile</span>
                </div>
                <div class="project-modal-verified-pill">
                    <?php echo icon('shield_check', '', 14); ?>
                    <span>Certified Chapter Architect</span>
                </div>
            </div>

            <!-- HERO SLIDESHOW STAGE -->
            <div class="modal-slideshow-container" onmouseenter="pauseSlideshow()" onmouseleave="resumeSlideshow()">
                <div class="modal-slide-stage" id="modalSlideStage">
                    <!-- Slides injected dynamically -->
                </div>
                
                <button type="button" class="modal-slider-btn modal-slider-prev" onclick="prevModalSlide()" aria-label="Previous Slide">
                    <?php echo icon('arrow_left', '', 22); ?>
                </button>
                <button type="button" class="modal-slider-btn modal-slider-next" onclick="nextModalSlide()" aria-label="Next Slide">
                    <?php echo icon('arrow_right', '', 22); ?>
                </button>

                <div class="modal-slider-meta">
                    <div class="modal-slider-dots" id="modalSliderDots"></div>
                    <span class="modal-slider-counter" id="modalSliderCounter">1 / 1</span>
                </div>
            </div>

            <!-- THUMBNAIL PREVIEW STRIP -->
            <div class="modal-thumbs-strip" id="modalThumbsStrip"></div>

            <!-- EDITORIAL SPLIT DETAILS -->
            <div class="modal-editorial-card">
                <div class="modal-editorial-grid">
                    <!-- LEFT COLUMN: Meta & Architecture details -->
                    <div class="modal-meta-col">
                        <span class="modal-cat-badge" id="modalCatBadge">RESIDENTIAL</span>
                        <h2 class="modal-project-title" id="modalProjectTitle">Project Title</h2>
                        <p class="modal-project-loc" id="modalProjectLoc">
                            <?php echo icon('map_pin', '', 14); ?>
                            <span id="modalProjectLocText">QUEZON PROVINCE</span>
                        </p>
                        <div class="modal-firm-info">
                            <?php if (!empty($member['company_name'])): ?>
                                <p class="modal-firm-name">
                                    <?php echo icon('building', '', 14); ?>
                                    <span><?php echo htmlspecialchars($member['company_name']); ?></span>
                                </p>
                            <?php endif; ?>
                            <p class="modal-lead-architect">
                                Principal Architect: <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                            </p>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Narrative & Project Team -->
                    <div class="modal-narrative-col">
                        <div class="modal-narrative-content">
                            <h3 class="modal-narrative-heading">Architectural Concept &amp; Narrative</h3>
                            <p class="modal-narrative-text" id="modalNarrativeText"></p>
                        </div>
                        <div class="modal-team-block" id="modalTeamBlock">
                            <h3 class="modal-team-heading">Project Team &amp; Collaborators:</h3>
                            <ul class="modal-team-list" id="modalTeamList"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    window.chapterProjects = <?php echo json_encode($clientProjects, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    window.currentModalProjectIdx = 0;
    window.currentModalSlideIdx = 0;
    window.modalSlideTimer = null;
    window.isModalPaused = false;

    function openProjectModal(projectIdx) {
        var proj = window.chapterProjects[projectIdx];
        if (!proj) return;
        window.currentModalProjectIdx = projectIdx;
        window.currentModalSlideIdx = 0;
        window.isModalPaused = false;

        document.getElementById('modalCatBadge').textContent = proj.category || 'RESIDENTIAL';
        document.getElementById('modalProjectTitle').textContent = proj.title || 'Completed Architectural Work';
        
        var locText = document.getElementById('modalProjectLocText');
        var locContainer = document.getElementById('modalProjectLoc');
        if (proj.location && proj.location.trim() !== '') {
            locText.textContent = proj.location.toUpperCase();
            locContainer.style.display = 'flex';
        } else {
            locContainer.style.display = 'none';
        }

        var narrText = document.getElementById('modalNarrativeText');
        narrText.textContent = proj.description || 'Architectural design, space planning, and construction documentation executed with high standards of design excellence.';

        var teamBlock = document.getElementById('modalTeamBlock');
        var teamList = document.getElementById('modalTeamList');
        teamList.innerHTML = '';
        if (proj.team && proj.team.length > 0) {
            proj.team.forEach(function(member) {
                var li = document.createElement('li');
                li.className = 'modal-team-member';
                li.textContent = member;
                teamList.appendChild(li);
            });
            teamBlock.style.display = 'block';
        } else {
            teamBlock.style.display = 'none';
        }

        var stage = document.getElementById('modalSlideStage');
        var thumbs = document.getElementById('modalThumbsStrip');
        var dots = document.getElementById('modalSliderDots');
        stage.innerHTML = '';
        thumbs.innerHTML = '';
        dots.innerHTML = '';

        var photos = proj.photos && proj.photos.length > 0 ? proj.photos : ['<?php echo BASE_URL; ?>/public/logo.jpg'];
        var totalPhotos = photos.length;

        photos.forEach(function(url, idx) {
            var slide = document.createElement('div');
            slide.className = 'modal-slide-item' + (idx === 0 ? ' active' : '');
            slide.id = 'modalSlide_' + idx;
            var img = document.createElement('img');
            img.src = url;
            img.alt = (proj.title || 'Project') + ' - Photo ' + (idx + 1);
            img.onerror = function() { this.src = '<?php echo BASE_URL; ?>/public/logo.jpg'; };
            slide.appendChild(img);
            stage.appendChild(slide);

            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'modal-dot' + (idx === 0 ? ' active' : '');
            dot.id = 'modalDot_' + idx;
            dot.setAttribute('aria-label', 'Go to photo ' + (idx + 1));
            dot.onclick = function() { setModalSlide(idx); };
            dots.appendChild(dot);

            if (totalPhotos > 1) {
                var thumbBtn = document.createElement('button');
                thumbBtn.type = 'button';
                thumbBtn.className = 'modal-thumb-btn' + (idx === 0 ? ' active' : '');
                thumbBtn.id = 'modalThumb_' + idx;
                thumbBtn.setAttribute('aria-label', 'Thumbnail ' + (idx + 1));
                var tImg = document.createElement('img');
                tImg.src = url;
                tImg.alt = 'Thumbnail ' + (idx + 1);
                tImg.onerror = function() { this.src = '<?php echo BASE_URL; ?>/public/logo.jpg'; };
                thumbBtn.appendChild(tImg);
                thumbBtn.onclick = function() { setModalSlide(idx); };
                thumbs.appendChild(thumbBtn);
            }
        });

        var prevBtn = document.querySelector('.modal-slider-prev');
        var nextBtn = document.querySelector('.modal-slider-next');
        if (totalPhotos <= 1) {
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
            thumbs.style.display = 'none';
            dots.style.display = 'none';
        } else {
            if (prevBtn) prevBtn.style.display = 'flex';
            if (nextBtn) nextBtn.style.display = 'flex';
            thumbs.style.display = 'flex';
            dots.style.display = 'flex';
        }

        updateSlideCounter();

        var modal = document.getElementById('projectModal');
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        restartSlideTimer();
    }

    function setModalSlide(idx) {
        var proj = window.chapterProjects[window.currentModalProjectIdx];
        if (!proj) return;
        var total = proj.photos.length;
        if (total <= 1) return;

        var oldSlide = document.getElementById('modalSlide_' + window.currentModalSlideIdx);
        var oldDot = document.getElementById('modalDot_' + window.currentModalSlideIdx);
        var oldThumb = document.getElementById('modalThumb_' + window.currentModalSlideIdx);
        if (oldSlide) oldSlide.classList.remove('active');
        if (oldDot) oldDot.classList.remove('active');
        if (oldThumb) oldThumb.classList.remove('active');

        window.currentModalSlideIdx = idx;

        var newSlide = document.getElementById('modalSlide_' + idx);
        var newDot = document.getElementById('modalDot_' + idx);
        var newThumb = document.getElementById('modalThumb_' + idx);
        if (newSlide) newSlide.classList.add('active');
        if (newDot) newDot.classList.add('active');
        if (newThumb) {
            newThumb.classList.add('active');
            newThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }

        updateSlideCounter();
        restartSlideTimer();
    }

    function nextModalSlide() {
        var proj = window.chapterProjects[window.currentModalProjectIdx];
        if (!proj || !proj.photos) return;
        var next = (window.currentModalSlideIdx + 1) % proj.photos.length;
        setModalSlide(next);
    }

    function prevModalSlide() {
        var proj = window.chapterProjects[window.currentModalProjectIdx];
        if (!proj || !proj.photos) return;
        var prev = (window.currentModalSlideIdx - 1 + proj.photos.length) % proj.photos.length;
        setModalSlide(prev);
    }

    function updateSlideCounter() {
        var proj = window.chapterProjects[window.currentModalProjectIdx];
        var total = (proj && proj.photos) ? proj.photos.length : 1;
        var counter = document.getElementById('modalSliderCounter');
        if (counter) {
            counter.textContent = (window.currentModalSlideIdx + 1) + ' / ' + total;
        }
    }

    function pauseSlideshow() {
        window.isModalPaused = true;
        if (window.modalSlideTimer) {
            clearInterval(window.modalSlideTimer);
            window.modalSlideTimer = null;
        }
    }

    function resumeSlideshow() {
        window.isModalPaused = false;
        restartSlideTimer();
    }

    function restartSlideTimer() {
        if (window.modalSlideTimer) {
            clearInterval(window.modalSlideTimer);
            window.modalSlideTimer = null;
        }
        var proj = window.chapterProjects[window.currentModalProjectIdx];
        if (!proj || !proj.photos || proj.photos.length <= 1) return;
        if (window.isModalPaused) return;

        window.modalSlideTimer = setInterval(function() {
            if (!window.isModalPaused) {
                nextModalSlide();
            }
        }, 3800);
    }

    function closeProjectModal() {
        var modal = document.getElementById('projectModal');
        if (modal) {
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
        if (window.modalSlideTimer) {
            clearInterval(window.modalSlideTimer);
            window.modalSlideTimer = null;
        }
    }

    document.addEventListener('keydown', function(e) {
        var modal = document.getElementById('projectModal');
        if (!modal || !modal.classList.contains('active')) return;
        if (e.key === 'Escape') {
            closeProjectModal();
        } else if (e.key === 'ArrowRight') {
            nextModalSlide();
        } else if (e.key === 'ArrowLeft') {
            prevModalSlide();
        }
    });

    window.addEventListener('DOMContentLoaded', function() {
        if (window.location.hash && window.location.hash.startsWith('#project-')) {
            var num = parseInt(window.location.hash.replace('#project-', ''), 10);
            if (!isNaN(num) && num >= 1 && num <= window.chapterProjects.length) {
                openProjectModal(num - 1);
            }
        }
    });
    </script>
    <?php endif; ?>

    <?php include __DIR__ . '/../includes/public_footer.php'; ?>

</body>
</html>
