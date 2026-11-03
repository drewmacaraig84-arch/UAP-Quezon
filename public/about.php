<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

$pageTitle = 'About Us | UAP Quezon Province Chapter';
$activePage = 'about';

$aboutUsText = get_site_setting(
    $pdo,
    'about_us',
    'The United Architects of the Philippines – Quezon Province Chapter (UAP-QPC) holds a meaningful place within national architectural history. Chartered on November 11, 1977, it was the first chapter established in District B4 and the 18th chapter registered in the Philippines.'
);
$contactEmail = get_site_setting($pdo, 'contact_email', 'quezon@united-architects.ph');
$contactPhone = get_site_setting($pdo, 'contact_phone', '+63 917 555 UAPQ');
$contactAddress = get_site_setting($pdo, 'contact_address', 'Lucena City, Quezon Province, Philippines');
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
        .history-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 2.5rem;
            align-items: center;
            margin-top: 2rem;
        }
        .highlight-quote {
            background: linear-gradient(135deg, rgba(229, 138, 19, 0.1) 0%, rgba(21, 128, 61, 0.08) 100%);
            border-left: 4px solid var(--quezon-gold);
            padding: 1.5rem;
            border-radius: 0 12px 12px 0;
            font-size: 1.05rem;
            color: #f1f5f9;
            margin: 1.5rem 0;
            font-style: italic;
        }
        .pillar-card {
            background: var(--quezon-card-inner);
            border: 1px solid var(--quezon-border);
            padding: 1.8rem;
            border-radius: 14px;
            transition: transform 0.2s, border-color 0.2s;
        }
        .pillar-card:hover {
            transform: translateY(-3px);
            border-color: var(--quezon-border-gold);
        }
        @media (max-width: 900px) {
            .history-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../includes/public_nav.php'; ?>

    <!-- HERO -->
    <section class="page-hero">
        <div class="hero-badge">
            <?php echo icon('landmark', '', 14); ?> Chartered November 11, 1977 • District B4
        </div>
        <h1>Built Through Time: <span class="highlight-gold">About UAP Quezon</span></h1>
        <p class="lead" style="max-width: 720px; margin: 16px auto 0;">
            The integrated and accredited professional organization of architects serving Lucena City, Tayabas, Sariaya, and the four legislative districts of Quezon Province.
        </p>
    </section>

    <div class="page-container" style="max-width: 1200px;">
        <!-- CHAPTER FOUNDING & HISTORY -->
        <section class="section-box" style="margin-bottom: 2.5rem;">
            <div class="section-header">
                <h2><?php echo icon('landmark', '', 22); ?> Built Through Time &bull; <span>Chapter History</span></h2>
                <a href="<?php echo BASE_URL; ?>/public/milestones.php" class="btn-outline-quezon" style="font-size: 0.8rem; padding: 6px 14px;">
                    View Historical Milestones &rarr;
                </a>
            </div>

            <div class="history-grid">
                <div>
                    <p style="font-size: 1.08rem; line-height: 1.8; color: #cbd5e1; margin-bottom: 1rem;">
                        The <strong>United Architects of the Philippines – Quezon Province Chapter (UAP-QPC)</strong> holds a profound place within the organization's national history. Chartered on <strong>November 11, 1977</strong>, it was the <em>first chapter established in District B4</em> and recognized as the <em>18th chapter to be registered nationwide</em>—marking its pivotal role in the professional growth of Southern Luzon.
                    </p>
                    <p style="font-size: 1.05rem; line-height: 1.8; color: #cbd5e1;">
                        Under the visionary leadership of its Founding Chapter President, <strong>Ar. Benecio N. Tolentino, FUAP</strong>, the chapter was established upon an unyielding foundation of integrity, ethical service, and commitment to the advancement of architecture.
                    </p>
                    <div class="highlight-quote">
                        &ldquo;From its inception, UAP Quezon has united architects across Quezon Province, cultivating a culture of collaboration, innovation, and professional distinction.&rdquo;
                    </div>
                </div>

                <div style="background: var(--quezon-card-inner); border: 1px solid var(--quezon-border-gold); padding: 2rem; border-radius: 16px; text-align: center;">
                    <div style="width: 120px; height: 120px; border-radius: 50%; background: #ffffff; padding: 4px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 24px rgba(229,138,19,0.3);">
                        <img src="<?php echo $logoSrc; ?>" alt="UAP Quezon" style="width: 90%; height: 90%; object-fit: contain;">
                    </div>
                    <h3 style="color: #ffffff; font-size: 1.2rem; margin-bottom: 4px;">UAP Quezon Province Chapter</h3>
                    <div style="font-size: 0.82rem; color: var(--quezon-gold); font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 16px;">
                        IAPOA Chapter 121 &bull; Area B
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; border-top: 1px solid var(--quezon-border); padding-top: 16px; text-align: left;">
                        <div>
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">Charter Date</span>
                            <strong style="color: #ffffff; font-size: 0.95rem;">Nov 11, 1977</strong>
                        </div>
                        <div>
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">Founding President</span>
                            <strong style="color: #ffffff; font-size: 0.95rem;">Ar. Benecio Tolentino, FUAP</strong>
                        </div>
                        <div>
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">Leadership Heritage</span>
                            <strong style="color: #ffffff; font-size: 0.95rem;">27 Presidents</strong>
                        </div>
                        <div>
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">Current Active Members</span>
                            <strong style="color: var(--quezon-green-light); font-size: 0.95rem;">300+ Architects</strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- A LEGACY OF EXCELLENCE -->
        <section class="section-box" style="margin-bottom: 2.5rem;">
            <div class="section-header">
                <h2><?php echo icon('award', '', 22); ?> A Legacy of <span>Excellence &amp; Leadership</span></h2>
            </div>
            <div style="font-size: 1.05rem; line-height: 1.8; color: #cbd5e1;">
                <p style="margin-bottom: 1.2rem;">
                    Through decades of dedication, <strong>twenty-seven (27) Chapter Presidents</strong> have guided the chapter's path—building a distinguished legacy of leadership as we approach our <strong>Golden 50th Anniversary</strong>.
                </p>
                <p style="margin-bottom: 1.2rem;">
                    Now comprising <strong>over 300 members</strong>, the chapter continues to support its architects in strengthening professional competencies and sharing technical expertise through CPD seminars, ethical practice workshops, and exhibits such as <em>Guhit Arkitektong Quezonian</em>—a signature annual event that serves as a platform for architects to proudly present their works, exchange ideas, and engage meaningfully with the community.
                </p>
                <p>
                    As the chapter approaches its 50th year, it reaffirms its dedication to empowering the profession, advancing architectural practice, and continuing its legacy of service and positive community impact.
                </p>
            </div>
        </section>

        <!-- OFFICIAL COMMEMORATIVE INFOGRAPHIC: BUILT THROUGH TIME -->
        <section class="section-box" style="margin-bottom: 2.5rem; text-align: center;">
            <div class="section-header" style="justify-content: center; text-align: center; margin-bottom: 1.2rem;">
                <h2><?php echo icon('award', '', 22); ?> Commemorative Heritage &bull; <span>Built Through Time</span></h2>
            </div>
            <p style="color: var(--text-muted); max-width: 720px; margin: 0 auto 1.5rem; font-size: 0.95rem; line-height: 1.6;">
                Official historical chronicle honoring the 27 Chapter Presidents who guided our chapter from Founding President Benecio N. Tolentino, FUAP, leading into our Golden 50th Anniversary.
            </p>
            <div style="max-width: 760px; margin: 0 auto; border-radius: 16px; overflow: hidden; border: 2px solid var(--quezon-border-gold); box-shadow: 0 16px 40px rgba(0,0,0,0.55); background: #0b1120;">
                <a href="<?php echo BASE_URL; ?>/public/images/uap_quezon_history_poster.jpg" target="_blank" title="Click to view full high-resolution commemorative poster">
                    <img src="<?php echo BASE_URL; ?>/public/images/uap_quezon_history_poster.jpg" alt="UAP Quezon Province Chapter - Built Through Time History Poster" style="width: 100%; height: auto; display: block; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.015)'" onmouseout="this.style.transform='scale(1)'">
                </a>
            </div>
            <div style="margin-top: 16px;">
                <a href="<?php echo BASE_URL; ?>/public/images/uap_quezon_history_poster.jpg" target="_blank" class="btn-outline-quezon" style="font-size: 0.85rem; padding: 8px 18px; display: inline-flex; align-items: center; gap: 6px;">
                    <?php echo icon('eye', '', 14); ?> Open Full-Resolution Commemorative Poster &rarr;
                </a>
            </div>
        </section>

        <!-- 4 PILLARS OF OUR PURPOSE -->
        <section class="section-box" style="margin-bottom: 2.5rem;">
            <div class="section-header">
                <h2><?php echo icon('sparkles', '', 22); ?> Our Four <span>Pillars of Excellence</span></h2>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                <div class="pillar-card">
                    <div style="color: var(--quezon-gold); margin-bottom: 12px;"><?php echo icon('shield_check', '', 28); ?></div>
                    <h3 style="color: #ffffff; font-size: 1.1rem; margin-bottom: 8px;">RA 9266 &amp; Ethical Practice</h3>
                    <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">
                        Strict enforcement of the Architecture Act of 2004, working closely with Quezon LGUs and building officials to protect the profession and ensure public safety.
                    </p>
                </div>

                <div class="pillar-card">
                    <div style="color: var(--quezon-green-light); margin-bottom: 12px;"><?php echo icon('sparkles', '', 28); ?></div>
                    <h3 style="color: #ffffff; font-size: 1.1rem; margin-bottom: 8px;">Banahaw Ecology &amp; Sustainability</h3>
                    <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">
                        Advocating green, climate-adaptive architecture designed specifically for the unique topography of Mount Banahaw and the coastal marine environments of Quezon.
                    </p>
                </div>

                <div class="pillar-card">
                    <div style="color: #60a5fa; margin-bottom: 12px;"><?php echo icon('building', '', 28); ?></div>
                    <h3 style="color: #ffffff; font-size: 1.1rem; margin-bottom: 8px;">Heritage Documentation</h3>
                    <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">
                        Leading preservation campaigns and architectural surveys of Spanish-era stone houses, neoclassical buildings, and cultural landmarks across Lucena, Tayabas, and Sariaya.
                    </p>
                </div>

                <div class="pillar-card">
                    <div style="color: #f472b6; margin-bottom: 12px;"><?php echo icon('handshake', '', 28); ?></div>
                    <h3 style="color: #ffffff; font-size: 1.1rem; margin-bottom: 8px;">Community Extension</h3>
                    <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">
                        Conducting pro-bono civic architectural assistance, school facility assessments, and community outreach programs in underserved municipalities across Quezon.
                    </p>
                </div>
            </div>
        </section>

        <!-- CTAS -->
        <div style="text-align: center; padding: 2rem 0;">
            <a href="<?php echo BASE_URL; ?>/public/architects.php" class="btn-portal" style="font-size: 0.95rem; padding: 12px 24px;">
                Explore Architects Directory &rarr;
            </a>
            <a href="<?php echo BASE_URL; ?>/public/contact.php" class="btn-outline-quezon" style="font-size: 0.95rem; padding: 12px 24px; margin-left: 12px;">
                Contact Secretariat
            </a>
        </div>
    </div>

    <?php require __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>

