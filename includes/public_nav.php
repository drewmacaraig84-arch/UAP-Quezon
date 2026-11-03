<?php
/**
 * Shared Public Navigation Bar
 * Included by homepage.php and dedicated public pages.
 */
$activePage = $activePage ?? 'home';
$logoSrc = BASE_URL . '/public/logo.jpg';

// Fetch uploaded header auxiliary badge images (Slots 1 to 3)
$headerBadges = [];
if (isset($pdo)) {
    for ($i = 1; $i <= 3; $i++) {
        $imgVal = trim(get_site_setting($pdo, "header_image_{$i}", ''));
        if ($imgVal) {
            $headerBadges[] = [
                'slot' => $i,
                'src'  => media_url($imgVal)
            ];
        }
    }
}

$isUserLoggedIn = !empty($_SESSION['user_id']);
$userPortalUrl = BASE_URL . '/auth/login.php';
$portalLabel = 'Portal Login';
$portalMobileLabel = 'Sign In to Member Portal';

if ($isUserLoggedIn) {
    $userRole = $_SESSION['role'] ?? 'member';
    if (in_array($userRole, ['admin', 'super_admin'], true)) {
        $userPortalUrl = BASE_URL . '/admin/dashboard.php';
        $portalLabel = 'Admin Portal &rarr;';
        $portalMobileLabel = 'Go to Admin Portal';
    } else {
        $userPortalUrl = BASE_URL . '/member/dashboard.php';
        $portalLabel = 'My Portal &rarr;';
        $portalMobileLabel = 'Go to Member Dashboard';
    }
}
?>
<!-- STICKY NAVBAR -->
<header>
    <style>
        .header-container {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0.75rem 2rem !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            gap: 1.25rem !important;
            box-sizing: border-box !important;
            width: 100% !important;
        }
        .brand-group {
            display: flex !important;
            align-items: center !important;
            gap: 14px !important;
            min-width: 0 !important;
            flex: 1 1 auto !important;
        }
        .brand-logo {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            text-decoration: none !important;
            min-width: 0 !important;
            flex-shrink: 0 !important;
        }
        .brand-logo img {
            height: 48px !important;
            width: auto !important;
            border-radius: 6px !important;
            background: #ffffff !important;
            padding: 2px !important;
            flex-shrink: 0 !important;
        }
        .brand-title {
            font-size: 1.15rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.01em !important;
            color: #ffffff !important;
            line-height: 1.15 !important;
            white-space: nowrap !important;
        }
        .brand-title span.gold { color: var(--quezon-gold, #e58a13) !important; }
        .brand-title span.green { color: var(--quezon-green-light, #22c55e) !important; }
        .brand-title .brand-province {
            font-size: 0.88em !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            letter-spacing: 0.02em !important;
        }
        .brand-sub {
            font-size: 0.72rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.12em !important;
            color: var(--text-muted, #94a3b8) !important;
            font-weight: 600 !important;
            display: block !important;
            white-space: nowrap !important;
        }
        .header-badges {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            padding-left: 12px !important;
            border-left: 1px solid rgba(255, 255, 255, 0.18) !important;
            flex-shrink: 0 !important;
        }
        .header-badge-box {
            width: 44px !important;
            height: 44px !important;
            max-width: 44px !important;
            max-height: 44px !important;
            min-width: 44px !important;
            min-height: 44px !important;
            border-radius: 6px !important;
            background: #ffffff !important;
            padding: 3px !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
            flex-shrink: 0 !important;
            border: 1px solid rgba(229, 138, 19, 0.4) !important;
            box-sizing: border-box !important;
            transition: transform 0.2s ease, border-color 0.2s ease !important;
        }
        .header-badge-box:hover {
            transform: translateY(-2px) !important;
            border-color: var(--quezon-gold, #e58a13) !important;
        }
        .header-badge-img {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            display: block !important;
        }
        .header-actions {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            flex-shrink: 0 !important;
        }
        .btn-nav-portal {
            display: inline-flex !important;
            align-items: center !important;
            gap: 5px !important;
            padding: 0.38rem 0.75rem !important;
            background: var(--gradient-brand, linear-gradient(135deg, #e58a13 0%, #15803d 100%)) !important;
            color: #ffffff !important;
            border-radius: 6px !important;
            font-size: 0.72rem !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            text-decoration: none !important;
            box-shadow: 0 3px 10px rgba(229, 138, 19, 0.25) !important;
            transition: all 0.2s ease !important;
            white-space: nowrap !important;
            flex-shrink: 0 !important;
            line-height: 1.1 !important;
        }
        .btn-nav-portal .portal-lbl-full { display: inline !important; }
        .btn-nav-portal .portal-lbl-short { display: none !important; }
        .btn-nav-portal svg {
            width: 13px !important;
            height: 13px !important;
            flex-shrink: 0 !important;
        }
        .btn-nav-portal:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 14px rgba(21, 128, 61, 0.4) !important;
            color: #ffffff !important;
        }
        .mobile-menu-btn {
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            width: 40px !important;
            height: 40px !important;
            border-radius: 8px !important;
            background: rgba(255, 255, 255, 0.06) !important;
            border: 1px solid var(--quezon-border, rgba(255, 255, 255, 0.12)) !important;
            color: #cbd5e1 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            flex-shrink: 0 !important;
            padding: 0 !important;
        }
        .mobile-menu-btn:hover, .mobile-menu-btn:active {
            background: rgba(229, 138, 19, 0.15) !important;
            border-color: var(--quezon-gold, #e58a13) !important;
            color: var(--quezon-gold, #e58a13) !important;
        }

        /* Desktop Nav & Single-Line Spacing */
        .desktop-nav ul {
            display: flex !important;
            align-items: center !important;
            gap: clamp(0.55rem, 1.1vw, 1.25rem) !important;
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .desktop-nav li {
            margin: 0 !important;
            padding: 0 !important;
            white-space: nowrap !important;
        }
        .desktop-nav a {
            font-size: clamp(0.76rem, 0.85vw, 0.84rem) !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.03em !important;
            color: #cbd5e1 !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            display: inline-block !important;
            padding: 4px 2px !important;
            transition: color 0.2s ease !important;
        }
        .desktop-nav a:hover,
        .desktop-nav a.active {
            color: var(--quezon-gold, #f59e0b) !important;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1040px) {
            .desktop-nav { display: none !important; }
            .mobile-menu-btn { display: inline-flex !important; }
        }
        @media (max-width: 900px) {
            .header-container { padding: 0.65rem 1.25rem !important; }
            .header-badges { gap: 6px !important; padding-left: 10px !important; }
            .header-badge-box { width: 36px !important; height: 36px !important; min-width: 36px !important; min-height: 36px !important; }
        }
        @media (max-width: 768px) {
            .header-container { padding: 0.55rem 0.85rem !important; gap: 8px !important; }
            .header-badges { display: none !important; }
            .brand-sub { display: none !important; }
            .brand-logo { gap: 8px !important; }
            .brand-logo img { height: 38px !important; }
            .brand-title { font-size: 0.95rem !important; }
            .brand-title .brand-province { font-size: 0.78rem !important; }
        }
        @media (max-width: 520px) {
            .header-container { padding: 0.45rem 0.65rem !important; }
            .brand-logo img { height: 34px !important; }
            .brand-title { font-size: 0.85rem !important; }
            .brand-title .brand-province { font-size: 0.72rem !important; display: block !important; line-height: 1.1 !important; }
            .header-actions { gap: 6px !important; }
            .btn-nav-portal { padding: 0.32rem 0.5rem !important; font-size: 0.68rem !important; }
            .btn-nav-portal .portal-lbl-full { display: none !important; }
            .btn-nav-portal .portal-lbl-short { display: inline !important; }
            .mobile-menu-btn { width: 36px !important; height: 36px !important; }
        }
        @media (max-width: 380px) {
            .header-container { padding: 0.4rem 0.5rem !important; }
            .brand-logo img { height: 30px !important; }
            .brand-title { font-size: 0.78rem !important; }
            .brand-title .brand-province { font-size: 0.65rem !important; }
            .btn-nav-portal { padding: 0.3rem 0.45rem !important; font-size: 0.64rem !important; }
            .mobile-menu-btn { width: 34px !important; height: 34px !important; }
        }
    </style>
    <div class="header-container">
        <div class="brand-group">
            <a href="<?php echo BASE_URL; ?>/public/homepage.php" class="brand-logo">
                <img src="<?php echo $logoSrc; ?>" alt="UAP Quezon Province Chapter Logo">
                <div>
                    <div class="brand-title">
                        <span class="gold">UAP</span> <span class="green">QUEZON</span> <span class="brand-province">PROVINCE CHAPTER</span>
                    </div>
                    <span class="brand-sub">United Architects of the Philippines</span>
                </div>
            </a>

            <?php if (!empty($headerBadges)): ?>
                <div class="header-badges">
                    <?php foreach ($headerBadges as $badge): ?>
                        <div class="header-badge-box" title="Affiliation &amp; Partner Badge <?php echo $badge['slot']; ?>">
                            <img src="<?php echo htmlspecialchars($badge['src']); ?>" alt="Affiliation badge <?php echo $badge['slot']; ?>" class="header-badge-img" onerror="this.parentElement.style.display='none';">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <nav class="desktop-nav">
            <ul>
                <li><a href="<?php echo BASE_URL; ?>/public/homepage.php" class="<?php echo $activePage === 'home' ? 'active' : ''; ?>">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/about.php" class="<?php echo $activePage === 'about' ? 'active' : ''; ?>">About</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/architects.php" class="<?php echo $activePage === 'architects' ? 'active' : ''; ?>">Architects</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/news.php" class="<?php echo $activePage === 'news' ? 'active' : ''; ?>">News &amp; Events</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/milestones.php" class="<?php echo $activePage === 'milestones' ? 'active' : ''; ?>">Milestones</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/partners.php" class="<?php echo $activePage === 'partners' ? 'active' : ''; ?>">Partners</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/contact.php" class="<?php echo $activePage === 'contact' ? 'active' : ''; ?>">Contact</a></li>
            </ul>
        </nav>

        <div class="header-actions">
            <a href="<?php echo $userPortalUrl; ?>" class="btn-nav-portal" title="<?php echo $isUserLoggedIn ? 'Switch back to your portal dashboard' : 'Sign in to chapter portal'; ?>">
                <?php echo icon($isUserLoggedIn ? 'dashboard' : 'shield_check', '', 14); ?>
                <span class="portal-lbl-full"><?php echo $portalLabel; ?></span>
                <span class="portal-lbl-short">Portal</span>
            </a>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle navigation menu" onclick="toggleMobileMenu()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Navigation Drawer -->
    <div class="mobile-nav-drawer" id="mobileNavDrawer">
        <ul>
            <li><a href="<?php echo BASE_URL; ?>/public/homepage.php" class="<?php echo $activePage === 'home' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><?php echo icon('dashboard', '', 16); ?> <span>Home</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>/public/about.php" class="<?php echo $activePage === 'about' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><?php echo icon('landmark', '', 16); ?> <span>About Chapter</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>/public/architects.php" class="<?php echo $activePage === 'architects' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><?php echo icon('members', '', 16); ?> <span>Architect Directory</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>/public/news.php" class="<?php echo $activePage === 'news' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><?php echo icon('newspaper', '', 16); ?> <span>News &amp; Events</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>/public/milestones.php" class="<?php echo $activePage === 'milestones' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><?php echo icon('clock', '', 16); ?> <span>Milestones</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>/public/partners.php" class="<?php echo $activePage === 'partners' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><?php echo icon('handshake', '', 16); ?> <span>Partners</span></a></li>
            <li><a href="<?php echo BASE_URL; ?>/public/contact.php" class="<?php echo $activePage === 'contact' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><?php echo icon('mail', '', 16); ?> <span>Contact Us</span></a></li>
            <li class="mobile-portal-item">
                <a href="<?php echo $userPortalUrl; ?>" class="btn-mobile-portal">
                    <?php echo icon($isUserLoggedIn ? 'dashboard' : 'shield_check', '', 16); ?>
                    <span><?php echo $portalMobileLabel; ?></span>
                </a>
            </li>
        </ul>
    </div>
</header>