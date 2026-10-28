<?php
require_once __DIR__ . '/../includes/config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $rateKey = 'login_attempts_' . md5($ip);
    $attempts = (int)($_SESSION[$rateKey] ?? 0);
    $lastAttemptTime = (int)($_SESSION[$rateKey . '_time'] ?? 0);

    // Lockout for 60 seconds if more than 5 attempts within 1 minute
    if ($attempts >= 5 && (time() - $lastAttemptTime) < 60) {
        $remainingLock = 60 - (time() - $lastAttemptTime);
        $error = "Too many failed login attempts. Please wait {$remainingLock} seconds before trying again.";
    } else {
        if ((time() - $lastAttemptTime) >= 60) {
            $_SESSION[$rateKey] = 0;
            $attempts = 0;
        }

        $id_number = trim($_POST['id_number'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ? OR (prc_number = ? AND prc_number IS NOT NULL AND prc_number != '') LIMIT 1");
            $stmt->execute([$id_number, $id_number]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            // Auto-initialize tables from schema.sql if tables do not exist yet
            if (str_contains($e->getMessage(), '1146') || (int)$e->getCode() === 1146) {
                $schemaFile = __DIR__ . '/../schema.sql';
                if (file_exists($schemaFile)) {
                    $rawSql = file_get_contents($schemaFile);
                    $cleanSql = '';
                    foreach (explode("\n", $rawSql) as $line) {
                        $t = trim($line);
                        if ($t !== '' && !str_starts_with($t, '--') && !str_starts_with($t, '/*')) {
                            $cleanSql .= $line . "\n";
                        }
                    }
                    foreach (array_filter(array_map('trim', explode(';', $cleanSql))) as $sqlStmt) {
                        if ($sqlStmt) {
                            try { $pdo->exec($sqlStmt); } catch (Throwable $eIgnore) {}
                        }
                    }
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ? OR (prc_number = ? AND prc_number IS NOT NULL AND prc_number != '') LIMIT 1");
                    $stmt->execute([$id_number, $id_number]);
                    $user = $stmt->fetch();
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }

        if ($user && password_verify($password, $user['password'])) {
            unset($_SESSION[$rateKey], $_SESSION[$rateKey . '_time']);

            if ($user['role'] === 'member' && $user['status'] === 'pending') {
                $error = 'Your account is currently awaiting administrative approval. Please check back shortly.';
            } elseif ($user['role'] === 'member' && $user['status'] === 'rejected') {
                $error = 'Your registration application was not approved. Please contact chapter administration.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                if (function_exists('audit_log')) {
                    audit_log('LOGIN_SUCCESS', 'AUTH', "Logged in as {$user['role']}", $user['id']);
                }
                header('Location: ' . BASE_URL . (in_array($user['role'], ['admin', 'super_admin']) ? '/admin/dashboard.php' : '/member/dashboard.php'));
                exit;
            }
        } else {
            $_SESSION[$rateKey] = $attempts + 1;
            $_SESSION[$rateKey . '_time'] = time();
            $error = 'Invalid PRC ID Number or password.';
        }
    }
}

$page_title = 'Portal Login • UAP Quezon Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
  <!-- THEME TOGGLE BUTTON -->
  <div style="position: absolute; top: 20px; right: 20px; z-index: 50;">
    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleTheme()" style="padding: 8px 14px; display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('sun', '', 14); ?> <span id="themeToggleText">Switch Theme</span>
    </button>
  </div>

  <div class="auth-card-modern">
    <!-- LEFT BRANDING & HERO PANEL -->
    <div class="auth-hero-panel">
      <div>
        <div class="auth-logo-badge">
          <img src="<?php echo BASE_URL; ?>/public/logo.jpg" alt="UAP Quezon Chapter Logo" onerror="if(this.src.indexOf('uploads/logo.jpg')===-1)this.src='<?php echo BASE_URL; ?>/uploads/logo.jpg';">
        </div>
        <p class="eyebrow" style="color: #fbbf24; margin-bottom: 6px;">OFFICIAL CHAPTER PORTAL</p>
        <h2 class="auth-hero-title">United Architects of the Philippines</h2>
        <p class="auth-hero-sub">Quezon Chapter &bull; Centralized financial administration, dues tracking, and member compliance system.</p>

        <div class="auth-feature-list">
          <div class="auth-feature-item">
            <div class="auth-feature-icon">
              <?php echo icon('zap', '', 16); ?>
            </div>
            <span>Instant Chapter Dues &amp; Payment Processing</span>
          </div>
          <div class="auth-feature-item">
            <div class="auth-feature-icon">
              <?php echo icon('shield_check', '', 16); ?>
            </div>
            <span>Verified Good Standing Status Tracking</span>
          </div>
          <div class="auth-feature-item">
            <div class="auth-feature-icon">
              <?php echo icon('website_directory', '', 16); ?>
            </div>
            <span>Public Architect Directory &amp; Showcase</span>
          </div>
        </div>
      </div>

      <div style="margin-top: 36px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.08); font-size: 12px; color: #64748b;">
        <div>&copy; <?php echo date('Y'); ?> UAP Quezon Chapter &bull; Portal &amp; Management</div>
        <div style="margin-top: 5px; font-size: 11.5px;">
          Designed &amp; Developed by <a href="https://drewportfoliio.vercel.app/" target="_blank" rel="noopener noreferrer" style="color: #fbbf24; font-weight: 600; text-decoration: underline; text-underline-offset: 2px;">Drew Macaraig</a>
        </div>
      </div>
    </div>

    <!-- RIGHT LOGIN FORM PANEL -->
    <div class="auth-form-panel">
      <div class="auth-header">
        <h1>Welcome Back</h1>
        <p>Sign in to access your administrative dashboard or member dues.</p>
      </div>

      <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success" style="display: flex; align-items: center; gap: 8px;">
          <?php echo icon('check', '', 18); ?>
          <div>
            <strong>Registration Submitted!</strong><br>
            <span style="font-size: 12.5px;">Your account is pending admin verification.</span>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-error" style="display: flex; align-items: center; gap: 8px;">
          <?php echo icon('alert', '', 18); ?>
          <div>
            <strong>Login Failed</strong><br>
            <span style="font-size: 12.5px;"><?php echo htmlspecialchars($error); ?></span>
          </div>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <?php echo csrf_field(); ?>
        
        <div class="field">
          <label>PRC License # / Username</label>
          <div class="input-icon-wrap">
            <span class="input-leading-icon"><?php echo icon('user', '', 18); ?></span>
            <input type="text" name="id_number" required autofocus placeholder="Enter PRC License # or Username" value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>">
          </div>
        </div>

        <div class="field" style="margin-bottom: 22px;">
          <label>Password</label>
          <div class="input-icon-wrap">
            <span class="input-leading-icon"><?php echo icon('key', '', 18); ?></span>
            <input type="password" id="loginPassword" name="password" required placeholder="Enter your password">
            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('loginPassword', this)" aria-label="Toggle password visibility">
              <?php echo icon('eye', '', 16); ?>
            </button>
          </div>
        </div>

        <button class="btn auth-btn-primary" type="submit" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
          <?php echo icon('arrow_right', '', 16); ?> <span>Sign In to Portal</span>
        </button>
      </form>

      <div class="auth-footer-link">
        Don't have a chapter member account? <a href="<?php echo BASE_URL; ?>/auth/register.php">Register here</a>
      </div>
    </div>
  </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  if (input.type === 'password') {
    input.type = 'text';
    btn.style.color = 'var(--accent-primary)';
  } else {
    input.type = 'password';
    btn.style.color = 'var(--muted-text)';
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
