<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

$pageTitle = 'UAP Quezon Chapter | United Architects of the Philippines';

// Handle contact form submission
$contactSuccess = '';
$contactError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_inquiry') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Website Inquiry');
    $msg = trim($_POST['message'] ?? '');

    if (!$name || !$msg) {
        $contactError = 'Please provide your name and inquiry message.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_inquiries (name, email, phone, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, 'unread', NOW())");
            $stmt->execute([$name, $email, $phone, $subject, $msg]);
            $contactSuccess = 'Thank you for reaching out! Your message has been routed to the UAP Quezon Chapter secretariat.';
        } catch (Throwable $e) {
            $contactError = 'Unable to send message at this time. Please try again.';
        }
    }
}

// Fetch website directory members from database
$members = [];
try {
    $publishedMembers = $pdo->query("SELECT * FROM website_members WHERE is_published = 1 AND deleted_at IS NULL ORDER BY name ASC")->fetchAll();
    if (!empty($publishedMembers)) {
        foreach ($publishedMembers as $wm) {
            $members[] = [
                'id' => $wm['id'],
                'name' => $wm['name'],
                'role' => $wm['role_title'] ?: 'Architect',
                'specialty' => $wm['specialty'] ?: 'Professional Architect',
                'prc' => $wm['id_number'] ?: ($wm['prc_number'] ?: '—'),
                'location' => $wm['location'] ?: 'Quezon Province',
                'company' => $wm['company_name'] ?: '',
                'achievements' => $wm['achievements'] ?: '',
                'awards' => $wm['awards'] ?: '',
                'photo_path' => $wm['photo_path'] ?? ''
            ];
        }
    }
} catch (Exception $e) {}

// Fetch sponsors from database
$sponsors = [];
try {
    $sponsors = $pdo->query("SELECT * FROM sponsors WHERE is_active = 1 AND deleted_at IS NULL ORDER BY display_order ASC LIMIT 8")->fetchAll();
} catch (Exception $e) {}

// Fetch about us and contact details from database
$aboutUsText = get_site_setting(
    $pdo, 
    'about_us', 
    'The United Architects of the Philippines (UAP) Quezon Chapter unites registered and licensed architects across Quezon Province. We stand committed to architectural excellence, environmental stewardship of Mt. Banahaw and the Quezon coastline, heritage preservation, and sustainable community development.'
);
$contactEmail = get_site_setting($pdo, 'contact_email', 'quezon@united-architects.ph');
$contactPhone = get_site_setting($pdo, 'contact_phone', '+63 917 555 UAPQ');
$contactAddress = get_site_setting($pdo, 'contact_address', 'Lucena City, Quezon Province, Philippines');

// Fetch news from database
$newsItems = [];
try {
    $newsItems = $pdo->query("SELECT * FROM news_announcements WHERE is_published = 1 AND deleted_at IS NULL ORDER BY display_order ASC LIMIT 4")->fetchAll();
} catch (Exception $e) {}

// Fetch milestones
$milestones = [];
try {
    $milestones = $pdo->query("SELECT * FROM chapter_milestones WHERE deleted_at IS NULL ORDER BY display_order ASC")->fetchAll();
} catch (Exception $e) {}

