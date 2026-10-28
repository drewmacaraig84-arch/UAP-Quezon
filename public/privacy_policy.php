<?php
require_once __DIR__ . '/../includes/config.php';
$page_title = 'Data Privacy Policy • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow" style="color: #fbbf24;">LEGAL COMPLIANCE &bull; RA 10173</p>
    <h1>Data Privacy Policy</h1>
    <p class="page-subtitle">How the United Architects of the Philippines - Quezon Chapter collects, protects, and handles your professional and personal data.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('shield_check', '', 14); ?> <span>NPC &amp; RA 10173 Compliant</span>
  </div>
</div>

<div class="card" style="max-width: 900px; margin: 0 auto 40px; padding: 36px; line-height: 1.7; color: var(--text-primary);">
  <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 20px; margin-bottom: 24px;">
    <span class="muted" style="font-size: 13px;">Effective Date: October 2026 &bull; Chapter Administration</span>
    <h2 style="font-size: 20px; font-weight: 800; margin-top: 6px; color: var(--accent-primary);">United Architects of the Philippines &ndash; Quezon Chapter</h2>
    <p style="margin: 0; font-size: 14px; color: var(--text-secondary);">
      The UAP Quezon Chapter is committed to protecting your personal data and upholding your rights under <strong>Republic Act No. 10173</strong>, otherwise known as the <em>Data Privacy Act of 2012 (DPA)</em> of the Republic of the Philippines.
    </p>
  </div>

  <section style="margin-bottom: 28px;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('user', '', 18); ?> 1. Information We Collect
    </h3>
    <p style="font-size: 14px; color: var(--text-secondary);">
      Upon creating a member account, updating your profile, or transacting within the portal, the Chapter collects and processes the following categories of information:
    </p>
    <ul style="font-size: 13.5px; color: var(--text-secondary); margin-left: 20px;">
      <li><strong>Personal &amp; Demographic Data:</strong> Full legal name, gender, date of birth, calculated age, civil status, and home/office address.</li>
      <li><strong>Professional Credentials:</strong> Professional Regulation Commission (PRC) License number, UAP National ID number, year of admission, and architectural specialization.</li>
      <li><strong>Contact Information:</strong> Official mobile/telephone numbers and active email address.</li>
      <li><strong>Financial &amp; Transactional Records:</strong> Assigned annual and national dues obligations, payment receipts, GCash/bank transaction references, and uploaded payment proof images.</li>
      <li><strong>Public Directory Content:</strong> Firm name, professional bio, portfolio links, and 1x1 architectural portrait (only if approved and consented for the public showcase).</li>
    </ul>
  </section>

  <section style="margin-bottom: 28px;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('shield', '', 18); ?> 2. Purpose of Processing
    </h3>
    <p style="font-size: 14px; color: var(--text-secondary);">
      Your personal and professional data is processed strictly for legitimate chapter administrative functions:
    </p>
    <ul style="font-size: 13.5px; color: var(--text-secondary); margin-left: 20px;">
      <li>Verifying and authenticating legitimate PRC-registered architects for Chapter membership vetting.</li>
      <li>Tracking chapter financial compliance, generating digital official receipts, and certifying <em>Member in Good Standing</em> status.</li>
      <li>Recording General Membership Meeting (GMM), assembly, and convention attendance.</li>
      <li>Delivering urgent chapter advisories, CPD seminar invitations, and electoral notifications.</li>
    </ul>
  </section>

  <section style="margin-bottom: 28px;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('database', '', 18); ?> 3. Data Storage, Security &amp; Retention
    </h3>
    <p style="font-size: 14px; color: var(--text-secondary);">
      The Chapter maintains rigorous technical, physical, and administrative safeguards. All passwords are encrypted with one-way algorithmic hashing (bcrypt). Database connections are secured, and administrative access is audited with forensic telemetry logging. Soft-deleted files are held in quarantine and purged in compliance with statutory retention standards.
    </p>
  </section>

  <section style="margin-bottom: 28px;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
      <?php echo icon('check', '', 18); ?> 4. Your Rights as a Data Subject
    </h3>
    <p style="font-size: 14px; color: var(--text-secondary);">
      Under the Data Privacy Act, you have the right to:
    </p>
    <ul style="font-size: 13.5px; color: var(--text-secondary); margin-left: 20px;">
      <li>Be informed of how your data is collected and processed.</li>
      <li>Access and request copies of your registered chapter profile and dues records.</li>
      <li>Rectify or update outdated personal information via your Member Portal.</li>
      <li>Object to or withhold consent for optional public directory publishing.</li>
    </ul>
  </section>

  <section style="border-top: 1px solid var(--border-color); padding-top: 20px; font-size: 13px; color: var(--text-secondary);">
    <strong style="color: var(--text-primary);">Data Protection Inquiries:</strong><br>
    United Architects of the Philippines &ndash; Quezon Chapter Secretariat<br>
    Email: <a href="mailto:privacy@uapquezon.org" style="color: var(--accent-primary);">privacy@uapquezon.org</a> &bull; Lucena City, Quezon Province
  </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

