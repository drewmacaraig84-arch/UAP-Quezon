<?php
/**
 * Shared Public Navigation Bar
 * Included by homepage.php and dedicated public pages.
 */
$activePage = $activePage ?? 'home';
$logoSrc = BASE_URL . '/public/logo.jpg';
?>
<!-- STICKY NAVBAR -->
<header>
    <div class="header-container">
        <a href="<?php echo BASE_URL; ?>/public/homepage.php" class="brand-logo" style="text-decoration: none;">
            <img src="<?php echo $logoSrc; ?>" alt="UAP Quezon Province Chapter Logo">
            <div>
                <div class="brand-title">
                    <span class="gold">UAP</span> <span class="green">QUEZON</span>
                </div>
                <span class="brand-sub">United Architects of the Philippines</span>
            </div>
        </a>

        <nav class="desktop-nav">
            <ul>
                <li><a href="<?php echo BASE_URL; ?>/public/homepage.php" class="<?php echo $activePage === 'home' ? 'active' : ''; ?>">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/about.php" class="<?php echo $activePage === 'about' ? 'active' : ''; ?>">About</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/architects.php" class="<?php echo $activePage === 'architects' ? 'active' : ''; ?>">Architects</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/news.php" class="<?php echo $activePage === 'news' ? 'active' : ''; ?>">News &amp; Events</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/milestones.php" class="<?php echo $activePage === 'milestones' ? 'active' : ''; ?>">Milestones</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/contact.php" class="<?php echo $activePage === 'contact' ? 'active' : ''; ?>">Contact</a></li>
            </ul>
        </nav>

        <div class="header-actions">
            <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn-portal">
                <?php echo icon('shield_check', '', 14); ?>
                <span>Portal Login</span>
            </a>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle navigation menu" onclick="toggleMobileMenu()">
                <?php echo icon('dashboard', '', 20); ?>
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
            <li><a href="<?php echo BASE_URL; ?>/public/contact.php" class="<?php echo $activePage === 'contact' ? 'active' : ''; ?>" onclick="closeMobileMenu()"><?php echo icon('mail', '', 16); ?> <span>Contact Us</span></a></li>
            <li class="mobile-portal-item">
                <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn-mobile-portal">
                    <?php echo icon('shield_check', '', 16); ?>
                    <span>Sign In to Member Portal</span>
                </a>
            </li>
        </ul>
    </div>
</header>