$logoSrc = BASE_URL . '/public/logo.jpg';
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
    <style>
        :root {
            /* UAP Quezon Official Palette - Derived from Logo */
            --quezon-gold: #f59e0b;
            --quezon-amber: #e58a13;
            --quezon-amber-dark: #b45309;
            --quezon-green: #15803d;
            --quezon-green-light: #22c55e;
            --quezon-green-dark: #14532d;
            --quezon-charcoal: #1e293b;
            --quezon-dark: #0b1120;
            --quezon-card: #131d33;
            --quezon-card-inner: #192542;
            --quezon-border: rgba(255, 255, 255, 0.08);
            --quezon-border-gold: rgba(229, 138, 19, 0.35);
            --quezon-border-green: rgba(21, 128, 61, 0.4);
            --gradient-brand: linear-gradient(135deg, #e58a13 0%, #15803d 100%);
            --gradient-gold: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-green: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
            --font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --font-heading: 'Space Grotesk', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--quezon-dark);
            color: var(--text-light);
            font-family: var(--font-family);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a { color: inherit; text-decoration: none; }

        /* HEADER & NAVIGATION */
        header {
            background: rgba(11, 17, 32, 0.92);
            backdrop-filter: blur(12px);
            border-bottom: 2px solid;
            border-image: var(--gradient-brand) 1;
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
        }

        .header-container {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.9rem 2rem;
            gap: 1.5rem;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-logo img {
            height: 48px;
            width: auto;
            border-radius: 6px;
            background: #ffffff;
            padding: 2px;
        }

        .brand-title {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #ffffff;
            line-height: 1.1;
        }

        .brand-title span.gold { color: var(--quezon-gold); }
        .brand-title span.green { color: var(--quezon-green-light); }
        .brand-sub { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.12em; color: var(--text-muted); font-weight: 600; display: block; }

        nav ul {
            display: flex;
            align-items: center;
            gap: 1.8rem;
            list-style: none;
        }

        nav a {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #cbd5e1;
            transition: all 0.2s ease;
            position: relative;
        }

        nav a:hover { color: var(--quezon-gold); }

        .btn-portal {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.65rem 1.3rem;
            background: var(--gradient-brand);
            color: #ffffff;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 4px 14px rgba(229, 138, 19, 0.3);
            transition: all 0.2s ease;
        }

        .btn-portal:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(21, 128, 61, 0.4);
        }

        /* HERO SECTION */
        .hero {
            position: relative;
            padding: 6rem 2rem 5rem;
            background: radial-gradient(circle at 50% 20%, rgba(229, 138, 19, 0.12) 0%, rgba(21, 128, 61, 0.08) 50%, var(--quezon-dark) 80%);
            border-bottom: 1px solid var(--quezon-border);
            text-align: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(229, 138, 19, 0.12);
            border: 1px solid var(--quezon-border-gold);
            border-radius: 9999px;
            color: var(--quezon-gold);
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1.5rem;
        }

        .hero h1 {
            font-size: clamp(2.2rem, 5vw, 3.8rem);
            font-weight: 800;
            line-height: 1.15;
            max-width: 950px;
            margin: 0 auto 1.2rem;
            letter-spacing: -0.02em;
        }

        .hero h1 .highlight-gold {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero h1 .highlight-green {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p.lead {
            font-size: 1.15rem;
            color: var(--text-muted);
            max-width: 720px;
            margin: 0 auto 2.5rem;
            line-height: 1.6;
        }

        .hero-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn-primary-quezon {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.9rem 2.2rem;
            background: var(--gradient-brand);
            color: #ffffff;
            font-weight: 800;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(229, 138, 19, 0.35);
            transition: all 0.2s ease;
        }

        .btn-primary-quezon:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(21, 128, 61, 0.45);
        }

        .btn-outline-quezon {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.9rem 2.2rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--quezon-border);
            color: var(--text-light);
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .btn-outline-quezon:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--quezon-green-light);
            color: var(--quezon-green-light);
        }

        /* STATS BAR */
        .stats-bar {
            max-width: 1100px;
            margin: 3.5rem auto 0;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            padding: 1.8rem 2rem;
            background: rgba(19, 29, 51, 0.65);
            backdrop-filter: blur(10px);
            border: 1px solid var(--quezon-border);
            border-radius: 16px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* MAIN CONTENT LAYOUT */
        .page-container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2.4fr 1fr;
            gap: 2.5rem;
            align-items: start;
        }

        /* SECTIONS */
        .section-box {
            background: var(--quezon-card);
            border: 1px solid var(--quezon-border);
            border-radius: 18px;
            padding: 2.2rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            position: relative;
        }

        .section-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 2rem;
            right: 2rem;
            height: 2px;
            background: var(--gradient-brand);
            border-radius: 2px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.8rem;
        }

        .section-header h2 {
            font-size: 1.55rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 span {
            color: var(--quezon-gold);
        }

        /* DIRECTORY TABLE */
        .directory-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .directory-table th {
            text-align: left;
            padding: 12px 14px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--quezon-border);
            background: var(--quezon-card-inner);
        }

        .directory-table td {
            padding: 14px;
            border-bottom: 1px solid var(--quezon-border);
            vertical-align: middle;
        }

        .directory-table tr:hover td {
            background: rgba(229, 138, 19, 0.04);
        }

        .badge-prc {
            font-family: monospace;
            padding: 3px 8px;
            background: rgba(21, 128, 61, 0.15);
            color: var(--quezon-green-light);
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        /* NEWS CARDS */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .news-card {
            background: var(--quezon-card-inner);
            border: 1px solid var(--quezon-border);
            border-radius: 14px;
            padding: 1.5rem;
            transition: all 0.2s ease;
        }

        .news-card:hover {
            border-color: var(--quezon-border-gold);
            transform: translateY(-3px);
        }

        .news-date {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--quezon-gold);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 8px;
        }

        .news-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #ffffff;
            line-height: 1.35;
        }

        .news-card p {
            font-size: 0.88rem;
            color: var(--text-muted);
            line-height: 1.55;
        }

        /* MILESTONES TIMELINE */
        .milestone-timeline {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .milestone-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 14px;
            background: var(--quezon-card-inner);
            border-radius: 12px;
            border-left: 3px solid var(--quezon-green-light);
        }

        .milestone-year {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--quezon-gold);
            min-width: 60px;
        }

        .milestone-item h4 {
            font-size: 0.95rem;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .milestone-item p {
            font-size: 0.84rem;
            color: var(--text-muted);
            margin: 0;
        }

        /* SIDEBAR SPONSORS & CONTACT */
        .sidebar-box {
            background: var(--quezon-card);
            border: 1px solid var(--quezon-border);
            border-radius: 18px;
            padding: 1.8rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }

        .sidebar-box h3 {
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sponsor-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .sponsor-card {
            background: var(--quezon-card-inner);
            border: 1px solid var(--quezon-border);
            border-radius: 10px;
            padding: 14px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .sponsor-card:hover {
            border-color: var(--quezon-border-green);
        }

        .sponsor-tier-badge {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 6px;
        }

        .tier-platinum { background: rgba(229, 138, 19, 0.2); color: #fbbf24; border: 1px solid rgba(229,138,19,0.3); }
        .tier-gold { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .tier-silver { background: rgba(148, 163, 184, 0.15); color: #cbd5e1; }

        /* CONTACT FORM */
        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            background: var(--quezon-card-inner);
            border: 1px solid var(--quezon-border);
            border-radius: 8px;
            color: #ffffff;
            font-family: inherit;
            font-size: 0.88rem;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--quezon-gold);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 90px;
        }

        .btn-submit-inquiry {
            width: 100%;
            padding: 12px;
            background: var(--gradient-brand);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.88rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-submit-inquiry:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        /* FOOTER */
        footer {
            background: #080d19;
            border-top: 1px solid var(--quezon-border);
            padding: 4rem 2rem 2rem;
        }

        .footer-container {
            max-width: 1300px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1.25fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .footer-brand img {
            height: 44px;
            background: #ffffff;
            padding: 2px;
            border-radius: 6px;
        }

        .footer-col h4 {
            font-size: 0.95rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #ffffff;
            margin-bottom: 16px;
        }

        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-col a {
            color: var(--text-muted);
            font-size: 0.88rem;
            transition: color 0.2s ease;
        }

        .footer-col a:hover {
            color: var(--quezon-gold);
        }

        .copyright {
            max-width: 1300px;
            margin: 0 auto;
            border-top: 1px solid var(--quezon-border);
            padding-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        @media (max-width: 960px) {
            .content-grid { grid-template-columns: 1fr; }
            .stats-bar { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
            .footer-container { grid-template-columns: 1fr; }
            header nav { display: none; }
        }
    </style>
</head>
<body>

    <!-- STICKY NAVBAR -->
    <header>
        <div class="header-container">
            <div class="brand-logo">
                <img src="<?php echo $logoSrc; ?>" alt="UAP Quezon Chapter Logo">
                <div>
                    <div class="brand-title">
                        <span class="gold">UAP</span> <span class="green">QUEZON</span>
                    </div>
                    <span class="brand-sub">United Architects of the Philippines</span>
                </div>
            </div>

            <nav>
                <ul>
                    <li><a href="#about">About</a></li>
                    <li><a href="#directory">Architects</a></li>
                    <li><a href="#news">News &amp; Events</a></li>
                    <li><a href="#milestones">Milestones</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>

            <div>
                <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn-portal">
                    <?php echo icon('shield_check', '', 14); ?>
                    <span>Portal Login</span>
                </a>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-badge">
            <?php echo icon('landmark', '', 14); ?> Area B • Southern Luzon Region
        </div>

        <h1>
            Architectural Excellence,<br>
            <span class="highlight-gold">Mt. Banahaw Ecology</span> &amp; <span class="highlight-green">Quezon Heritage</span>
        </h1>

        <p class="lead">
            The official digital hub of the United Architects of the Philippines - Quezon Chapter. Uniting registered architects across Lucena City, Tayabas, Sariaya, and the four legislative districts of Quezon Province.
        </p>

        <div class="hero-buttons">
            <a href="#directory" class="btn-primary-quezon">
                <?php echo icon('members', '', 16); ?>
                <span>Browse Chapter Directory</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn-outline-quezon">
                <?php echo icon('wallet', '', 16); ?>
                <span>Pay Dues &amp; Access Receipts</span>
            </a>
        </div>

        <!-- STATS -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-value" style="color: var(--quezon-gold);"><?php echo max(120, count($members) * 15); ?>+</div>
                <div class="stat-label">Registered Architects</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color: var(--quezon-green-light);">42+</div>
                <div class="stat-label">Years of Heritage</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color: #fbbf24;">4</div>
                <div class="stat-label">Quezon Districts Served</div>
            </div>
        </div>
    </section>

    <!-- MAIN BODY -->
    <div class="page-container">
        <div class="content-grid">
            <!-- MAIN COLUMN -->
            <main>
                <!-- DIRECTORY SECTION -->
                <section class="section-box" id="directory">
                    <div class="section-header">
                        <h2><?php echo icon('members', '', 20); ?> Chapter <span>Architect Directory</span></h2>
                        <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn-outline-quezon" style="font-size: 0.78rem; padding: 6px 14px;">
                            Join Chapter &rarr;
                        </a>
                    </div>

                    <?php if (empty($members)): ?>
                        <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: rgba(229,138,19,0.12); color: var(--quezon-gold); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                                <?php echo icon('landmark', '', 26); ?>
                            </div>
                            <h4 style="color: #ffffff; margin-bottom: 4px;">Directory Profiles Updating</h4>
                            <p style="font-size: 0.88rem;">Quezon Chapter architects are updating their digital profiles and good standing records.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="directory-table">
                                <thead>
                                    <tr>
                                        <th>Architect</th>
                                        <th>Role / Practice</th>
                                        <th>Specialty</th>
                                        <th>PRC Reg.</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $m): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 700; color: #ffffff;"><?php echo htmlspecialchars($m['name']); ?></div>
                                                <?php if ($m['company']): ?>
                                                    <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo htmlspecialchars($m['company']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color: var(--quezon-gold); font-weight: 600;"><?php echo htmlspecialchars($m['role']); ?></td>
                                            <td style="color: var(--text-muted);"><?php echo htmlspecialchars($m['specialty']); ?></td>
                                            <td><span class="badge-prc"><?php echo htmlspecialchars($m['prc']); ?></span></td>
                                            <td style="color: var(--text-muted);"><?php echo htmlspecialchars($m['location']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- ABOUT US SECTION -->
                <section class="section-box" id="about">
                    <div class="section-header">
                        <h2><?php echo icon('landmark', '', 20); ?> About <span>UAP Quezon</span></h2>
                    </div>
                    <div style="font-size: 1.05rem; line-height: 1.7; color: #cbd5e1;">
                        <p><?php echo nl2br(htmlspecialchars($aboutUsText)); ?></p>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 2rem;">
                        <div style="background: var(--quezon-card-inner); padding: 1.2rem; border-radius: 12px; border: 1px solid var(--quezon-border);">
                            <div style="color: var(--quezon-gold); margin-bottom: 8px;"><?php echo icon('building', '', 22); ?></div>
                            <h4 style="color: #ffffff; margin-bottom: 4px; font-size: 0.95rem;">Heritage Advocacy</h4>
                            <p style="font-size: 0.82rem; color: var(--text-muted);">Protecting ancestral stone and wooden houses across Lucena, Sariaya, and Tayabas.</p>
                        </div>
                        <div style="background: var(--quezon-card-inner); padding: 1.2rem; border-radius: 12px; border: 1px solid var(--quezon-border);">
                            <div style="color: var(--quezon-green-light); margin-bottom: 8px;"><?php echo icon('sparkles', '', 22); ?></div>
                            <h4 style="color: #ffffff; margin-bottom: 4px; font-size: 0.95rem;">Ecological Design</h4>
                            <p style="font-size: 0.82rem; color: var(--text-muted);">Climate-resilient construction celebrating Mt. Banahaw watershed topography.</p>
                        </div>
                        <div style="background: var(--quezon-card-inner); padding: 1.2rem; border-radius: 12px; border: 1px solid var(--quezon-border);">
                            <div style="color: #60a5fa; margin-bottom: 8px;"><?php echo icon('award', '', 22); ?></div>
                            <h4 style="color: #ffffff; margin-bottom: 4px; font-size: 0.95rem;">Professional Pride</h4>
                            <p style="font-size: 0.82rem; color: var(--text-muted);">Strict enforcement of RA 9266 Architecture Act in Quezon local government units.</p>
                        </div>
                    </div>
                </section>

                <!-- NEWS & EVENTS SECTION -->
                <section class="section-box" id="news">
                    <div class="section-header">
                        <h2><?php echo icon('newspaper', '', 20); ?> News &amp; <span>Announcements</span></h2>
                    </div>

                    <div class="news-grid">
                        <?php foreach ($newsItems as $news): ?>
                            <article class="news-card">
                                <span class="news-date"><?php echo htmlspecialchars($news['date_display'] ?: date('M d, Y', strtotime($news['created_at']))); ?></span>
                                <h3><?php echo htmlspecialchars($news['title']); ?></h3>
                                <p><?php echo htmlspecialchars($news['summary']); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- CHAPTER MILESTONES -->
                <section class="section-box" id="milestones">
                    <div class="section-header">
                        <h2><?php echo icon('award', '', 20); ?> Chapter <span>Milestones</span></h2>
                    </div>

                    <div class="milestone-timeline">
                        <?php foreach ($milestones as $ms): ?>
                            <div class="milestone-item">
                                <div class="milestone-year"><?php echo htmlspecialchars($ms['year']); ?></div>
                                <div>
                                    <h4><?php echo htmlspecialchars($ms['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($ms['description']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </main>

            <!-- SIDEBAR -->
            <aside>
                <!-- OFFICIAL SPONSORS -->
                <div class="sidebar-box">
                    <h3><?php echo icon('handshake', '', 18); ?> Official Partners</h3>
                    <?php if (empty($sponsors)): ?>
                        <p style="color: var(--text-muted); font-size: 0.85rem;">Partner showcase loading...</p>
                    <?php else: ?>
                        <div class="sponsor-grid">
                            <?php foreach ($sponsors as $sp): ?>
                                <div class="sponsor-card">
                                    <span class="sponsor-tier-badge tier-<?php echo htmlspecialchars($sp['tier']); ?>">
                                        <?php echo htmlspecialchars($sp['tier']); ?> Partner
                                    </span>
                                    <div style="font-weight: 700; color: #ffffff; font-size: 0.95rem; margin-top: 4px;">
                                        <?php echo htmlspecialchars($sp['name']); ?>
                                    </div>
                                    <?php if ($sp['description']): ?>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">
                                            <?php echo htmlspecialchars($sp['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- CONTACT SECRETARIAT -->
                <div class="sidebar-box" id="contact">
                    <h3><?php echo icon('mail', '', 18); ?> Contact Secretariat</h3>

                    <?php if ($contactSuccess): ?>
                        <div style="background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 14px;">
                            <?php echo htmlspecialchars($contactSuccess); ?>
                        </div>
                    <?php elseif ($contactError): ?>
                        <div style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 14px;">
                            <?php echo htmlspecialchars($contactError); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="submit_inquiry">

                        <div class="form-group">
                            <label>Your Name *</label>
                            <input type="text" name="name" required class="form-control" placeholder="Ar. Juan Dela Cruz">
                        </div>

                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="architect@example.com">
                        </div>

                        <div class="form-group">
                            <label>Phone / Mobile</label>
                            <input type="text" name="phone" class="form-control" placeholder="0917 123 4567">
                        </div>

                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="Membership / Chapter Inquiry">
                        </div>

                        <div class="form-group">
                            <label>Message *</label>
                            <textarea name="message" required class="form-control" placeholder="How can the chapter assist you?"></textarea>
                        </div>

                        <button type="submit" class="btn-submit-inquiry">
                            Send Message &rarr;
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div class="footer-container">
            <div>
                <div class="footer-brand">
                    <img src="<?php echo $logoSrc; ?>" alt="UAP Quezon Logo">
                    <div>
                        <div style="font-weight: 800; font-size: 1.1rem; color: #ffffff;">UAP QUEZON CHAPTER</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em;">United Architects of the Philippines</div>
                    </div>
                </div>
                <p style="font-size: 0.88rem; color: var(--text-muted); max-width: 440px; line-height: 1.6;">
                    The integrated and accredited professional organization of architects (IAPOA) representing registered architects of Quezon Province.
                </p>
            </div>

            <div class="footer-col">
                <h4>Chapter Links</h4>
                <ul>
                    <li><a href="#about">About UAP Quezon</a></li>
                    <li><a href="#directory">Chapter Architects</a></li>
                    <li><a href="#news">News &amp; Events</a></li>
                    <li><a href="#milestones">Timeline &amp; Milestones</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/auth/login.php">Member Portal &amp; Dues</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Contact UAP-QC</h4>
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
                        <span>Mon &ndash; Fri &bull; 9:00 AM &ndash; 5:00 PM</span>
                    </li>
                    <li style="margin-top: 6px;">
                        <a href="#contact" style="display: inline-flex; align-items: center; gap: 6px; color: var(--quezon-green-light); font-weight: 700; font-size: 0.85rem; text-decoration: none;">
                            <span>Message Chapter Secretariat</span>
                            <?php echo icon('arrow_right', '', 13); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="copyright">
            <div>
                &copy; <?php echo date('Y'); ?> United Architects of the Philippines - Quezon Chapter. All rights reserved.
                <div style="margin-top: 6px; font-size: 0.85rem; color: var(--text-muted);">
                    Developed by <a href="https://drewportfoliio.vercel.app/" target="_blank" rel="noopener noreferrer" style="color: var(--quezon-gold); font-weight: 700; text-decoration: underline; text-underline-offset: 3px;">Drew Macaraig</a>
                </div>
            </div>
            <div style="color: var(--quezon-green-light); font-weight: 600;">Area B • Quezon Province</div>
        </div>
    </footer>
</body>
</html>
