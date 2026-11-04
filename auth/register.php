<?php

require_once __DIR__ . '/../includes/config.php';

$error = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf();

    $last_name = trim($_POST['last_name'] ?? '');

    $first_name_raw = trim($_POST['first_name'] ?? '');

    $middle_initial = trim($_POST['middle_initial'] ?? '');

    $suffix = trim($_POST['suffix'] ?? '');

    $membership_status = strtoupper(trim($_POST['membership_status'] ?? 'UAP'));

    $id_number = trim($_POST['id_number'] ?? '');

    $email = trim($_POST['email'] ?? '');

    $phone = trim($_POST['phone'] ?? '');

    $gender = trim($_POST['gender'] ?? '');

    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;

    $civil_status = trim($_POST['civil_status'] ?? '');

    $address = trim($_POST['address'] ?? '');

    $password = $_POST['password'] ?? '';

    $confirm_password = $_POST['confirm_password'] ?? '';

    $terms_agree = !empty($_POST['terms_agree']);



    // Fallback if legacy single 'name' field is posted

    if ($last_name === '' && $first_name_raw === '' && !empty($_POST['name'])) {

        $parsed = parse_member_full_name($_POST['name']);

        $last_name = $parsed['last_name'];

        $first_name_raw = $parsed['first_name'];

        $middle_initial = $parsed['middle_initial'];

        $suffix = $parsed['suffix'];

        $membership_status = $parsed['membership_status'];

    }



    // Clean and normalize name fields

    $cleanFirst = trim(preg_replace('/^(ar\.?|arch\.?|architect)\s*/i', '', $first_name_raw));

    // Requirement: Automatically put AR. on the front of first name after registration

    $first_name = 'Ar. ' . $cleanFirst;



    if ($middle_initial !== '') {

        $cleanMI = strtoupper(rtrim($middle_initial, '.'));

        $middle_initial = $cleanMI . '.';

    }



    if ($suffix !== '') {
        $cleanSuffix = trim($suffix);
        $sufLower = strtolower(rtrim($cleanSuffix, '.'));
        if (in_array($sufLower, ['jr', 'sr'])) {
            $suffix = ucfirst($sufLower) . '.';
        } else {
            $suffix = strtoupper($cleanSuffix);
        }
    }

    // Preserve titles and post-nominals (e.g. UAP, EUAP, FUAP, Dr., ASEAN, EnP)
    if ($membership_status === '') {
        $membership_status = 'UAP';
    }



    // Compose official forward name (e.g. "Ar. Juan M. Dela Cruz, Jr., UAP")

    $nameParts = [$first_name];

    if ($middle_initial !== '') {

        $nameParts[] = $middle_initial;

    }

    $nameParts[] = $last_name;

    $canonicalName = implode(' ', $nameParts);

    if ($suffix !== '') {

        $canonicalName .= ', ' . $suffix;

    }

    if ($membership_status !== '') {

        $canonicalName .= ', ' . $membership_status;

    }

    $name = $canonicalName;



    if (!$last_name || !$cleanFirst || !$id_number || !$password) {

        $error = 'Please fill in all required fields (Last Name, First Name, PRC License #, Password).';

    } elseif (strlen($password) < 6) {

        $error = 'Password must be at least 6 characters long.';

    } elseif ($password !== $confirm_password) {

        $error = 'Passwords do not match.';

    } elseif (!$terms_agree) {

        $error = 'You must accept the Terms of Agreement, Data Privacy Policy, and Cookie Policy to create an account.';

    } else {

        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");

        $stmt->execute([$id_number]);

        if ($stmt->fetch()) {

            $error = 'An account with that PRC License Number already exists. Please sign in or contact the secretariat.';

        } else {

            // Compute Age dynamically

            $computedAge = null;

            if (!empty($birth_date)) {

                try {

                    $dob = new DateTime($birth_date);

                    $today = new DateTime();

                    $computedAge = $dob->diff($today)->y;

                } catch (Exception $e) {

                    $computedAge = null;

                }

            }



            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("

                INSERT INTO users (

                    name, first_name, middle_initial, last_name, suffix, membership_status,

                    id_number, prc_number, email, phone, gender, birth_date, age, civil_status, address,

                    password, role, status, terms_accepted, terms_accepted_at, privacy_accepted, privacy_accepted_at,

                    cookie_accepted, cookie_accepted_at

                ) VALUES (

                    ?, ?, ?, ?, ?, ?,

                    ?, ?, ?, ?, ?, ?, ?, ?, ?,

                    ?, 'member', 'pending', 1, NOW(), 1, NOW(), 1, NOW()

                )

            ");

            $stmt->execute([

                $name,

                $first_name,

                $middle_initial ?: null,

                $last_name,

                $suffix ?: null,

                $membership_status,

                $id_number,

                $id_number,

                $email ?: null,

                $phone ?: null,

                $gender ?: null,

                $birth_date ?: null,

                $computedAge,

                $civil_status ?: null,

                $address ?: null,

                $hash

            ]);



            // Social & Telegram notification

            @require_once __DIR__ . '/../includes/social_notifier.php';

            if (function_exists('notify_admin_event')) {

                notify_admin_event($pdo, 'registration', [

                    'name' => $name,

                    'id_number' => $id_number,

                    'email' => $email,

                    'phone' => $phone

                ]);

            }



            header('Location: ' . BASE_URL . '/auth/login.php?registered=1');

            exit;

        }

    }

}



