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
    $stmt = $pdo->prepare("SELECT wm.*, COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path FROM website_members wm LEFT JOIN users u ON wm.user_id = u.id WHERE wm.id = ? AND wm.is_published = 1");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
} elseif ($prcNumber !== '') {
    $stmt = $pdo->prepare("SELECT wm.*, COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path FROM website_members wm LEFT JOIN users u ON wm.user_id = u.id WHERE wm.id_number = ? AND wm.is_published = 1");
    $stmt->execute([$prcNumber]);
    $member = $stmt->fetch();
} elseif ($nameQuery !== '') {
    $stmt = $pdo->prepare("SELECT wm.*, COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path FROM website_members wm LEFT JOIN users u ON wm.user_id = u.id WHERE wm.name = ? AND wm.is_published = 1");
    $stmt->execute([$nameQuery]);
    $member = $stmt->fetch();
}

// 2. Check if unlocked via paid application & in good standing
if ($member && !empty($member['user_id'])) {
    if (function_exists('has_unlocked_website_directory') && !has_unlocked_website_directory($pdo, (int)$member['user_id'])) {
        $member = null; // Do not show if not paid/unlocked
    } elseif (function_exists('is_good_member') && !is_good_member($pdo, (int)$member['user_id'])) {
        $member = null; // Do not show if unpaid past grace period
    }
}

