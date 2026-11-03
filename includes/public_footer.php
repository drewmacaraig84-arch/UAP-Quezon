<?php
/**
 * Shared Public Footer
 */
$logoSrc = BASE_URL . '/public/logo.jpg';
$contactEmail = $contactEmail ?? get_site_setting($pdo, 'contact_email', 'uap.qpc@gmail.com');
if (empty($contactEmail) || in_array($contactEmail, ['quezon@united-architects.ph', 'info@uap-quezon.org'], true)) {
    $contactEmail = 'uap.qpc@gmail.com';
}
$contactPhone = $contactPhone ?? get_site_setting($pdo, 'contact_phone', '09399125237 / 09175939781');
if (empty($contactPhone) || strpos($contactPhone, '555') !== false) {
    $contactPhone = '09399125237 / 09175939781';
}
$contactAddress = $contactAddress ?? get_site_setting($pdo, 'contact_address', 'Lucena City, Quezon Province, Philippines');
$hoursWeekdays = $hoursWeekdays ?? get_site_setting($pdo, 'office_hours_weekdays', '9:00 AM – 5:00 PM');
$hoursSaturday = $hoursSaturday ?? get_site_setting($pdo, 'office_hours_saturday', '9:00 AM – 12:00 PM');
$hoursSunday = $hoursSunday ?? get_site_setting($pdo, 'office_hours_sunday', 'Closed');
?>
<!-- FOOTER -->
<footer>
    <div class="footer-container">
        <div>
            <div class="footer-brand">
                <img src="<?php echo $logoSrc; ?>" alt="UAP Quezon Logo">
                <div>
                    <div style="font-weight: 800; font-size: 1.1rem; color: #ffffff;">UAP QUEZON PROVINCE CHAPTER</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em;">United Architects of the Philippines</div>
                </div>
            </div>
            <p style="font-size: 0.88rem; color: var(--text-muted); max-width: 440px; line-height: 1.6;">
                The integrated and accredited professional organization of architects (IAPOA) representing registered architects of Quezon Province.
            </p>
        </div>

        <div class="footer-col">
            <h4>Chapter Pages</h4>
            <ul>
                <li><a href="<?php echo BASE_URL; ?>/public/homepage.php">Home (Overview)</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/about.php">About UAP Quezon</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/architects.php">Chapter Architects Directory</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/news.php">News, CPD &amp; Events</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/milestones.php">Timeline &amp; Milestones</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/partners.php">Industry Partners</a></li>
                <li><a href="<?php echo BASE_URL; ?>/public/contact.php">Contact Secretariat</a></li>
                <li><a href="<?php echo BASE_URL; ?>/auth/login.php">Member Portal &amp; Dues</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Contact UAP-QPC</h4>
            <ul>
                <li style="display: flex; align-items: flex-start; gap: 8px; color: var(--text-muted); font-size: 0.88rem; line-height: 1.4;">
                    <span style="color: var(--quezon-gold); flex-shrink: 0; margin-top: 2px;"><?php echo icon('map_pin', '', 15); ?></span>
                    <span><?php echo htmlspecialchars($contactAddress); ?></span>
                </li>
                <li style="display: flex; align-items: center; gap: 8px; font-size: 0.88rem;">
                    <span style="color: var(--quezon-gold); flex-shrink: 0;"><?php echo icon('mail', '', 15); ?></span>
                    <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>" style="color: var(--text-muted); text-decoration: none; word-break: break-all;"><?php echo htmlspecialchars($contactEmail); ?></a>
                </li>
                <li style="display: flex; align-items: center; gap: 8px; font-size: 0.88rem;">
                    <span style="color: var(--quezon-gold); flex-shrink: 0;"><?php echo icon('phone', '', 15); ?></span>
                    <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $contactPhone)); ?>" style="color: var(--text-muted); text-decoration: none;"><?php echo htmlspecialchars($contactPhone); ?></a>
                </li>
                <li style="display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 0.88rem;">
                    <span style="color: var(--quezon-gold); flex-shrink: 0;"><?php echo icon('clock', '', 15); ?></span>
                    <span>Mon &ndash; Fri &bull; <?php echo htmlspecialchars($hoursWeekdays); ?></span>
                </li>
                <li style="margin-top: 6px;">
                    <a href="<?php echo BASE_URL; ?>/public/contact.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--quezon-green-light); font-weight: 700; font-size: 0.85rem; text-decoration: none;">
                        <span>Message Chapter Secretariat</span>
                        <?php echo icon('arrow_right', '', 13); ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="copyright">
        <div>
            &copy; <?php echo date('Y'); ?> United Architects of the Philippines - Quezon Province Chapter. All rights reserved.
            <div style="margin-top: 6px; font-size: 0.85rem; color: var(--text-muted);">
                Designed &amp; Developed by <a href="https://drewportfoliio.vercel.app/" target="_blank" rel="noopener noreferrer" style="color: var(--quezon-gold); font-weight: 700; text-decoration: underline; text-underline-offset: 3px;">Drew Macaraig</a>
            </div>
        </div>
        <div style="color: var(--quezon-green-light); font-weight: 700; letter-spacing: 0.05em;">DISTRICT - B4 &bull; QUEZON PROVINCE</div>
    </div>
</footer>

<script>
function toggleMobileMenu() {
    const drawer = document.getElementById('mobileNavDrawer');
    if (drawer) {
        drawer.classList.toggle('open');
    }
}
function closeMobileMenu() {
    const drawer = document.getElementById('mobileNavDrawer');
    if (drawer) {
        drawer.classList.remove('open');
    }
}
document.addEventListener('click', function(e) {
    const drawer = document.getElementById('mobileNavDrawer');
    const btn = document.getElementById('mobileMenuBtn');
    if (drawer && drawer.classList.contains('open')) {
        if (!drawer.contains(e.target) && !btn.contains(e.target)) {
            drawer.classList.remove('open');
        }
    }
});
</script>

