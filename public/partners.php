<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

$pageTitle = 'Industry Partners & Corporate Collaborators | UAP Quezon Province Chapter';
$activePage = 'partners';

$partners = [];
try {
    $partners = $pdo->query("
        SELECT * FROM sponsors 
        WHERE is_active = 1 AND deleted_at IS NULL 
        ORDER BY (CASE WHEN tier = 'platinum' OR is_platinum = 1 THEN 1 ELSE 0 END) DESC, display_order ASC, id DESC
    ")->fetchAll();
} catch (Throwable $e) {
    try {
        $partners = $pdo->query("
            SELECT * FROM sponsors 
            WHERE is_active = 1 
            ORDER BY (CASE WHEN tier = 'platinum' THEN 1 ELSE 0 END) DESC, display_order ASC, id DESC
        ")->fetchAll();
    } catch (Throwable $e2) {
        $partners = [];
    }
}

$totalCount = count($partners);
$platinumCount = 0;
$regularCount = 0;
foreach ($partners as $p) {
    if (!empty($p['is_platinum'])) {
        $platinumCount++;
    } else {
        $regularCount++;
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/public_theme.css?v=2.3">
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
        .partners-filter-bar {
            display: inline-flex;
            gap: 8px;
            background: rgba(15, 23, 42, 0.8);
            padding: 6px;
            border-radius: 9999px;
            border: 1px solid var(--quezon-border);
            margin: 2rem auto 0;
            flex-wrap: wrap;
            justify-content: center;
        }
        .filter-pill-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 8px 16px;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 0.84rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filter-pill-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }
        .filter-pill-btn.active {
            background: var(--quezon-gold);
            color: #0b1120;
            box-shadow: 0 2px 10px rgba(229, 138, 19, 0.3);
        }
        .filter-pill-count {
            background: rgba(0, 0, 0, 0.25);
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 0.75rem;
        }
        .filter-pill-btn.active .filter-pill-count {
            background: rgba(0, 0, 0, 0.35);
            color: #ffffff;
        }
        .partners-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
            gap: 1.8rem;
            margin-top: 2.5rem;
        }
        .partner-showcase-card {
            background: var(--quezon-card);
            border: 1px solid var(--quezon-border);
            border-radius: 16px;
            padding: 1.6rem;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }
        .partner-showcase-card:hover {
            transform: translateY(-4px);
            border-color: var(--quezon-border-gold);
            box-shadow: 0 16px 30px rgba(0, 0, 0, 0.35);
        }
        .partner-showcase-card.is-platinum {
            border-color: rgba(245, 158, 11, 0.35);
            background: linear-gradient(180deg, rgba(245, 158, 11, 0.04) 0%, var(--quezon-card) 40%);
        }
        .partner-logo-box {
            height: 95px;
            background: #ffffff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            margin-bottom: 1.2rem;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }
        .partner-logo-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }
        .partner-tier-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 3px 9px;
            border-radius: 999px;
            width: fit-content;
            margin-bottom: 10px;
        }
        .tag-platinum {
            background: rgba(245, 158, 11, 0.18);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        .tag-standard {
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-muted);
            border: 1px solid var(--quezon-border);
        }
        .partner-name {
            font-size: 1.25rem;
            font-family: var(--font-heading);
            color: #ffffff;
            margin: 0 0 8px;
            line-height: 1.3;
        }
        .partner-bio {
            color: var(--text-muted);
            font-size: 0.88rem;
            line-height: 1.6;
            margin-bottom: 1.2rem;
            flex-grow: 1;
        }
        .partner-products-box {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--quezon-border);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 1.2rem;
        }
        .partner-products-header {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--quezon-gold);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .product-mini-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .product-mini-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .product-mini-img {
            width: 36px;
            height: 36px;
            border-radius: 4px;
            background: #fff;
            object-fit: cover;
            flex-shrink: 0;
        }
        .product-mini-name {
            font-size: 0.82rem;
            font-weight: 600;
            color: #e2e8f0;
            line-height: 1.3;
        }
        .partner-footer-action {
            margin-top: auto;
            padding-top: 10px;
            display: flex;
            gap: 10px;
        }
        .btn-partner-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--quezon-gold);
            background: rgba(229, 138, 19, 0.08);
            border: 1px solid var(--quezon-border-gold);
            transition: all 0.2s ease;
        }
        .btn-partner-link:hover {
            background: var(--quezon-gold);
            color: #0b1120;
        }
        .benefits-section {
            padding: 4rem 1.5rem;
            border-top: 1px solid var(--quezon-border);
            background: rgba(0, 0, 0, 0.2);
        }
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .benefit-card {
            background: var(--quezon-card);
            border: 1px solid var(--quezon-border);
            border-radius: 14px;
            padding: 1.6rem;
            text-align: left;
        }
        .benefit-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: rgba(229, 138, 19, 0.12);
            color: var(--quezon-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .partner-cta-banner {
            background: linear-gradient(135deg, rgba(229, 138, 19, 0.12) 0%, rgba(21, 128, 61, 0.15) 100%);
            border: 1px solid var(--quezon-border-gold);
            border-radius: 16px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            margin: 3rem 0;
        }
        @media (max-width: 640px) {
            .partners-grid { grid-template-columns: 1fr; }
            .page-hero { padding: 3.5rem 1rem 2.5rem; }
        }
    </style>
</head>
<body>

    <?php require __DIR__ . '/../includes/public_nav.php'; ?>

    <!-- HERO SECTION -->
    <section class="page-hero">
        <div class="hero-badge">
            <?php echo icon('handshake', '', 14); ?> Corporate &amp; Industry Collaboration
        </div>

        <h1>Industry <span class="highlight-gold">Partners</span> &amp; Collaborators</h1>

        <p class="lead" style="max-width: 720px; margin: 12px auto 0;">
            Empowering architectural innovation across Quezon Province through allied partnerships with material manufacturers, building technology providers, and construction specialists.
        </p>

        <!-- FILTER BAR -->
        <?php if ($totalCount > 0): ?>
            <div class="partners-filter-bar">
                <button type="button" class="filter-pill-btn active" onclick="filterPartners('all', this)">
                    <span>All Partners</span>
                    <span class="filter-pill-count"><?php echo $totalCount; ?></span>
                </button>
                <?php if ($platinumCount > 0): ?>
                    <button type="button" class="filter-pill-btn" onclick="filterPartners('platinum', this)">
                        <span>Platinum Partners</span>
                        <span class="filter-pill-count"><?php echo $platinumCount; ?></span>
                    </button>
                <?php endif; ?>
                <?php if ($regularCount > 0): ?>
                    <button type="button" class="filter-pill-btn" onclick="filterPartners('standard', this)">
                        <span>Industry Partners</span>
                        <span class="filter-pill-count"><?php echo $regularCount; ?></span>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- MAIN PARTNERS SHOWCASE -->
    <div class="page-container" style="max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem;">
        
        <?php if (empty($partners)): ?>
            <div style="text-align: center; padding: 4rem 1.5rem; background: var(--quezon-card); border: 1px solid var(--quezon-border); border-radius: 14px;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(229,138,19,0.12); color: var(--quezon-gold); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <?php echo icon('handshake', '', 26); ?>
                </div>
                <h3 style="color: #ffffff; font-size: 1.3rem; margin-bottom: 8px;">No Partners Listed Yet</h3>
                <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto 1.5rem; font-size: 0.9rem;">
                    Our official corporate partnership directory is being updated. Inquire with our Secretariat to explore partnership opportunities.
                </p>
                <a href="<?php echo BASE_URL; ?>/public/contact.php" class="btn-primary-quezon" style="display: inline-flex; padding: 10px 20px;">
                    Inquire for Corporate Partnership
                </a>
            </div>
        <?php else: ?>
            <div class="partners-grid reveal-stagger" id="partnersGrid">
                <?php foreach ($partners as $p): 
                    $isPlat = !empty($p['is_platinum']);
                    $tierKey = $isPlat ? 'platinum' : 'standard';
                    $products = [];
                    if (!empty($p['products_json'])) {
                        $decoded = json_decode($p['products_json'], true);
                        if (is_array($decoded)) $products = $decoded;
                    }
                    $sponsorUrl = !empty($p['url']) ? $p['url'] : (!empty($p['website_url']) ? $p['website_url'] : '');
                    $logoUrl = !empty($p['logo_path']) ? media_url($p['logo_path']) : '';
                ?>
                    <div class="partner-showcase-card <?php echo $isPlat ? 'is-platinum' : ''; ?>" data-tier="<?php echo $tierKey; ?>">
                        <!-- Tier Badge -->
                        <div class="partner-tier-tag <?php echo $isPlat ? 'tag-platinum' : 'tag-standard'; ?>">
                            <?php echo $isPlat ? 'Platinum Partner' : 'Industry Partner'; ?>
                        </div>

                        <!-- Logo Box -->
                        <div class="partner-logo-box">
                            <?php if ($logoUrl): ?>
                                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="partner-logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <span style="display: none; font-size: 1.2rem; font-weight: 800; color: #0b1120;"><?php echo htmlspecialchars($p['name']); ?></span>
                            <?php else: ?>
                                <span style="font-size: 1.2rem; font-weight: 800; color: #0b1120;"><?php echo htmlspecialchars($p['name']); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Partner Details -->
                        <h3 class="partner-name"><?php echo htmlspecialchars($p['name']); ?></h3>

                        <?php if (!empty($p['description'])): ?>
                            <div class="partner-bio">
                                <?php echo nl2br(htmlspecialchars($p['description'])); ?>
                            </div>
                        <?php else: ?>
                            <div class="partner-bio" style="font-style: italic; color: #64748b;">
                                Official Corporate Partner of UAP Quezon Province Chapter.
                            </div>
                        <?php endif; ?>

                        <!-- Promotional Products / Material Catalogs -->
                        <?php if (!empty($products)): ?>
                            <div class="partner-products-box">
                                <div class="partner-products-header">
                                    <?php echo icon('sparkles', '', 14); ?>
                                    <span>Featured Products &amp; Catalogs (<?php echo count($products); ?>)</span>
                                </div>
                                <?php foreach (array_slice($products, 0, 3) as $prod): 
                                    $prodImg = !empty($prod['image_path']) ? media_url($prod['image_path']) : '';
                                ?>
                                    <div class="product-mini-item">
                                        <?php if ($prodImg): ?>
                                            <img src="<?php echo htmlspecialchars($prodImg); ?>" alt="<?php echo htmlspecialchars($prod['name'] ?? 'Product'); ?>" class="product-mini-img">
                                        <?php endif; ?>
                                        <div style="flex: 1; min-width: 0;">
                                            <div class="product-mini-name"><?php echo htmlspecialchars($prod['name'] ?? 'Promotional Product'); ?></div>
                                            <?php if (!empty($prod['description'])): ?>
                                                <div style="font-size: 0.72rem; color: var(--text-muted); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                                    <?php echo htmlspecialchars($prod['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Action Link -->
                        <div class="partner-footer-action">
                            <?php if ($sponsorUrl): ?>
                                <a href="<?php echo htmlspecialchars($sponsorUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn-partner-link">
                                    <span>Visit Partner Website</span>
                                    <?php echo icon('arrow_right', '', 13); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- WHY PARTNER WITH US SECTION -->
        <section class="benefits-section" style="margin-top: 3.5rem; border-radius: 16px;">
            <div style="text-align: center;">
                <div class="hero-badge" style="margin-bottom: 8px;">
                    <?php echo icon('shield_check', '', 14); ?> Allied Industry Collaboration
                </div>
                <h2 style="font-family: var(--font-heading); color: #ffffff; font-size: 1.8rem; margin: 0 0 8px;">
                    Why Partner With UAP Quezon Province Chapter?
                </h2>
                <p style="color: var(--text-muted); max-width: 620px; margin: 0 auto; font-size: 0.9rem;">
                    We connect building material manufacturers, suppliers, and allied professionals with our network of over 300 licensed architects.
                </p>
            </div>

            <div class="benefits-grid reveal-stagger">
                <div class="benefit-card">
                    <div class="benefit-icon"><?php echo icon('members', '', 22); ?></div>
                    <h4 style="color: #ffffff; font-size: 1.05rem; margin: 0 0 6px;">Direct Architect Access</h4>
                    <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5; margin: 0;">
                        Engage directly with practicing architects, principal designers, and project managers across Lucena, Tayabas, Sariaya, and the four legislative districts.
                    </p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><?php echo icon('calendar', '', 22); ?></div>
                    <h4 style="color: #ffffff; font-size: 1.05rem; margin: 0 0 6px;">CPD &amp; Technical Masterclasses</h4>
                    <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5; margin: 0;">
                        Co-sponsor Continuing Professional Development (CPD) programs, launch product presentations, and conduct live material demonstrations for local practitioners.
                    </p>
                </div>
                <div class="benefit-card">
                    <div class="benefit-icon"><?php echo icon('sparkles', '', 22); ?></div>
                    <h4 style="color: #ffffff; font-size: 1.05rem; margin: 0 0 6px;">Permanent Digital Visibility</h4>
                    <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5; margin: 0;">
                        Enjoy prominent brand features across our official website, digital member portal, chapter circulars, and annual milestone celebrations.
                    </p>
                </div>
            </div>

            <!-- PARTNER CTA -->
            <div class="partner-cta-banner">
                <h3 style="font-family: var(--font-heading); color: #ffffff; font-size: 1.45rem; margin: 0 0 8px;">
                    Ready to Collaborate with Quezon Architects?
                </h3>
                <p style="color: #cbd5e1; max-width: 560px; margin: 0 auto 1.5rem; font-size: 0.9rem;">
                    Contact our Chapter Secretariat to discuss corporate partnership packages, technical presentation opportunities, and upcoming event schedules.
                </p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>/public/contact.php" class="btn-primary-quezon" style="padding: 10px 22px; font-weight: 700;">
                        Message Chapter Secretariat
                    </a>
                    <a href="<?php echo BASE_URL; ?>/public/architects.php" class="btn btn-partner-link" style="padding: 10px 18px; color: #ffffff; border-color: rgba(255,255,255,0.2);">
                        View Architect Directory &rarr;
                    </a>
                </div>
            </div>
        </section>
    </div>

    <!-- CLIENT FILTER SCRIPT -->
    <script>
    function filterPartners(tier, btn) {
        document.querySelectorAll('.filter-pill-btn').forEach(function(b) {
            b.classList.remove('active');
        });
        btn.classList.add('active');

        var cards = document.querySelectorAll('.partner-showcase-card');
        cards.forEach(function(card) {
            if (tier === 'all' || card.getAttribute('data-tier') === tier) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }
    </script>

    <?php require __DIR__ . '/../includes/public_footer.php'; ?>

</body>
</html>
