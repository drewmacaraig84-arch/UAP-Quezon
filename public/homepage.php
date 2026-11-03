<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

$pageTitle = 'UAP Quezon Province Chapter | United Architects of the Philippines';

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
            $contactSuccess = 'Thank you for reaching out! Your message has been routed to the UAP Quezon Province Chapter secretariat.';
        } catch (Throwable $e) {
            $contactError = 'Unable to send message at this time. Please try again.';
        }
    }
}

// Fetch website directory members from database
$members = [];
try {
    $publishedMembers = $pdo->query("
        SELECT wm.* 
        FROM website_members wm
        JOIN users u ON wm.user_id = u.id
        WHERE wm.is_published = 1 
          AND wm.deleted_at IS NULL 
          AND u.deleted_at IS NULL 
          AND u.status = 'approved' 
        ORDER BY wm.name ASC
    ")->fetchAll();
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
    'The United Architects of the Philippines (UAP) Quezon Province Chapter unites registered and licensed architects across Quezon Province. We stand committed to architectural excellence, environmental stewardship of Mt. Banahaw and the Quezon coastline, heritage preservation, and sustainable community development.'
);
$contactEmail = get_site_setting($pdo, 'contact_email', 'uap.qpc@gmail.com');
$contactPhone = get_site_setting($pdo, 'contact_phone', '09399125237 / 09175939781');
$contactAddress = get_site_setting($pdo, 'contact_address', 'Lucena City, Quezon Province, Philippines');
$heroBadge = get_site_setting($pdo, 'hero_badge', 'DISTRICT - B4 • QUEZON PROVINCE');
if (in_array($heroBadge, ['District B4 • Quezon Province', 'Area B • Quezon Province', 'Area B • Southern Luzon Region', 'District B4'], true)) { $heroBadge = 'DISTRICT - B4 • QUEZON PROVINCE'; }
$heroTitle = get_site_setting($pdo, 'hero_title', '“TAGUYOD”');
$heroTagline = get_site_setting($pdo, 'hero_tagline', 'MOVERS OF PROGRESS 2.0');

// Fetch news from database
$newsItems = [];
try {
    $newsItems = $pdo->query("SELECT * FROM news_announcements WHERE target_audience IN ('public', 'all') AND is_published = 1 AND deleted_at IS NULL ORDER BY display_order ASC LIMIT 4")->fetchAll();
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

        *, *:before, *:after { margin: 0; padding: 0; box-sizing: border-box; }

        html {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        body {
            background-color: var(--quezon-dark);
            color: var(--text-light);
            font-family: var(--font-family);
            line-height: 1.6;
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
            position: relative;
            box-sizing: border-box;
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
            max-width: 100%;
            margin: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 2rem;
            gap: 1.5rem;
            box-sizing: border-box;
        }

        .brand-group {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
        }

        .header-badges {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-left: 14px;
            border-left: 1px solid rgba(255, 255, 255, 0.18);
        }

        .header-badge-box {
            width: 44px;
            height: 44px;
            border-radius: 6px;
            background: #ffffff;
            padding: 3px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid rgba(229, 138, 19, 0.4);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .header-badge-box:hover {
            transform: translateY(-2px);
            border-color: var(--quezon-gold);
        }

        .header-badge-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
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
            width: 100%;
            max-width: 100vw;
            box-sizing: border-box;
            overflow-x: hidden;
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

        .hero-theme-showcase {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0.6rem auto 1rem;
        }

        .hero-taguyod-banner {
            max-width: 680px;
            width: 92%;
            height: auto;
            border-radius: 14px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.5), 0 0 25px rgba(229, 138, 19, 0.25);
            border: 1px solid rgba(229, 138, 19, 0.4);
            display: block;
        }

        .hero-taguyod-sub {
            font-size: clamp(0.95rem, 2.5vw, 1.25rem);
            font-weight: 600;
            color: #f1f5f9;
            margin: 0.5rem auto 0;
            line-height: 1.5;
            max-width: 680px;
        }

        .hero-taguyod-sub small {
            display: block;
            font-size: 0.88em;
            color: var(--quezon-gold);
            font-weight: 500;
            margin-top: 2px;
        }

        .hero-accent-lines {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            margin: 1.1rem auto 1.3rem;
            max-width: 380px;
            width: 88%;
        }

        .hero-accent-lines .accent-line {
            display: block;
            border-radius: 9999px;
        }

        .hero-accent-lines .line-1 {
            width: 100%;
            height: 2.5px;
            background: linear-gradient(90deg, transparent, rgba(229, 138, 19, 0.95), transparent);
        }

        .hero-accent-lines .line-2 {
            width: 72%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #fbbf24, transparent);
        }

        .hero-accent-lines .line-3 {
            width: 44%;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(34, 197, 94, 0.85), transparent);
        }

        .hero-movers-showcase {
            margin: 0.8rem auto 1.8rem;
            display: flex;
            justify-content: center;
        }

        .hero-movers-banner {
            max-width: 500px;
            width: 86%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            display: block;
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
            width: 100%;
            margin: 3.5rem auto 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1.5rem;
            padding: 1.8rem 2rem;
            background: rgba(19, 29, 51, 0.65);
            backdrop-filter: blur(10px);
            border: 1px solid var(--quezon-border);
            border-radius: 16px;
            box-sizing: border-box;
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
            width: 100%;
            margin: 0 auto;
            padding: 4rem 2rem;
            box-sizing: border-box;
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 2.4fr) minmax(0, 1fr);
            gap: 2.5rem;
            align-items: start;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        main, aside {
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
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
            width: 100%;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
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
        .directory-table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            border: 1px solid var(--quezon-border);
            margin-top: 10px;
        }

        .directory-table {
            width: 100%;
            min-width: 580px;
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
            grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr));
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
            width: 100%;
            max-width: 100vw;
            box-sizing: border-box;
            overflow-x: hidden;
        }

        .footer-container {
            max-width: 1300px;
            width: 100%;
            margin: 0 auto;
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1.25fr);
            gap: 3rem;
            margin-bottom: 3rem;
            box-sizing: border-box;
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

        .about-features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 2rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mobile-menu-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 9px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--quezon-border);
            color: #ffffff;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0;
        }

        .mobile-menu-btn:hover, .mobile-menu-btn:active {
            background: rgba(229, 138, 19, 0.15);
            border-color: var(--quezon-gold);
            color: var(--quezon-gold);
        }

        .mobile-nav-drawer {
            display: none;
            background: #090e1a;
            border-bottom: 2px solid var(--quezon-gold);
            padding: 1rem 1.25rem 1.5rem;
            box-shadow: 0 16px 35px rgba(0,0,0,0.6);
        }

        .mobile-nav-drawer.open {
            display: block;
            animation: mobileSlideDown 0.22s ease-out;
        }

        @keyframes mobileSlideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .mobile-nav-drawer ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .mobile-nav-drawer a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 0.92rem;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .mobile-nav-drawer a:hover, .mobile-nav-drawer a:active {
            color: var(--quezon-gold);
            border-color: var(--quezon-border-gold);
            background: rgba(229, 138, 19, 0.1);
        }

        .mobile-portal-item {
            margin-top: 6px;
            padding-top: 10px;
            border-top: 1px solid var(--quezon-border);
        }

        .btn-mobile-portal {
            background: var(--gradient-brand) !important;
            color: #ffffff !important;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(229, 138, 19, 0.35);
        }

        /* RESPONSIVE BREAKPOINTS (Tablets & Mobile Devices) */
        @media (max-width: 960px) {
            .header-badges { gap: 6px; padding-left: 10px; }
            .header-badge-box { width: 36px; height: 36px; }
            .content-grid { grid-template-columns: minmax(0, 1fr); }
            .stats-bar { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; padding: 1.5rem; }
            .footer-container { grid-template-columns: minmax(0, 1fr); gap: 2.2rem; }
            .desktop-nav { display: none; }
            .mobile-menu-btn { display: inline-flex; }
            .about-features-grid { grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr)); }
        }

        @media (max-width: 768px) {
            .header-container { padding: 0.8rem 1rem; }
            .hero { padding: 4rem 1rem 3rem; }
            .hero h1 { font-size: clamp(1.65rem, 6vw, 2.4rem); line-height: 1.2; }
            .hero p.lead { font-size: 0.95rem; margin-bottom: 1.8rem; }
            .stats-bar { grid-template-columns: minmax(0, 1fr); gap: 1rem; padding: 1.2rem; margin-top: 2rem; }
            .page-container { padding: 2rem 1rem; }
            .section-box { padding: 1.5rem 1.1rem; border-radius: 14px; margin-bottom: 1.8rem; }
            .section-box::before { left: 1rem; right: 1rem; }
            .sidebar-box { padding: 1.5rem 1.1rem; border-radius: 14px; margin-bottom: 1.8rem; }
            .copyright { flex-direction: column; align-items: flex-start; gap: 10px; text-align: left; }
            .milestone-item { padding: 12px; }
            footer { padding: 3rem 1rem 2rem; }
            .table-scroll-hint { display: flex !important; }
        }

        @media (max-width: 540px) {
            .header-container { padding: 0.65rem 0.85rem; gap: 0.5rem; }
            .header-badges { gap: 4px; padding-left: 6px; }
            .header-badge-box { width: 30px; height: 30px; padding: 2px; }
            .brand-logo { gap: 8px; }
            .brand-logo img { height: 36px; }
            .brand-title { font-size: 0.95rem; }
            .brand-sub { display: none; }
            .btn-portal { padding: 0.45rem 0.75rem; font-size: 0.75rem; gap: 4px; }
            .btn-portal span { display: none; }
            .btn-portal::after { content: 'Portal'; }
            .hero { padding: 3rem 0.85rem 2rem; }
            .hero h1 { font-size: 1.65rem; }
            .hero p.lead { font-size: 0.88rem; }
            .hero-badge { font-size: 0.7rem; padding: 4px 10px; }
            .page-container { padding: 1.5rem 0.75rem; }
            .section-box { padding: 1.2rem 0.85rem; border-radius: 12px; margin-bottom: 1.5rem; }
            .section-box::before { left: 0.85rem; right: 0.85rem; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 8px; }
            .section-header h2 { font-size: 1.2rem; }
            .about-features-grid { grid-template-columns: minmax(0, 1fr); }
            .news-grid { grid-template-columns: minmax(0, 1fr); }
            .news-card { padding: 1.1rem 0.85rem; }
            .sidebar-box { padding: 1.2rem 0.85rem; border-radius: 12px; }
            footer { padding: 2.5rem 0.85rem 1.8rem; }
            .form-control { font-size: 16px; } /* Prevents iOS auto-zoom on input focus */
            .stat-value { font-size: 1.8rem; }
        }

        @media (max-width: 380px) {
            .header-container { padding: 0.5rem 0.65rem; }
            .brand-logo img { height: 32px; }
            .brand-title { font-size: 0.85rem; }
            .mobile-menu-btn { width: 34px; height: 34px; }
            .btn-portal { padding: 0.4rem 0.6rem; font-size: 0.7rem; }
            .hero h1 { font-size: 1.45rem; }
            .stat-value { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

    <?php $activePage = 'home'; require __DIR__ . '/../includes/public_nav.php'; ?>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-badge">
            <?php echo icon('landmark', '', 14); ?> <?php echo htmlspecialchars($heroBadge); ?>
        </div>

        <!-- Official TAGUYOD Chapter Theme Banner -->
        <div class="hero-theme-showcase">
            <img src="<?php echo BASE_URL; ?>/public/images/theme_taguyod.jpg" alt="TAGUYOD - Paghuhubog ng Kahusayan, Pagpapalakas ng Samahan (Shaping Excellence, Strengthening Fellowship)" class="hero-taguyod-banner" onerror="this.style.display='none'; document.getElementById('heroTextTaguyod').style.display='block';">
            
            <div id="heroTextTaguyod" style="display: none;">
                <h1 class="hero-title-taguyod">
                    <span class="highlight-gold">TAGUYOD</span>
                </h1>
                <div class="hero-taguyod-sub">
                    Paghuhubog ng Kahusayan, Pagpapalakas ng Samahan<br>
                    <small>(Shaping Excellence, Strengthening Fellowship)</small>
                </div>
            </div>
        </div>

        <!-- Architectural Triple Accent Lines -->
        <div class="hero-accent-lines" aria-hidden="true">
            <span class="accent-line line-1"></span>
            <span class="accent-line line-2"></span>
            <span class="accent-line line-3"></span>
        </div>

        <!-- Official Movers of Progress 2.0 National Campaign Banner -->
        <div class="hero-movers-showcase">
            <img src="<?php echo BASE_URL; ?>/public/images/theme_movers_progress.jpg" alt="Movers of Progress 2.0 - Justice to the Profession, Visionary Initiatives, Member Empowerment" class="hero-movers-banner">
        </div>

        <p class="lead">
            The official digital hub of the United Architects of the Philippines - Quezon Province Chapter. Uniting registered architects across Lucena City, Tayabas, Sariaya, and the four legislative districts of Quezon Province.
        </p>

        <!-- STATS -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-value" style="color: var(--quezon-gold);">1977</div>
                <div class="stat-label">Chartered (1st in B4)</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color: var(--quezon-green-light);">27</div>
                <div class="stat-label">Chapter Presidents</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color: #60a5fa;">300+</div>
                <div class="stat-label">Registered Architects</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color: #fbbf24;">50th</div>
                <div class="stat-label">Golden Anniversary</div>
            </div>
        </div>
    </section>

    <!-- MAIN BODY -->
    <div class="page-container">
        <div class="content-grid">
            <!-- MAIN COLUMN -->
            <main>
                <!-- ABOUT US SECTION -->
                <section class="section-box" id="about">
                    <div class="section-header">
                        <h2><?php echo icon('landmark', '', 20); ?> Built Through Time &amp; <span>About UAP Quezon</span></h2>
                        <a href="<?php echo BASE_URL; ?>/public/about.php" class="btn-outline-quezon" style="font-size: 0.78rem; padding: 6px 14px;">
                            Full Story &rarr;
                        </a>
                    </div>
                    <div style="font-size: 1.02rem; line-height: 1.7; color: #cbd5e1; margin-bottom: 1.2rem;">
                        <p style="margin-bottom: 0.8rem;">
                            The <strong>United Architects of the Philippines – Quezon Province Chapter (UAP-QPC)</strong> holds a historic place within the national organization. Chartered on <strong>November 11, 1977</strong>, it was the first chapter established in District B4 and the 18th chapter registered in the Philippines.
                        </p>
                        <p style="margin-bottom: 0.8rem;">
                            Under the visionary leadership of Founding Chapter President <strong>Ar. Benecio N. Tolentino, FUAP</strong>, and guided across decades by <strong>twenty-seven (27) Chapter Presidents</strong>, the chapter has united over 300 licensed architects across Lucena City, Tayabas, Sariaya, and the four districts of Quezon.
                        </p>
                        <p>
                            As we approach our <strong>Golden 50th Anniversary</strong>, we reaffirm our commitment to empowering the profession through CPD masterclasses, signature showcases such as <em>Guhit Arkitektong Quezonian</em>, community outreach, and collaborative enforcement of Republic Act No. 9266 with local government units.
                        </p>
                    </div>

                    <div class="about-features-grid">
                        <div style="background: var(--quezon-card-inner); padding: 1.2rem; border-radius: 12px; border: 1px solid var(--quezon-border);">
                            <div style="color: var(--quezon-gold); margin-bottom: 8px;"><?php echo icon('building', '', 22); ?></div>
                            <h4 style="color: #ffffff; margin-bottom: 4px; font-size: 0.95rem;">Professional Standards &amp; RA 9266</h4>
                            <p style="font-size: 0.82rem; color: var(--text-muted);">Partnering with Quezon municipal building officials to enforce architectural practice laws.</p>
                        </div>
                        <div style="background: var(--quezon-card-inner); padding: 1.2rem; border-radius: 12px; border: 1px solid var(--quezon-border);">
                            <div style="color: var(--quezon-green-light); margin-bottom: 8px;"><?php echo icon('sparkles', '', 22); ?></div>
                            <h4 style="color: #ffffff; margin-bottom: 4px; font-size: 0.95rem;">Ecological &amp; Heritage Design</h4>
                            <p style="font-size: 0.82rem; color: var(--text-muted);">Preserving Quezon ancestral landmarks while designing climate-resilient architecture.</p>
                        </div>
                        <div style="background: var(--quezon-card-inner); padding: 1.2rem; border-radius: 12px; border: 1px solid var(--quezon-border);">
                            <div style="color: #60a5fa; margin-bottom: 8px;"><?php echo icon('award', '', 22); ?></div>
                            <h4 style="color: #ffffff; margin-bottom: 4px; font-size: 0.95rem;">Community Extension Services</h4>
                            <p style="font-size: 0.82rem; color: var(--text-muted);">Pro-bono civic planning, community outreach, and public school assistance.</p>
                        </div>
                    </div>
                </section>

                <!-- DIRECTORY SECTION -->
                <section class="section-box" id="directory">
                    <div class="section-header">
                        <h2><?php echo icon('members', '', 20); ?> Chapter <span>Architect Directory</span></h2>
                        <div style="display: flex; gap: 8px;">
                            <a href="<?php echo BASE_URL; ?>/public/architects.php" class="btn-outline-quezon" style="font-size: 0.78rem; padding: 6px 14px;">
                                Full Directory &rarr;
                            </a>
                            <a href="<?php echo BASE_URL; ?>/auth/register.php" class="btn-outline-quezon" style="font-size: 0.78rem; padding: 6px 14px;">
                                Join &rarr;
                            </a>
                        </div>
                    </div>

                    <?php if (empty($members)): ?>
                        <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: rgba(229,138,19,0.12); color: var(--quezon-gold); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                                <?php echo icon('landmark', '', 26); ?>
                            </div>
                            <h4 style="color: #ffffff; margin-bottom: 4px;">Directory Profiles Updating</h4>
                            <p style="font-size: 0.88rem;">Quezon Province Chapter architects are updating their digital profiles and good standing records.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-scroll-hint" style="display: none; align-items: center; justify-content: space-between; background: rgba(229,138,19,0.08); border: 1px dashed var(--quezon-border-gold); padding: 7px 12px; border-radius: 8px; font-size: 11.5px; color: var(--quezon-gold); margin-bottom: 8px;">
                            <span style="display: flex; align-items: center; gap: 6px;">
                                <?php echo icon('members', '', 14); ?> <span>Scroll table horizontally for full credentials</span>
                            </span>
                            <span style="font-weight: 700; letter-spacing: 2px;">&larr;&rarr;</span>
                        </div>
                        <div class="directory-table-wrap">
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

                <!-- NEWS & EVENTS SECTION -->
                <section class="section-box" id="news">
                    <div class="section-header">
                        <h2><?php echo icon('newspaper', '', 20); ?> News &amp; <span>Announcements</span></h2>
                        <a href="<?php echo BASE_URL; ?>/public/news.php" class="btn-outline-quezon" style="font-size: 0.78rem; padding: 6px 14px;">
                            All Events &rarr;
                        </a>
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
                        <a href="<?php echo BASE_URL; ?>/public/milestones.php" class="btn-outline-quezon" style="font-size: 0.78rem; padding: 6px 14px;">
                            Full Milestones &rarr;
                        </a>
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

    <?php require __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>
