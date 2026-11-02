<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

$memberId = (int)($_GET['id'] ?? 0);
$prcNumber = trim($_GET['prc'] ?? '');
$nameQuery = trim($_GET['name'] ?? '');

$member = null;

// 1. Fetch from database
if ($memberId > 0) {
    $stmt = $pdo->prepare("SELECT wm.*, COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path FROM website_members wm LEFT JOIN users u ON wm.user_id = u.id WHERE wm.id = ? AND wm.is_published = 1 AND wm.deleted_at IS NULL");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
} elseif ($prcNumber !== '') {
    $stmt = $pdo->prepare("SELECT wm.*, COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path FROM website_members wm LEFT JOIN users u ON wm.user_id = u.id WHERE (wm.prc_number = ? OR wm.id_number = ?) AND wm.is_published = 1 AND wm.deleted_at IS NULL");
    $stmt->execute([$prcNumber, $prcNumber]);
    $member = $stmt->fetch();
} elseif ($nameQuery !== '') {
    $stmt = $pdo->prepare("SELECT wm.*, COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path FROM website_members wm LEFT JOIN users u ON wm.user_id = u.id WHERE wm.name = ? AND wm.is_published = 1 AND wm.deleted_at IS NULL");
    $stmt->execute([$nameQuery]);
    $member = $stmt->fetch();
}

// 2. Check if member is in good standing
if ($member && !empty($member['user_id'])) {
    if (function_exists('is_good_member') && !is_good_member($pdo, (int)$member['user_id'])) {
        $member = null; // Do not show if standing is revoked
    }
}

// Decode multiple project gallery photos
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

$pageTitle = $member ? htmlspecialchars($member['name']) . ' • Verified Architect Profile | UAP Quezon' : 'Member Profile Not Found • UAP Quezon Province Chapter';
$activePage = 'architects';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/public_theme.css">
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
                <a href="<?php echo BASE_URL; ?>/public/architects.php" class="btn-portal" style="display: inline-flex;">
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
                    <?php if (!empty($member['photo_path'])): ?>
                        <img src="<?php echo BASE_URL . '/' . ltrim($member['photo_path'], '/'); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>">
                    <?php else: ?>
                        <div class="profile-avatar-initials"><?php echo htmlspecialchars($initials); ?></div>
                    <?php endif; ?>
                    <div class="verified-badge-floating" title="Verified PRC Registered Architect">
                        <?php echo icon('check', '', 16); ?>
                    </div>
                </div>

                <div class="profile-details">
                    <h1 class="profile-name"><?php echo htmlspecialchars($member['name']); ?></h1>
                    <div class="profile-role"><?php echo htmlspecialchars($member['role_title'] ?: 'Architect'); ?></div>

                    <div class="profile-badges-row">
                        <span class="badge-verified-pill">
                            <?php echo icon('shield_check', '', 14); ?>
                            <span>Good Standing (Verified)</span>
                        </span>
                        <span class="badge-gold-pill">
                            <?php echo icon('landmark', '', 14); ?>
                            <span>UAP Chapter 121</span>
                        </span>
                        <span class="badge-gold-pill">
                            <?php echo icon('map_pin', '', 14); ?>
                            <span><?php echo htmlspecialchars($cleanLocation); ?></span>
                        </span>
                    </div>
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
                        <span class="cred-label">PRC Registration License</span>
                        <span class="cred-value" style="color: var(--quezon-green-light);"><?php echo htmlspecialchars($prcNumber); ?></span>
                    </div>

                    <div class="cred-item">
                        <span class="cred-label">Chapter Member ID</span>
                        <span class="cred-value" style="color: var(--quezon-gold);"><?php echo htmlspecialchars($chapterId); ?></span>
                    </div>

                    <div class="cred-item">
                        <span class="cred-label">Chapter Affiliation</span>
                        <span class="cred-value">UAP Quezon Province Chapter (121)</span>
                    </div>

                    <div class="cred-item">
                        <span class="cred-label">Architectural Specialization</span>
                        <span class="cred-value"><?php echo htmlspecialchars($cleanSpecialty); ?></span>
                    </div>

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
                        <span class="cred-label">Official Standing Status</span>
                        <span class="cred-value" style="color: #34d399; display: flex; align-items: center; gap: 6px;">
                            <?php echo icon('shield_check', '', 14); ?>
                            <span>Certified Good Standing</span>
                        </span>
                    </div>

                    <div class="cred-item">
                        <span class="cred-label">Public Directory Status</span>
                        <span class="cred-value">Active Verified Chapter Member</span>
                    </div>
                </div>
            </div>

            <!-- 2. PROJECT PORTFOLIO / GALLERY -->
            <?php if (!empty($gallery)): ?>
            <div class="section-box">
                <div class="section-header-row">
                    <h2>
                        <span style="color: var(--quezon-gold);"><?php echo icon('camera', '', 20); ?></span>
                        <span>Project Portfolio &amp; Works Gallery (<?php echo count($gallery); ?>)</span>
                    </h2>
                </div>
                <div class="gallery-grid">
                    <?php foreach ($gallery as $gIndex => $gItem): ?>
                        <div class="gallery-card">
                            <div class="gallery-card-img-wrap">
                                <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($gItem['path']); ?>" 
                                     alt="<?php echo htmlspecialchars($member['name']); ?> Project Work <?php echo $gIndex + 1; ?>"
                                     loading="lazy">
                            </div>
                            <?php if (!empty(trim($gItem['description'] ?? ''))): ?>
                                <div class="gallery-card-desc">
                                    <?php echo nl2br(htmlspecialchars($gItem['description'])); ?>
                                </div>
                            <?php endif; ?>
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

                <?php if (!empty($member['qr_image_path'])): ?>
                    <div style="margin: 0 auto 10px; width: 140px; height: 140px; padding: 8px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.3);">
                        <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($member['qr_image_path']); ?>" alt="Architect Verification QR" style="width: 100%; height: 100%; object-fit: contain;">
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

    <?php include __DIR__ . '/../includes/public_footer.php'; ?>

</body>
</html>
