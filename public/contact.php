<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';

$pageTitle = 'Contact Secretariat | UAP Quezon Province Chapter';
$activePage = 'contact';

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
        $contactError = 'Please provide your full name and inquiry message.';
    } elseif (!$email && !$phone) {
        $contactError = 'Please provide at least an email address or mobile phone number.';
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'Please provide a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_inquiries (name, email, phone, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, 'unread', NOW())");
            $stmt->execute([$name, $email, $phone, $subject, $msg]);
            $contactSuccess = 'Thank you for reaching out! Your message has been received by the UAP Quezon Province Chapter secretariat.';

            // Social & Telegram notification
            @require_once __DIR__ . '/../includes/social_notifier.php';
            if (function_exists('notify_admin_event')) {
                notify_admin_event($pdo, 'inquiry', [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'subject' => $subject,
                    'message' => $msg
                ]);
            }
        } catch (Throwable $e) {
            $contactError = 'Unable to send message at this time. Please try again or contact us directly via email.';
        }
    }
}

$contactEmail = get_site_setting($pdo, 'contact_email', 'uap.qpc@gmail.com');
$contactPhone = get_site_setting($pdo, 'contact_phone', '09399125237 / 09175939781');
$contactAddress = get_site_setting($pdo, 'contact_address', 'Lucena City, Quezon Province, Philippines');
$hoursWeekdays = get_site_setting($pdo, 'office_hours_weekdays', '9:00 AM – 5:00 PM');
$hoursSaturday = get_site_setting($pdo, 'office_hours_saturday', '9:00 AM – 12:00 PM');
$hoursSunday = get_site_setting($pdo, 'office_hours_sunday', 'Closed');
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
        .contact-layout {
            display: grid;
            grid-template-columns: 1fr 1.35fr;
            gap: 2.5rem;
            margin-top: 2rem;
            align-items: start;
        }
        .info-card-wrap {
            background: var(--quezon-card);
            border: 1px solid var(--quezon-border);
            border-radius: 16px;
            padding: 2.2rem;
        }
        .contact-form-wrap {
            background: var(--quezon-card);
            border: 1px solid var(--quezon-border);
            border-radius: 16px;
            padding: 2.2rem;
        }
        .info-point {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 22px;
        }
        .info-point-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: rgba(229, 138, 19, 0.12);
            border: 1px solid var(--quezon-border-gold);
            color: var(--quezon-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        @media (max-width: 900px) {
            .contact-layout { grid-template-columns: 1fr; gap: 1.5rem; }
        }
        @media (max-width: 768px) {
            .page-hero { padding: 3.5rem 1rem 2.2rem; }
            .info-card-wrap, .contact-form-wrap { padding: 1.35rem 1rem; }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../includes/public_nav.php'; ?>

    <!-- HERO -->
    <section class="page-hero">
        <div class="hero-badge">
            <?php echo icon('mail', '', 14); ?> Secretariat &bull; Public Inquiries &bull; RA 9266 Verification
        </div>
        <h1>Get in Touch: <span class="highlight-gold">Contact Secretariat</span></h1>
        <p class="lead" style="max-width: 680px; margin: 16px auto 0;">
            Reach out to the UAP Quezon Province Chapter secretariat for architectural inquiries, member validation, partnerships, and LGU coordination.
        </p>
    </section>

    <div class="page-container" style="max-width: 1200px;">
        <div class="contact-layout">
            <!-- LEFT: INFO CARDS -->
            <div class="info-card-wrap reveal-left">
                <h2 style="color: #ffffff; font-size: 1.4rem; font-family: var(--font-heading); margin-bottom: 8px;">
                    Chapter Secretariat
                </h2>
                <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 24px;">
                    Our secretariat serves as the central administrative hub connecting clients, building officials, and licensed architects.
                </p>

                <div class="info-point">
                    <div class="info-point-icon"><?php echo icon('map_pin', '', 20); ?></div>
                    <div>
                        <strong style="color: #ffffff; display: block; font-size: 0.95rem; margin-bottom: 2px;">Office Location</strong>
                        <p style="color: #cbd5e1; font-size: 0.88rem;"><?php echo htmlspecialchars($contactAddress); ?></p>
                    </div>
                </div>

                <div class="info-point">
                    <div class="info-point-icon"><?php echo icon('mail', '', 20); ?></div>
                    <div>
                        <strong style="color: #ffffff; display: block; font-size: 0.95rem; margin-bottom: 2px;">Official Email</strong>
                        <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>" style="color: var(--quezon-gold); font-size: 0.88rem; text-decoration: none;">
                            <?php echo htmlspecialchars($contactEmail); ?>
                        </a>
                    </div>
                </div>

                <div class="info-point">
                    <div class="info-point-icon"><?php echo icon('phone', '', 20); ?></div>
                    <div>
                        <strong style="color: #ffffff; display: block; font-size: 0.95rem; margin-bottom: 2px;">Telephone / Mobile</strong>
                        <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $contactPhone)); ?>" style="color: #cbd5e1; font-size: 0.88rem; text-decoration: none;">
                            <?php echo htmlspecialchars($contactPhone); ?>
                        </a>
                    </div>
                </div>

                <div class="info-point">
                    <div class="info-point-icon"><?php echo icon('clock', '', 20); ?></div>
                    <div>
                        <strong style="color: #ffffff; display: block; font-size: 0.95rem; margin-bottom: 2px;">Secretariat Hours</strong>
                        <p style="color: #cbd5e1; font-size: 0.88rem;">Monday &ndash; Friday: <?php echo htmlspecialchars($hoursWeekdays); ?></p>
                        <p style="color: var(--text-muted); font-size: 0.82rem;">Saturday: <?php echo htmlspecialchars($hoursSaturday); ?> &bull; Sunday: <?php echo htmlspecialchars($hoursSunday); ?></p>
                    </div>
                </div>

                <!-- RA 9266 NOTICE -->
                <div style="background: rgba(229,138,19,0.08); border: 1px solid var(--quezon-border-gold); padding: 16px; border-radius: 12px; margin-top: 24px;">
                    <div style="display: flex; gap: 10px; align-items: center; color: var(--quezon-gold); font-weight: 700; font-size: 0.88rem; margin-bottom: 6px;">
                        <?php echo icon('shield_check', '', 18); ?>
                        <span>Republic Act No. 9266 Notice</span>
                    </div>
                    <p style="font-size: 0.82rem; color: #cbd5e1; line-height: 1.5;">
                        Under Philippine Law, only Registered and Licensed Architects (RLA) may prepare, sign, and dry-seal architectural plans and specifications. Verify your architect's credentials through our official directory.
                    </p>
                </div>
            </div>

            <!-- RIGHT: INQUIRY FORM -->
            <div class="contact-form-wrap">
                <h2 style="color: #ffffff; font-size: 1.4rem; font-family: var(--font-heading); margin-bottom: 8px;">
                    Send Us a Message
                </h2>
                <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 24px;">
                    Fill out the form below and our secretariat will review your inquiry and get back to you promptly.
                </p>

                <?php if ($contactSuccess): ?>
                    <div style="background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; padding: 16px; border-radius: 10px; font-size: 0.92rem; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($contactSuccess); ?>
                    </div>
                <?php elseif ($contactError): ?>
                    <div style="background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; padding: 16px; border-radius: 10px; font-size: 0.92rem; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($contactError); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="submit_inquiry">

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #e2e8f0; margin-bottom: 6px;">Full Name *</label>
                        <input type="text" name="name" required class="form-control" placeholder="e.g. Ar. Juan Dela Cruz / Engr. Santos" style="width: 100%;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                        <div class="form-group">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #e2e8f0; margin-bottom: 6px;">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="architect@example.com" style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #e2e8f0; margin-bottom: 6px;">Phone / Mobile</label>
                            <input type="text" name="phone" class="form-control" placeholder="0917 123 4567" style="width: 100%;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #e2e8f0; margin-bottom: 6px;">Subject</label>
                        <input type="text" name="subject" class="form-control" placeholder="Membership inquiry, project referral, LGU collaboration..." style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #e2e8f0; margin-bottom: 6px;">Your Message *</label>
                        <textarea name="message" required rows="5" class="form-control" placeholder="How can the UAP Quezon Province Chapter assist you?" style="width: 100%; resize: vertical;"></textarea>
                    </div>

                    <button type="submit" class="btn-submit-inquiry" style="width: 100%; font-size: 1rem; padding: 14px;">
                        Send Inquiry Message &rarr;
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/../includes/public_footer.php'; ?>
</body>
</html>

