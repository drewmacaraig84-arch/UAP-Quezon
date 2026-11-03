<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

$pageTitle = 'Chapter Milestones & Heritage | UAP Quezon Province Chapter';
$activePage = 'milestones';

$milestones = [];
try {
    $milestones = $pdo->query("SELECT * FROM chapter_milestones WHERE deleted_at IS NULL ORDER BY display_order ASC, year ASC")->fetchAll();
} catch (Exception $e) {}

if (empty($milestones)) {
    $milestones = [
        ['year' => '1977', 'title' => 'Chartering of UAP Quezon Province Chapter', 'description' => 'Chartered on November 11, 1977 as the first chapter in District B4 and the 18th chapter registered in the Philippines, founded under Benecio N. Tolentino, FUAP.'],
        ['year' => '1995', 'title' => 'Preserving Quezon Heritage & Culture', 'description' => 'Pioneered heritage documentation and preservation advocacy for ancestral stone houses and civic landmarks across Lucena City, Tayabas, and Sariaya.'],
        ['year' => '2004', 'title' => 'Advocacy for Republic Act No. 9266', 'description' => 'Led active collaboration with local municipal governments and building officials across Quezon Province for the full implementation of the Architecture Act.'],
        ['year' => '2018', 'title' => 'Guhit Arkitektong Quezonian', 'description' => 'Launched the chapter\'s signature annual design exhibition, CPD symposium, and platform uniting local architectural talent and creative community dialogue.'],
        ['year' => '2026', 'title' => 'Approaching Golden 50th Anniversary', 'description' => 'Comprising over 300 members and guided by 27 Chapter Presidents, the chapter launches its digital member portal and verified architect directory.']
    ];
}

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
        .milestone-timeline-full {
            position: relative;
            max-width: 900px;
            margin: 2.5rem auto 0;
            padding: 1rem 0;
        }
        .milestone-card-full {
            background: var(--quezon-card);
            border: 1px solid var(--quezon-border);
            border-radius: 14px;
            padding: 1.8rem;
            margin-bottom: 2rem;
            position: relative;
            display: flex;
            gap: 1.8rem;
            transition: transform 0.25s, border-color 0.25s;
        }
        .milestone-card-full:hover {
            transform: translateY(-3px);
            border-color: var(--quezon-border-gold);
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }
        .milestone-year-badge {
            font-family: var(--font-heading);
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--quezon-gold);
            background: rgba(229, 138, 19, 0.12);
            border: 1px solid var(--quezon-border-gold);
            padding: 10px 18px;
            border-radius: 12px;
            height: fit-content;
            flex-shrink: 0;
            text-align: center;
        }
        @media (max-width: 768px) {
            .page-hero { padding: 3.5rem 1rem 2.2rem; }
            .milestone-card-full {
                flex-direction: column;
                gap: 1rem;
                padding: 1.35rem 1rem;
            }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../includes/public_nav.php'; ?>

    <!-- HERO -->
    <section class="page-hero">
        <div class="hero-badge">
            <?php echo icon('clock', '', 14); ?> 1977 &ndash; Present &bull; Approaching Golden 50th Anniversary
        </div>
        <h1>A Legacy of Excellence: <span class="highlight-gold">Chapter Milestones</span></h1>
        <p class="lead" style="max-width: 720px; margin: 16px auto 0;">
            Twenty-seven (27) Chapter Presidents and nearly five decades of professional architecture leadership, municipal collaboration, and civic service across Quezon Province.
        </p>
    </section>

    <div class="page-container" style="max-width: 1100px;">
        <!-- STATS OVERVIEW -->
        <div class="section-box" style="margin-bottom: 2.5rem; background: linear-gradient(135deg, rgba(25, 37, 66, 0.8) 0%, rgba(11, 17, 32, 0.9) 100%); border: 1px solid var(--quezon-border-gold);">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; text-align: center;">
                <div>
                    <span style="font-size: 2.4rem; font-weight: 800; font-family: var(--font-heading); color: var(--quezon-gold); display: block;">1977</span>
                    <span style="color: #ffffff; font-weight: 700; font-size: 0.9rem;">Charter Year</span>
                    <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">First in District B4 &bull; 18th in PH</p>
                </div>
                <div>
                    <span style="font-size: 2.4rem; font-weight: 800; font-family: var(--font-heading); color: var(--quezon-green-light); display: block;">27</span>
                    <span style="color: #ffffff; font-weight: 700; font-size: 0.9rem;">Chapter Presidents</span>
                    <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Guiding continuous excellence</p>
                </div>
                <div>
                    <span style="font-size: 2.4rem; font-weight: 800; font-family: var(--font-heading); color: #60a5fa; display: block;">300+</span>
                    <span style="color: #ffffff; font-weight: 700; font-size: 0.9rem;">Registered Members</span>
                    <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Across 4 legislative districts</p>
                </div>
                <div>
                    <span style="font-size: 2.4rem; font-weight: 800; font-family: var(--font-heading); color: #fbbf24; display: block;">50th</span>
                    <span style="color: #ffffff; font-weight: 700; font-size: 0.9rem;">Anniversary Horizon</span>
                    <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Golden jubilee milestone</p>
                </div>
            </div>
        </div>

        <!-- FULL MILESTONES TIMELINE -->
        <div class="section-header">
            <h2><?php echo icon('award', '', 22); ?> Historical <span>Timeline &bull; Chronology</span></h2>
        </div>

        <div class="milestone-timeline-full">
            <?php foreach ($milestones as $idx => $ms): ?>
                <div class="milestone-card-full">
                    <div class="milestone-year-badge">
                        <?php echo htmlspecialchars($ms['year']); ?>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 4px;">
                            Era #<?php echo $idx + 1; ?> &bull; Chapter History
                        </div>
                        <h3 style="font-size: 1.25rem; color: #ffffff; font-weight: 700; margin-bottom: 8px;">
                            <?php echo htmlspecialchars($ms['title']); ?>
                        </h3>
                        <p style="font-size: 0.92rem; color: #cbd5e1; line-height: 1.7;">
                            <?php echo htmlspecialchars($ms['description']); ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- OFFICIAL COMMEMORATIVE INFOGRAPHIC: BUILT THROUGH TIME -->
        <section class="section-box" style="margin-top: 2.5rem; text-align: center;">
            <div class="section-header" style="justify-content: center; text-align: center; margin-bottom: 1.2rem;">
                <h2><?php echo icon('award', '', 22); ?> Official Commemorative Poster &bull; <span>Built Through Time</span></h2>
            </div>
            <p style="color: var(--text-muted); max-width: 720px; margin: 0 auto 1.5rem; font-size: 0.95rem; line-height: 1.6;">
                The complete visual chronicle celebrating our founding on November 11, 1977, the legacy of our 27 Chapter Presidents, and our approaching Golden 50th Anniversary.
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

        <!-- LEADERSHIP & COMMUNITY IMPACT -->
        <section class="section-box" style="margin-top: 2.5rem;">
            <div class="section-header">
                <h2><?php echo icon('handshake', '', 22); ?> Community Impact &bull; <span>RA 9266 Implementation</span></h2>
            </div>
            <div style="font-size: 1.05rem; line-height: 1.8; color: #cbd5e1;">
                <p style="margin-bottom: 1rem;">
                    The chapter maintains an active community presence through collaborative efforts with municipal governments throughout Quezon in the enforcement of <strong>Republic Act No. 9266 (The Architecture Act)</strong>, ensuring that only licensed and registered architects prepare, sign, and seal architectural plans.
                </p>
                <p>
                    Through community extension services, pro-bono civic planning, and post-disaster facility evaluations, UAP-QPC continues to uphold its commitment to positive civic service and sustainable regional progress.
                </p>
            </div>
        </section>

        <!-- CTAS -->
        <div style="text-align: center; padding: 2rem 0; display: flex; justify-content: center; align-items: center; gap: 14px; flex-wrap: wrap;">
            <a href="<?php echo BASE_URL; ?>/public/about.php" class="btn-outline-quezon">
                &larr; About UAP Quezon
            </a>
            <a href="<?php echo BASE_URL; ?>/public/architects.php" class="btn-primary-quezon">
                Explore Architects Directory &rarr;
            </a>
        </div>
    </div>

    <?php require __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>