// Redirect to modern React website profile page
if ($member && !empty($member['id'])) {
    header('Location: ' . BASE_URL . '/profile/' . (int)$member['id']);
    exit;
} else {
    header('Location: ' . BASE_URL . '/directory');
    exit;
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

$pageTitle = $member ? htmlspecialchars($member['name']) . ' - Chapter Directory Profile' : 'Member Profile - UAP Quezon Province Chapter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        :root {
            --bg-black: #080808;
            --bg-card: #121212;
            --bg-inner-card: #1a1a1a;
            --accent-yellow: #f5b800;
            --accent-yellow-hover: #d9a300;
            --text-light: #f5f5f5;
            --text-muted: #a0a0a0;
            --border-dark: #262626;
            --border-yellow-subtle: rgba(245, 184, 0, 0.3);
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        *, *:before, *:after { margin: 0; padding: 0; box-sizing: border-box; }

        html {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-black);
            color: var(--text-light);
            font-family: var(--font-family);
            line-height: 1.6;
            min-height: 100vh;
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        a { color: inherit; text-decoration: none; }

        header {
            background-color: #000000;
            border-bottom: 3px solid var(--accent-yellow);
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
        }

        .header-container {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 2rem;
            gap: 1.2rem;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-logo img {
            height: 42px;
            width: auto;
            max-width: 50px;
            object-fit: contain;
            display: block;
        }

        .brand-title {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #ffffff;
            white-space: nowrap;
        }

        .brand-title span {
            color: var(--accent-yellow);
        }

        .header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1.2rem;
        }

        nav ul {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 2rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        nav a {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: color 0.2s ease;
        }

        nav a:hover { color: var(--accent-yellow); }

        .login-btn {
            display: inline-block;
            padding: 0.7rem 1.2rem;
            background: var(--accent-yellow);
            color: #000000;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1;
            transition: background-color 0.2s ease;
        }

        .login-btn:hover {
            background: var(--accent-yellow-hover);
        }

        .btn-yellow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.7rem 1.6rem;
            background-color: var(--accent-yellow);
            color: #000000;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 4px;
            transition: background-color 0.2s ease, transform 0.15s ease;
            font-size: 0.8rem;
        }

        .btn-yellow:hover { background-color: var(--accent-yellow-hover); transform: translateY(-1px); }

        .main-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 4rem;
            flex: 1 0 auto;
        }

        .dark-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-dark);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 1.8rem;
        }

        .section-heading {
            border-left: 4px solid var(--accent-yellow);
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }

        .section-heading h2 {
            font-size: 1.35rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #ffffff;
        }

        .profile-hero-card {
            background: linear-gradient(135deg, #141c28 0%, #0e141e 100%);
            border: 1px solid rgba(245, 184, 0, 0.35);
            border-radius: 12px;
            padding: 28px 32px;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 1.8rem;
            box-shadow: 0 12px 30px rgba(0,0,0,0.4);
        }

        .profile-avatar-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f5b800, #b45309);
            color: #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 900;
            flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(245, 184, 0, 0.3);
        }

        .profile-hero-info h1 {
            font-size: 2.2rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .profile-hero-info .role-tag {
            color: var(--accent-yellow);
            font-size: 1.15rem;
            font-weight: 700;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
        }

        .info-cell {
            background: var(--bg-inner-card);
            border: 1px solid var(--border-dark);
            border-radius: 6px;
            padding: 16px 18px;
        }

        .info-cell label {
            display: block;
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .info-cell span {
            font-size: 1.05rem;
            color: #ffffff;
            font-weight: 600;
            word-break: break-word;
        }

        .content-box {
            background: var(--bg-inner-card);
            border-left: 3px solid var(--accent-yellow);
            border-radius: 0 6px 6px 0;
            padding: 18px 22px;
            color: #d1d5db;
            font-size: 0.98rem;
            line-height: 1.75;
        }

        .qr-wrapper {
            background: #ffffff;
            padding: 12px;
            border-radius: 10px;
            display: inline-block;
            margin: 12px 0;
        }

        .qr-wrapper img {
            max-width: 160px;
            max-height: 160px;
            object-fit: contain;
            display: block;
        }

        footer {
            background-color: #000000;
            border-top: 1px solid var(--border-dark);
            padding: 3rem 2rem 1.5rem;
            width: 100%;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2.5rem;
            margin-bottom: 2rem;
        }

        .footer-col h1 {
            font-size: 1.1rem;
            color: var(--accent-yellow);
            margin-bottom: 1rem;
        }

        .footer-col h4 {
            color: var(--text-light);
            font-size: 0.95rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .contact-details p {
            color: var(--text-muted);
            font-size: 0.88rem;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .contact-details strong { color: var(--text-light); }

        .copyright {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-dark);
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            nav ul { display: none; }
            .header-container { padding: 0.8rem 1rem; }
            .main-container { padding: 1.5rem 1rem 3rem; }
            .profile-hero-card { flex-direction: column; text-align: center; padding: 20px 16px; }
            .profile-hero-info h1 { font-size: 1.6rem; }
            .profile-hero-info .role-tag { font-size: 1rem; }
            .dark-card { padding: 1.2rem; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- STICKY HEADER -->
    <header>
        <div class="header-container">
            <div class="brand-logo">
                <a href="<?php echo BASE_URL; ?>/index.php" style="display:flex;align-items:center;gap:14px;text-decoration:none;">
                    <img src="<?php echo BASE_URL; ?>/public/logo.jpg" alt="UAP Quezon Logo" onerror="if(this.src.indexOf('uploads/logo.jpg')===-1)this.src='<?php echo BASE_URL; ?>/uploads/logo.jpg';">
                    <div class="brand-title">UAP-<span>Quezon Province Chapter</span></div>
                </a>
            </div>

            <div class="header-actions">
                <nav>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#home">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#members">Members</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#about">About</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#news">News</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#contact">Contact</a></li>
                    </ul>
                </nav>
                <a href="<?php echo BASE_URL; ?>/auth/login.php" class="login-btn">Login</a>
            </div>
        </div>
    </header>

    <!-- MAIN CONTAINER -->
    <div class="main-container">
        
        <div style="margin-bottom: 1.4rem;">
            <a href="<?php echo BASE_URL; ?>/index.php#members" class="btn-yellow">
                &larr; Back to Chapter Directory
            </a>
        </div>

        <?php if (!$member): ?>
            <section class="dark-card" style="text-align:center; padding: 4rem 2rem;">
                <div style="width: 60px; height: 60px; border-radius: 16px; background: rgba(245,158,11,0.12); color: var(--accent-yellow, #f5b800); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                    <?php echo icon('landmark', '', 32); ?>
                </div>
                <h2 style="color:#fff; margin-bottom: 10px;">Member Profile Not Found / Under Verification</h2>
                <p style="color:var(--text-muted); max-width: 520px; margin: 0 auto 24px; font-size: 0.95rem; line-height: 1.6;">
                    This member profile is either not published yet or is currently awaiting annual directory placement fee verification.
                </p>
                <a href="<?php echo BASE_URL; ?>/index.php#members" class="btn-yellow">Return to Directory</a>
            </section>
        <?php else: 
            $initials = '';
            $parts = explode(' ', preg_replace('/^(Ar\.|Arch\.|Architect)\s+/i', '', $member['name']));
            foreach ($parts as $p) {
                if (!empty($p)) $initials .= strtoupper($p[0]);
            }
            $initials = substr($initials, 0, 2) ?: 'AR';

            // Sanitize text fields
            $cleanSpecialty = (!empty($member['specialty']) && strcasecmp(trim($member['specialty']), 'none') !== 0) ? $member['specialty'] : 'General Practice';
            $cleanLocation = (!empty($member['location']) && strcasecmp(trim($member['location']), 'none') !== 0) ? $member['location'] : 'Quezon, Philippines';
            $cleanAchievements = (!empty($member['achievements']) && strcasecmp(trim($member['achievements']), 'none') !== 0) ? $member['achievements'] : 'Registered Architect actively practicing in the Quezon region.';
            $cleanAwards = (!empty($member['awards']) && strcasecmp(trim($member['awards']), 'none') !== 0) ? $member['awards'] : 'Active professional member in good standing under UAP Quezon Province Chapter (121).';
        ?>

            <!-- HERO MEMBER HEADER -->
            <div class="profile-hero-card">
                <div class="profile-avatar-circle">
                    <?php if (!empty($member['photo_path'])): ?>
                        <img src="<?php echo BASE_URL . '/' . ltrim($member['photo_path'], '/'); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <?php echo htmlspecialchars($initials); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-hero-info">
                    <h1><?php echo htmlspecialchars($member['name']); ?></h1>
                    <div class="role-tag"><?php echo htmlspecialchars($member['role_title'] ?: 'Architect'); ?></div>
                </div>
            </div>

            <!-- PROFESSIONAL CREDENTIALS -->
            <section class="dark-card">
                <div class="section-heading">
                    <h2 style="display:flex; align-items:center; gap:8px;"><?php echo icon('landmark', '', 20); ?> <span>Professional Credentials</span></h2>
                </div>
                <div class="info-grid">
                    <div class="info-cell">
                        <label>PRC License Number</label>
                        <span><?php echo htmlspecialchars($member['id_number'] ?: '—'); ?></span>
                    </div>
                    <div class="info-cell">
                        <label>Chapter Affiliation</label>
                        <span>UAP Quezon Province Chapter (121)</span>
                    </div>
                    <div class="info-cell">
                        <label>Architectural Specialty</label>
                        <span><?php echo htmlspecialchars($cleanSpecialty); ?></span>
                    </div>
                    <?php if (!empty($member['company_name'])): ?>
                    <div class="info-cell">
                        <label>Company / Firm Name</label>
                        <span style="display:inline-flex; align-items:center; gap:6px;">
                            <?php echo icon('briefcase', '', 14); ?>
                            <strong><?php echo htmlspecialchars($member['company_name']); ?></strong>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($member['link_url'])): 
                        $linkPlatform = function_exists('detect_social_link_type') ? detect_social_link_type($member['link_url'], $member['link_type'] ?? 'auto') : 'website';
                    ?>
                    <div class="info-cell">
                        <label>Official Link (<?php echo ucfirst($linkPlatform); ?>)</label>
                        <span>
                            <a href="<?php echo htmlspecialchars($member['link_url']); ?>" target="_blank" rel="noopener noreferrer" style="color: var(--accent-yellow); display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                                <?php echo icon($linkPlatform === 'website' ? 'globe' : $linkPlatform, '', 14); ?>
                                <span><?php echo htmlspecialchars($member['link_url']); ?> &rarr;</span>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="info-cell">
                        <label>Base Location</label>
                        <span><?php echo htmlspecialchars($cleanLocation); ?></span>
                    </div>
                    <div class="info-cell">
                        <label>Status / Standing</label>
                        <span style="color: #10b981; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                            <?php echo icon('shield_check', '', 14); ?> <span>Good Standing (Verified)</span>
                        </span>
                    </div>
                    <div class="info-cell">
                        <label>Website Placement</label>
                        <span>Active Chapter Member</span>
                    </div>
                </div>
            </section>

            <!-- FEATURED PROJECT / WORK GALLERY -->
            <?php if (!empty($gallery)): ?>
            <section class="dark-card">
                <div class="section-heading">
                    <h2 style="display:flex; align-items:center; gap:8px;"><?php echo icon('camera', '', 20); ?> <span>Project Portfolio &amp; Works Gallery (<?php echo count($gallery); ?>)</span></h2>
                </div>
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <?php foreach ($gallery as $gIndex => $gItem): ?>
                        <div style="background: var(--bg-inner-card); border: 1px solid var(--border-dark); border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 8px 24px rgba(0,0,0,0.3);">
                            <div style="width: 100%; height: 230px; overflow: hidden; background: #000; display: flex; align-items: center; justify-content: center;">
                                <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($gItem['path']); ?>" 
                                     alt="<?php echo htmlspecialchars($member['name']); ?> Work <?php echo $gIndex + 1; ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;"
                                     onmouseover="this.style.transform='scale(1.04)'"
                                     onmouseout="this.style.transform='scale(1)'">
                            </div>
                            <?php if (!empty(trim($gItem['description'] ?? ''))): ?>
                                <div style="padding: 14px 16px; color: #d1d5db; font-size: 0.92rem; line-height: 1.6; border-top: 1px solid var(--border-dark); flex: 1;">
                                    <?php echo nl2br(htmlspecialchars($gItem['description'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- ACHIEVEMENTS -->
            <section class="dark-card">
                <div class="section-heading">
                    <h2 style="display:flex; align-items:center; gap:8px;"><?php echo icon('trophy', '', 20); ?> <span>Career Achievements &amp; Practice</span></h2>
                </div>
                <div class="content-box">
                    <?php echo nl2br(htmlspecialchars($cleanAchievements)); ?>
                </div>
            </section>

            <!-- AWARDS -->
            <section class="dark-card">
                <div class="section-heading">
                    <h2 style="display:flex; align-items:center; gap:8px;"><?php echo icon('award', '', 20); ?> <span>Honors, Distinctions &amp; Awards</span></h2>
                </div>
                <div class="content-box">
                    <?php echo nl2br(htmlspecialchars($cleanAwards)); ?>
                </div>
            </section>

            <!-- CHAPTER VERIFICATION & QR -->
            <section class="dark-card" style="text-align: center;">
                <div class="section-heading" style="text-align: left;">
                    <h2 style="display:flex; align-items:center; gap:8px;"><?php echo icon('shield_check', '', 20); ?> <span>Chapter Verification</span></h2>
                </div>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.2rem;">
                    Official registered architect verified under the United Architects of the Philippines – Quezon Province Chapter.
                </p>
                
                <?php if (!empty($member['qr_image_path'])): ?>
                    <div class="qr-wrapper">
                        <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($member['qr_image_path']); ?>" alt="Architect QR Card">
                    </div>
                    <p style="font-size: 0.82rem; color: var(--text-muted);">Scan to verify / contact</p>
                <?php else: ?>
                    <div style="background: var(--bg-inner-card); border: 1px dashed var(--border-dark); border-radius: 8px; padding: 24px; max-width: 440px; margin: 0 auto; display:flex; flex-direction:column; align-items:center; gap:8px;">
                        <div style="color: var(--accent-yellow, #f5b800);"><?php echo icon('landmark', '', 32); ?></div>
                        <strong style="color: #ffffff; font-size: 0.95rem;">UAP Quezon Province Chapter Registered Member</strong>
                    </div>
                <?php endif; ?>
            </section>

        <?php endif; ?>

    </div>

    <!-- FOOTER -->
    <footer id="contact">
        <div class="footer-container">
            <div class="footer-col">
                <h1>UAP Quezon Province Chapter</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.6;">
                    Official chapter of the United Architects of the Philippines (IAPOA Chapter 121) serving architects and communities across Quezon.
                </p>
            </div>
            <div class="footer-col">
                <h4>Contact Us</h4>
                <div class="contact-details">
                    <p style="display:flex; align-items:center; gap:6px;"><?php echo icon('map_pin', '', 14); ?> <strong>Address:</strong> Lucena City, Quezon Province, Philippines</p>
                    <p style="display:flex; align-items:center; gap:6px;"><?php echo icon('mail', '', 14); ?> <strong>Email:</strong> quezon@united-architects.ph</p>
                </div>
            </div>
        </div>
        <div class="copyright">
            <div>
                &copy; <?php echo date('Y'); ?> United Architects of the Philippines - Quezon Province Chapter. All rights reserved.
                <div style="margin-top: 6px; font-size: 0.85rem; color: var(--text-muted, #94a3b8);">
                    Designed &amp; Developed by <a href="https://drewportfoliio.vercel.app/" target="_blank" rel="noopener noreferrer" style="color: var(--quezon-gold, #f59e0b); font-weight: 700; text-decoration: underline; text-underline-offset: 3px;">Drew Macaraig</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