$page_title = 'Member Registration • UAP Quezon Province Chapter';

include __DIR__ . '/../includes/header.php';

?>



<div class="auth-page" style="padding: 50px 20px 80px 20px; align-items: flex-start; min-height: 100vh;">

  <!-- THEME TOGGLE BUTTON -->

  <div style="position: absolute; top: 20px; right: 20px; z-index: 50;">

    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleTheme()" style="padding: 8px 14px; display: inline-flex; align-items: center; gap: 6px;">

      <?php echo icon('sun', '', 14); ?> <span id="themeToggleText">Switch Theme</span>

    </button>

  </div>



  <div class="auth-card-modern register-card" style="max-width: 1060px; margin: 0 auto;">

    <!-- LEFT BRANDING & HERO PANEL -->

    <div class="auth-hero-panel" style="position: sticky; top: 30px; height: fit-content; min-height: 100%;">

      <div>

        <div class="auth-logo-badge">

          <img src="<?php echo BASE_URL; ?>/public/logo.jpg" alt="UAP Quezon Province Chapter Logo" onerror="if(this.src.indexOf('uploads/logo.jpg')===-1)this.src='<?php echo BASE_URL; ?>/uploads/logo.jpg';">

        </div>

        <p class="eyebrow" style="color: #fbbf24; margin-bottom: 6px;">NEW MEMBER REGISTRATION</p>

        <h2 class="auth-hero-title">Join UAP Quezon Province Chapter</h2>

        <p class="auth-hero-sub">Register your official architect credentials to access online dues payments, track official receipts, and appear in the certified directory.</p>



        <div class="auth-feature-list">

          <div class="auth-feature-item">

            <div class="auth-feature-icon">

              <?php echo icon('user', '', 16); ?>

            </div>

            <span>Official Architect Profile &amp; Demographics</span>

          </div>

          <div class="auth-feature-item">

            <div class="auth-feature-icon">

              <?php echo icon('dues', '', 16); ?>

            </div>

            <span>Online Dues &amp; Installment Tracking</span>

          </div>

          <div class="auth-feature-item">

            <div class="auth-feature-icon">

              <?php echo icon('shield_check', '', 16); ?>

            </div>

            <span>Good Standing Certification</span>

          </div>

          <div class="auth-feature-item">

            <div class="auth-feature-icon">

              <?php echo icon('website_directory', '', 16); ?>

            </div>

            <span>Public Directory &amp; Digital QR Showcase</span>

          </div>

        </div>

      </div>



      <div style="margin-top: 36px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.08); font-size: 12px; color: #64748b;">

        <div>&copy; <?php echo date('Y'); ?> UAP Quezon Province Chapter &bull; Portal &amp; Management</div>

        <div style="margin-top: 5px; font-size: 11.5px;">

          Designed &amp; Developed by <a href="https://drewportfoliio.vercel.app/" target="_blank" rel="noopener noreferrer" style="color: #fbbf24; font-weight: 600; text-decoration: underline; text-underline-offset: 2px;">Drew Macaraig</a>

        </div>

      </div>

    </div>



    <!-- RIGHT REGISTER FORM PANEL -->

    <div class="auth-form-panel" style="padding: 44px 40px; justify-content: flex-start;">

      <div class="auth-header">

        <h1>Create Member Account</h1>

        <p>Register as a licensed architect of the UAP Quezon Province Chapter.</p>

      </div>



      <?php if ($error): ?>

        <div class="alert alert-error" style="display: flex; align-items: center; gap: 8px; margin-bottom: 20px;">

          <?php echo icon('alert', '', 18); ?>

          <div>

            <strong>Registration Error</strong><br>

            <span style="font-size: 12.5px;"><?php echo htmlspecialchars($error); ?></span>

          </div>

        </div>

      <?php endif; ?>



      <form method="post" autocomplete="on">

        <?php echo csrf_field(); ?>



        <div style="font-size: 12px; font-weight: 700; color: var(--accent-primary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">

          <span>Section 1: Architectural Identity &amp; Credentials</span>

          <span style="font-size: 11px; text-transform: none; color: #94a3b8; font-weight: 500;">Title <code style="color:#f59e0b; background: rgba(245,158,11,0.1); padding: 1px 5px; border-radius: 4px;">Ar.</code> is automatically prefixed to First Name</span>

        </div>



        <!-- Row 1: Last Name, First Name, Middle Initial -->

        <div class="arch-identity-grid-1" style="display: grid; grid-template-columns: 1fr 1fr 110px; gap: 12px; margin-bottom: 14px;">

          <div class="field" style="margin: 0;">

            <label>Last Name <span style="color:#ef4444;">*</span></label>

            <div class="input-icon-wrap">

              <span class="input-leading-icon"><?php echo icon('user', '', 18); ?></span>

              <input type="text" name="last_name" required placeholder="e.g. Dela Cruz" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" style="text-transform: capitalize;">

            </div>

          </div>



          <div class="field" style="margin: 0;">

            <label>First Name <span style="color:#ef4444;">*</span></label>

            <div class="input-icon-wrap" style="position: relative;">

              <span class="input-leading-icon" style="font-size: 11.5px; font-weight: 800; color: var(--accent-primary); background: rgba(245,158,11,0.15); padding: 2px 7px; border-radius: 4px; left: 10px;">Ar.</span>

              <input type="text" name="first_name" required placeholder="Juan" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" style="padding-left: 48px; text-transform: capitalize;">

            </div>

          </div>



          <div class="field" style="margin: 0;">

            <label>M.I.</label>

            <input type="text" name="middle_initial" maxlength="5" placeholder="e.g. M." value="<?php echo htmlspecialchars($_POST['middle_initial'] ?? ''); ?>" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg, #0f172a); color: var(--text-primary); text-transform: uppercase;">

          </div>

        </div>



        <!-- Row 2: Name Suffix, Titles / Post-Nominals, PRC License # -->
        <div class="arch-identity-grid-2" style="display: grid; grid-template-columns: 140px 1.5fr 1.1fr; gap: 12px; margin-bottom: 14px;">
          <div class="field" style="margin: 0;">
            <label>Name Suffix <span style="font-size: 11px; color: #94a3b8; font-weight: normal;">(optional)</span></label>
            <input type="text" name="suffix" list="suffixList" maxlength="20" placeholder="e.g. Jr., III" value="<?php echo htmlspecialchars($_POST['suffix'] ?? ''); ?>" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg, #0f172a); color: var(--text-primary);">
            <datalist id="suffixList">
              <option value="Jr.">Jr.</option>
              <option value="Sr.">Sr.</option>
              <option value="I">I</option>
              <option value="II">II</option>
              <option value="III">III</option>
              <option value="IV">IV</option>
              <option value="V">V</option>
              <option value="VI">VI</option>
            </datalist>
          </div>

          <div class="field" style="margin: 0;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px;">
              <label style="margin: 0;">Titles / Post-Nominals <span style="color:#ef4444;">*</span></label>
            </div>
            <input type="text" name="membership_status" id="titleInput" required list="titlesList" placeholder="e.g. UAP, FUAP, Dr., ASEAN, EnP" value="<?php echo htmlspecialchars($_POST['membership_status'] ?? 'UAP'); ?>" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg, #0f172a); color: var(--text-primary); font-weight: 600;">
            <datalist id="titlesList">
              <option value="UAP">UAP (Regular Member)</option>
              <option value="EUAP">EUAP (Emeritus)</option>
              <option value="FUAP">FUAP (Fellow, UAP)</option>
              <option value="UAP, EnP">UAP, EnP (Environmental Planner)</option>
              <option value="FUAP, ASEAN Ar.">FUAP, ASEAN Ar.</option>
              <option value="Dr., UAP">Dr., UAP</option>
              <option value="Dr., FUAP, ASEAN Ar., EnP">Dr., FUAP, ASEAN Ar., EnP</option>
            </datalist>
            <!-- Quick Chips -->
            <div style="display: flex; gap: 4px; flex-wrap: wrap; margin-top: 6px;">
              <button type="button" class="title-chip-btn" onclick="addTitleTag('UAP')">+ UAP</button>
              <button type="button" class="title-chip-btn" onclick="addTitleTag('EUAP')">+ EUAP</button>
              <button type="button" class="title-chip-btn" onclick="addTitleTag('FUAP')">+ FUAP</button>
              <button type="button" class="title-chip-btn" onclick="addTitleTag('Dr.')">+ Dr.</button>
              <button type="button" class="title-chip-btn" onclick="addTitleTag('ASEAN')">+ ASEAN</button>
              <button type="button" class="title-chip-btn" onclick="addTitleTag('EnP')">+ EnP</button>
            </div>
          </div>

          <div class="field" style="margin: 0;">
            <label>PRC License # <span style="color:#ef4444;">*</span></label>
            <div class="input-icon-wrap">
              <span class="input-leading-icon"><?php echo icon('shield_check', '', 18); ?></span>
              <input type="text" name="id_number" required placeholder="e.g. 0089452" value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>">
            </div>
          </div>
        </div>

        <div style="font-size: 12px; font-weight: 700; color: var(--accent-primary); text-transform: uppercase; letter-spacing: 0.5px; margin: 18px 0 12px 0;">

          Section 2: Member Demographic Details

        </div>



        <div class="form-row-2">

          <div class="field" style="margin: 0;">

            <label>Gender</label>

            <select name="gender" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg, #0f172a); color: var(--text-primary);">

              <option value="">Select Gender</option>

              <option value="Male" <?php echo ($_POST['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>

              <option value="Female" <?php echo ($_POST['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>

              <option value="Prefer not to say" <?php echo ($_POST['gender'] ?? '') === 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>

            </select>

          </div>



          <div class="field" style="margin: 0;">

            <label>Civil Status</label>

            <select name="civil_status" class="form-control" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg, #0f172a); color: var(--text-primary);">

              <option value="">Select Civil Status</option>

              <option value="Single" <?php echo ($_POST['civil_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>

              <option value="Married" <?php echo ($_POST['civil_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>

              <option value="Widowed" <?php echo ($_POST['civil_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>

              <option value="Separated" <?php echo ($_POST['civil_status'] ?? '') === 'Separated' ? 'selected' : ''; ?>>Separated</option>

              <option value="Divorced" <?php echo ($_POST['civil_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>

            </select>

          </div>

        </div>



        <div class="form-row-2">

          <div class="field" style="margin: 0;">

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">

              <label style="margin: 0;">Birth Date</label>

              <span id="regAgeBadge" style="font-size: 11px; font-weight: 700; color: #f59e0b;">Age: —</span>

            </div>

            <input type="date" name="birth_date" id="regBirthDate" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg, #0f172a); color: var(--text-primary);" value="<?php echo htmlspecialchars($_POST['birth_date'] ?? ''); ?>" onchange="updateRegAge(this.value)">

          </div>



          <div class="field" style="margin: 0;">

            <label>Mobile Number</label>

            <div class="input-icon-wrap">

              <span class="input-leading-icon"><?php echo icon('mail', '', 18); ?></span>

              <input type="tel" name="phone" placeholder="e.g. +63 917 123 4567" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">

            </div>

          </div>

        </div>



        <div class="field" style="margin-bottom: 14px;">

          <label>Email Address</label>

          <div class="input-icon-wrap">

            <span class="input-leading-icon"><?php echo icon('mail', '', 18); ?></span>

            <input type="email" name="email" placeholder="architect@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">

          </div>

        </div>



        <div class="field" style="margin-bottom: 18px;">

          <label>Complete Address</label>

          <textarea name="address" rows="2" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg, #0f172a); color: var(--text-primary); resize: vertical;" placeholder="House/Unit #, Street, Barangay, City/Municipality, Province"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>

        </div>



        <div style="font-size: 12px; font-weight: 700; color: var(--accent-primary); text-transform: uppercase; letter-spacing: 0.5px; margin: 18px 0 12px 0;">

          Section 3: Security &amp; Credentials

        </div>



        <div class="form-row-2" style="margin-bottom: 18px;">

          <div class="field" style="margin: 0;">

            <label>Create Password <span style="color:#ef4444;">*</span></label>

            <div class="input-icon-wrap">

              <span class="input-leading-icon"><?php echo icon('key', '', 18); ?></span>

              <input type="password" id="regPassword" name="password" required placeholder="Min. 6 characters">

            </div>

          </div>



          <div class="field" style="margin: 0;">

            <label>Confirm Password <span style="color:#ef4444;">*</span></label>

            <div class="input-icon-wrap">

              <span class="input-leading-icon"><?php echo icon('key', '', 18); ?></span>

              <input type="password" id="regConfirmPassword" name="confirm_password" required placeholder="Re-type password">

            </div>

          </div>

        </div>



        <!-- ============================================================== -->

        <!-- LEGAL COMPLIANCE: PRIVACY TERMS, TERMS OF AGREEMENT & COOKIES  -->

        <!-- ============================================================== -->

        <div style="padding: 16px; background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.25); border-radius: 10px; margin-bottom: 22px;">

          <div style="display: flex; align-items: flex-start; gap: 10px;">

            <input type="checkbox" name="terms_agree" id="termsAgreeCheckbox" value="1" required style="margin-top: 3px; cursor: pointer; width: 18px; height: 18px; accent-color: var(--accent-primary, #f59e0b);">

            <label for="termsAgreeCheckbox" style="font-size: 13px; color: var(--text-primary); line-height: 1.5; cursor: pointer;">

              I certify that all details submitted are truthful and authentic. I have read, understood, and voluntarily agree to the 

              <button type="button" class="legal-link-btn" onclick="openLegalModal('termsModal')">Terms of Agreement</button>, 

              <button type="button" class="legal-link-btn" onclick="openLegalModal('privacyModal')">Data Privacy Policy</button>, and 

              <button type="button" class="legal-link-btn" onclick="openLegalModal('cookieModal')">Cookie Policy</button> 

              of the UAP Quezon Province Chapter.

            </label>

          </div>

        </div>



        <button class="btn auth-btn-primary" type="submit" style="width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; padding: 12px;">

          <?php echo icon('plus', '', 16); ?> <span>Submit Member Application</span>

        </button>

      </form>



      <div class="auth-footer-link" style="margin-top: 18px;">

        Already have a member account? <a href="<?php echo BASE_URL; ?>/auth/login.php">Sign in here</a>

      </div>

    </div>

  </div>

</div>



<!-- ============================================================== -->

<!-- LEGAL MODALS (TERMS, PRIVACY, COOKIES)                         -->

<!-- ============================================================== -->

<div id="legalModalBackdrop" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 20px;">

  <div style="background: var(--card-bg, #1e293b); border: 1px solid var(--border-color, #334155); border-radius: 12px; max-width: 650px; width: 100%; max-height: 80vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.5);">

    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">

      <h3 id="legalModalTitle" style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary);">Policy</h3>

      <button type="button" onclick="closeLegalModal()" style="background: transparent; border: none; color: var(--text-secondary); cursor: pointer; font-size: 18px; padding: 4px;">&times;</button>

    </div>

    <div id="legalModalContent" style="padding: 20px; overflow-y: auto; font-size: 13.5px; line-height: 1.6; color: var(--text-secondary);">

      <!-- Injected via JavaScript -->

    </div>

    <div style="padding: 12px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">

      <button type="button" class="btn btn-sm btn-primary" onclick="closeLegalModal()">I Understand &amp; Agree</button>

    </div>

  </div>

</div>



<style>

.legal-link-btn {

  background: none;

  border: none;

  padding: 0;

  color: var(--accent-primary, #f59e0b);

  font-weight: 700;

  text-decoration: underline;

  cursor: pointer;

  font-size: 13px;

  display: inline;

}

.legal-link-btn:hover {

  color: #fbbf24;

}



@media (max-width: 768px) {

  .arch-identity-grid-1, .arch-identity-grid-2 {

    grid-template-columns: 1fr !important;

  }

}




.title-chip-btn {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.25);
  color: #fbbf24;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s ease;
}
.title-chip-btn:hover {
  background: rgba(245, 158, 11, 0.22);
  border-color: #f59e0b;
}
</style>



<script>
function addTitleTag(tag) {
  const input = document.getElementById('titleInput');
  if (!input) return;
  let val = input.value.trim();
  if (!val) {
    input.value = tag;
    return;
  }
  let tags = val.split(',').map(s => s.trim()).filter(Boolean);
  if (['UAP', 'EUAP', 'FUAP'].includes(tag)) {
    tags = tags.filter(t => !['UAP', 'EUAP', 'FUAP'].includes(t.toUpperCase()));
    tags.unshift(tag);
  } else {
    if (!tags.some(t => t.toLowerCase() === tag.toLowerCase())) {
      tags.push(tag);
    }
  }
  input.value = tags.join(', ');
  input.focus();
}


function updateRegAge(birthDateStr) {

  const badge = document.getElementById('regAgeBadge');

  if (!birthDateStr) {

    badge.textContent = 'Age: —';

    return;

  }

  const dob = new Date(birthDateStr);

  const diff = Date.now() - dob.getTime();

  const ageDate = new Date(diff);

  const calculatedAge = Math.abs(ageDate.getUTCFullYear() - 1970);

  if (!isNaN(calculatedAge)) {

    badge.textContent = 'Age: ' + calculatedAge + ' yrs';

  } else {

    badge.textContent = 'Age: —';

  }

}



const legalTexts = {

  termsModal: {

    title: 'Terms of Agreement & Chapter By-Laws',

    content: `

      <p><strong>1. Architectural License Integrity:</strong> Membership is restricted to registered architects certified by PRC under RA 9266. All information submitted must be accurate and valid.</p>

      <p><strong>2. Financial Obligations:</strong> Members agree to settle assigned chapter dues and obligations within fiscal deadlines to maintain active Good Standing status.</p>

      <p><strong>3. Code of Ethics:</strong> Members pledge compliance with the Architect's National Code (SPP 200) and the UAP National Constitution.</p>

      <p><strong>4. Portal Acceptable Use:</strong> Unauthorized credential sharing, vulnerability testing, or data scraping is strictly prohibited.</p>

      <p><em>Full policy available at: <a href="<?php echo BASE_URL; ?>/public/terms_of_agreement.php" target="_blank" style="color: #f59e0b;">Official Terms of Agreement Page</a></em></p>

    `

  },

  privacyModal: {

    title: 'Data Privacy Policy (RA 10173)',

    content: `

      <p><strong>1. Compliance:</strong> UAP Quezon Province Chapter complies with Republic Act No. 10173 (Data Privacy Act of 2012) and National Privacy Commission regulations.</p>

      <p><strong>2. Information Collected:</strong> We process your full name, PRC number, date of birth, contact details, address, and chapter payment proofs.</p>

      <p><strong>3. Purpose:</strong> Data is collected solely for chapter membership management, good standing verification, dues billing, and official event tracking.</p>

      <p><strong>4. Security:</strong> Encrypted passwords, role-based access controls, and forensic audit logs safeguard your records against unauthorized disclosure.</p>

      <p><em>Full policy available at: <a href="<?php echo BASE_URL; ?>/public/privacy_policy.php" target="_blank" style="color: #f59e0b;">Official Data Privacy Policy Page</a></em></p>

    `

  },

  cookieModal: {

    title: 'Cookie Policy',

    content: `

      <p><strong>1. Essential Cookies Only:</strong> Our portal deploys essential session cookies (<code>PHPSESSID</code> and <code>csrf_token</code>) required to authenticate your account and secure financial transactions.</p>

      <p><strong>2. Preference Storage:</strong> We use local preferences to remember your Light or Dark theme choice.</p>

      <p><strong>3. Zero Third-Party Trackers:</strong> We never sell, transmit, or expose member activity to third-party advertisers or marketing data brokers.</p>

      <p><em>Full policy available at: <a href="<?php echo BASE_URL; ?>/public/cookie_policy.php" target="_blank" style="color: #f59e0b;">Official Cookie Policy Page</a></em></p>

    `

  }

};



function openLegalModal(type) {

  const modal = document.getElementById('legalModalBackdrop');

  const title = document.getElementById('legalModalTitle');

  const content = document.getElementById('legalModalContent');

  if (legalTexts[type]) {

    title.textContent = legalTexts[type].title;

    content.innerHTML = legalTexts[type].content;

    modal.style.display = 'flex';

  }

}



function closeLegalModal() {

  document.getElementById('legalModalBackdrop').style.display = 'none';

}

</script>



<?php include __DIR__ . '/../includes/footer.php'; ?>

