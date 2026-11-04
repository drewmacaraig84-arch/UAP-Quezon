<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

$pageTitle = 'News, CPD Seminars & Events | UAP Quezon Province Chapter';
$activePage = 'news';

$newsItems = [];
try {
    $newsItems = $pdo->query("SELECT * FROM news_announcements WHERE (target_audience IN ('public', 'all') OR target_audience IS NULL OR target_audience = '') AND (is_published = 1 OR is_published IS NULL) AND deleted_at IS NULL ORDER BY display_order ASC, id DESC")->fetchAll();
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
        .news-full-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .news-article-card {
            background: var(--quezon-card);
            border: 1px solid var(--quezon-border);
            border-radius: 16px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            transition: transform 0.25s, border-color 0.25s, box-shadow 0.25s;
        }
        .news-article-card:hover {
            transform: translateY(-4px);
            border-color: var(--quezon-border-gold);
            box-shadow: 0 12px 28px rgba(0,0,0,0.45);
        }
        .news-badge {
            display: inline-block;
            background: rgba(229, 138, 19, 0.12);
            color: var(--quezon-gold);
            border: 1px solid var(--quezon-border-gold);
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
            align-self: flex-start;
        }
        .news-card-title {
            font-size: 1.25rem;
            color: #ffffff;
            font-weight: 700;
            line-height: 1.4;
            margin-bottom: 12px;
        }
        .news-card-text {
            color: #cbd5e1;
            font-size: 0.92rem;
            line-height: 1.7;
            flex-grow: 1;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .news-full-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../includes/public_nav.php'; ?>

    <!-- HERO -->
    <section class="page-hero">
        <div class="hero-badge">
            <?php echo icon('newspaper', '', 14); ?> CPD Seminars &bull; Assemblies &bull; Guhit Arkitektong Quezonian
        </div>
        <h1>News &amp; <span class="highlight-gold">Chapter Events</span></h1>
        <p class="lead" style="max-width: 680px; margin: 16px auto 0;">
            Stay informed with the latest continuing professional development (CPD) accredited workshops, chapter assemblies, and public architecture exhibits.
        </p>
    </section>

    <div class="page-container" style="max-width: 1200px;">
        <!-- HIGHLIGHT: GUHIT ARKITEKTONG QUEZONIAN -->
        <section class="section-box" style="background: linear-gradient(135deg, rgba(25, 37, 66, 0.85) 0%, rgba(11, 17, 32, 0.95) 100%); border: 1px solid var(--quezon-border-gold); padding: 2.2rem; margin-bottom: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
                <div style="max-width: 750px;">
                    <span class="news-badge">Signature Annual Exhibition</span>
                    <h2 style="font-size: 1.6rem; color: #ffffff; font-family: var(--font-heading); margin-bottom: 10px;">
                        Guhit Arkitektong Quezonian
                    </h2>
                    <p style="font-size: 0.96rem; color: #cbd5e1; line-height: 1.7;">
                        A cornerstone chapter initiative that serves as a premier showcase for Quezonian architects to exhibit architectural renderings, scale models, and regional heritage studies. It stimulates creative dialogue with local communities, encouraging appreciation of sustainable and resilient design.
                    </p>
                </div>
                <div style="text-align: right;">
                    <a href="<?php echo BASE_URL; ?>/public/contact.php" class="btn-portal" style="font-size: 0.85rem;">
                        Inquire About Sponsorship &rarr;
                    </a>
                </div>
            </div>
        </section>

        <!-- ALL ANNOUNCEMENTS -->
        <div class="section-header" style="margin-bottom: 1rem;">
            <h2><?php echo icon('newspaper', '', 22); ?> Latest <span>Announcements</span></h2>
        </div>

        <?php if (empty($newsItems)): ?>
            <div class="section-box" style="text-align: center; padding: 4rem 1rem;">
                <p style="color: var(--text-muted);">No announcements at this time. Check back soon for upcoming CPD events.</p>
            </div>
        <?php else: ?>
            <div class="news-full-grid">
                <?php foreach ($newsItems as $n): ?>
                    <article class="news-article-card">
                        <span class="news-badge">
                            <?php echo htmlspecialchars(!empty($n['date_posted']) ? date('F d, Y', strtotime($n['date_posted'])) : (!empty($n['date_display']) ? $n['date_display'] : date('F d, Y', strtotime($n['created_at'])))); ?>
                        </span>
                        <h3 class="news-card-title"><?php echo htmlspecialchars($n['title']); ?></h3>
                        <p class="news-card-text">
                            <?php echo nl2br(htmlspecialchars($n['summary'])); ?>
                        </p>
                        <div style="border-top: 1px solid var(--quezon-border); padding-top: 14px; font-size: 0.82rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center;">
                            <span><?php echo icon('shield_check', '', 14); ?> Official Chapter Release</span>
                            <span style="color: var(--quezon-gold); font-weight: 700;">UAP-QPC</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- CPD NOTICE BOX -->
        <div class="section-box" style="margin-top: 3rem; background: var(--quezon-card-inner); border: 1px solid var(--quezon-border);">
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <div style="color: var(--quezon-gold); flex-shrink: 0; margin-top: 2px;">
                    <?php echo icon('award', '', 26); ?>
                </div>
                <div>
                    <h4 style="color: #ffffff; font-size: 1.05rem; margin-bottom: 6px;">CPD Program Notice for Licensed Architects</h4>
                    <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6;">
                        In compliance with PRC Continuing Professional Development (CPD) requirements under RA 10912, certificates of attendance with applied credit units are issued to good-standing chapter members upon completion of accredited seminars and technical symposiums.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>

