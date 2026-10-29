<?php
require_once __DIR__ . '/../includes/config.php';
$page_title = 'Cookie Policy • UAP Quezon Province Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow" style="color: #fbbf24;">PORTAL INTEGRITY &bull; PRIVACY</p>
    <h1>Cookie Policy</h1>
    <p class="page-subtitle">Learn how the UAP Quezon Province Chapter portal utilizes essential cookies for security and seamless navigation.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('zap', '', 14); ?> <span>Essential Cookies Only</span>
  </div>
</div>

<div class="card" style="max-width: 900px; margin: 0 auto 40px; padding: 36px; line-height: 1.7; color: var(--text-primary);">
  <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 24px;">
    <span class="muted" style="font-size: 13px;">Last Updated: October 2026 &bull; System Administration</span>
    <h2 style="font-size: 20px; font-weight: 800; margin-top: 6px; color: var(--accent-primary);">United Architects of the Philippines &ndash; Quezon Province Chapter</h2>
    <p style="margin: 0; font-size: 14px; color: var(--text-secondary);">
      This Cookie Policy explains how our chapter management system uses cookies and similar technologies to recognize you when you visit our portal.
    </p>
  </div>

  <section style="margin-bottom: 28px;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('settings', '', 18); ?> 1. What Are Cookies?
    </h3>
    <p style="font-size: 14px; color: var(--text-secondary);">
      Cookies are small text files placed on your computer or mobile device when you access websites. They are widely used to make websites work properly, provide secure user authentication, and remember your visual display preferences.
    </p>
  </section>

  <section style="margin-bottom: 28px;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('shield_check', '', 18); ?> 2. Cookies We Deploy
    </h3>
    <p style="font-size: 14px; color: var(--text-secondary);">
      The UAP Quezon Province Chapter portal only deploys <strong>Strictly Necessary and Functional Cookies</strong>:
    </p>
    <div class="table-shell" style="margin-top: 14px;">
      <table>
        <thead>
          <tr>
            <th>Cookie Name</th>
            <th>Type</th>
            <th>Purpose</th>
            <th>Lifespan</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><code>PHPSESSID</code></td>
            <td>Essential Session</td>
            <td>Maintains your authenticated member session and prevents unauthorized login spoofing.</td>
            <td>Browser Session</td>
          </tr>
          <tr>
            <td><code>csrf_token</code></td>
            <td>Security Token</td>
            <td>Protects your account against Cross-Site Request Forgery (CSRF) on forms and financial submissions.</td>
            <td>Browser Session</td>
          </tr>
          <tr>
            <td><code>uap_theme</code></td>
            <td>Visual Preference</td>
            <td>Remembers your preference for Dark Mode or Light Mode theme across visits.</td>
            <td>1 Year</td>
          </tr>
          <tr>
            <td><code>uap_cookies_accepted</code></td>
            <td>Functional</td>
            <td>Records your acknowledgement of the Cookie Policy banner so you are not prompted repeatedly.</td>
            <td>1 Year</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <section style="margin-bottom: 28px;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('check', '', 18); ?> 3. Zero Third-Party Advertising Trackers
    </h3>
    <p style="font-size: 14px; color: var(--text-secondary);">
      We respect professional confidentiality. We <strong>do not use</strong> advertising cookies, third-party marketing trackers, data broker scripts, or cross-site tracking pixels on this portal.
    </p>
  </section>

  <section style="margin-bottom: 28px;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('archive', '', 18); ?> 4. How You Can Control Cookies
    </h3>
    <p style="font-size: 14px; color: var(--text-secondary);">
      You can control or clear cookies at any time through your browser settings. However, disabling essential session cookies will prevent you from signing in to your member portal or submitting dues proofs.
    </p>
  </section>

  <section style="border-top: 1px solid var(--border-color); padding-top: 20px; font-size: 13px; color: var(--text-secondary);">
    <strong style="color: var(--text-primary);">System Administrator Inquiries:</strong><br>
    United Architects of the Philippines &ndash; Quezon Province Chapter<br>
    Email: <a href="mailto:admin@uapquezon.org" style="color: var(--accent-primary);">admin@uapquezon.org</a>
  </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

