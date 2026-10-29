</div>
<footer class="footer" style="padding: 24px 28px; border-top: 1px solid var(--border-color); color: var(--text-secondary); font-size: 13px; margin-top: 40px;">
    <div style="max-width: 1380px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <div>
              &copy; <?php echo date('Y'); ?> <strong>United Architects of the Philippines &bull; Quezon Province Chapter</strong>
            </div>
            <div style="font-size: 12px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
              <a href="<?php echo BASE_URL; ?>/public/privacy_policy.php" style="color: var(--text-secondary); text-decoration: none;">Privacy Policy</a>
              <span>&bull;</span>
              <a href="<?php echo BASE_URL; ?>/public/terms_of_agreement.php" style="color: var(--text-secondary); text-decoration: none;">Terms of Agreement</a>
              <span>&bull;</span>
              <a href="<?php echo BASE_URL; ?>/public/cookie_policy.php" style="color: var(--text-secondary); text-decoration: none;">Cookie Policy</a>
            </div>
        </div>
        <div style="font-size: 12px; color: var(--text-secondary); text-align: right;">
            Designed &amp; Developed by <a href="https://drewportfoliio.vercel.app/" target="_blank" rel="noopener noreferrer" style="color: var(--accent-primary, #f59e0b); font-weight: 700; text-decoration: underline; text-underline-offset: 2px;">Drew Macaraig</a>
        </div>
    </div>
</footer>

<!-- Floating Cookie Consent Banner -->
<div id="uapCookieBanner" style="display: none; position: fixed; bottom: 20px; right: 20px; max-width: 420px; width: calc(100% - 40px); background: rgba(15, 23, 42, 0.96); backdrop-filter: blur(12px); border: 1px solid rgba(245,158,11,0.35); border-radius: 14px; padding: 18px 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.6); z-index: 99990; color: #f8fafc;">
  <div style="display: flex; align-items: flex-start; gap: 12px;">
    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(245,158,11,0.18); color: #f59e0b; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 16px;">
      🍪
    </div>
    <div style="flex: 1;">
      <strong style="font-size: 13.5px; display: block; margin-bottom: 4px; color: #fbbf24;">Cookie &amp; Privacy Notice</strong>
      <p style="margin: 0 0 12px 0; font-size: 12px; line-height: 1.45; color: #cbd5e1;">
        We use essential cookies to maintain secure sessions and protect financial transactions. By continuing, you agree to our 
        <a href="<?php echo BASE_URL; ?>/public/cookie_policy.php" style="color: #fbbf24; text-decoration: underline;">Cookie Policy</a> and 
        <a href="<?php echo BASE_URL; ?>/public/privacy_policy.php" style="color: #fbbf24; text-decoration: underline;">Privacy Terms</a>.
      </p>
      <div style="display: flex; gap: 8px;">
        <button type="button" onclick="acceptUapCookies()" class="btn btn-sm btn-primary" style="font-size: 11.5px; padding: 6px 14px; font-weight: 700;">
          Accept All
        </button>
        <button type="button" onclick="dismissUapCookies()" class="btn btn-sm btn-secondary" style="font-size: 11.5px; padding: 6px 12px;">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// ================= COOKIE CONSENT =================
document.addEventListener('DOMContentLoaded', function() {
  const consent = localStorage.getItem('uap_cookie_consent');
  if (!consent) {
    const banner = document.getElementById('uapCookieBanner');
    if (banner) {
      setTimeout(() => { banner.style.display = 'block'; }, 1000);
    }
  }
});

function acceptUapCookies() {
  localStorage.setItem('uap_cookie_consent', 'accepted');
  const banner = document.getElementById('uapCookieBanner');
  if (banner) banner.style.display = 'none';
}

function dismissUapCookies() {
  sessionStorage.setItem('uap_cookie_banner_dismissed', '1');
  const banner = document.getElementById('uapCookieBanner');
  if (banner) banner.style.display = 'none';
}

// ================= THEME MANAGEMENT =================
function toggleTheme() {
  const html = document.getElementById('htmlElement');
  const current = html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  const next = current === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  document.documentElement.style.colorScheme = next;
  localStorage.setItem('theme', next);
  updateThemeText(next);
}

function updateThemeText(theme) {
  const textEl = document.getElementById('themeToggleText');
  if (textEl) {
    textEl.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const theme = document.getElementById('htmlElement').getAttribute('data-theme') || 'dark';
  updateThemeText(theme);

  const themeBtn = document.getElementById('themeMenuToggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      toggleTheme();
    });
  }
});

// ================= USER MENU & NOTIFICATIONS =================
document.addEventListener('DOMContentLoaded', function() {
  const userTrigger = document.getElementById('userMenuTrigger');
  if (userTrigger) {
    userTrigger.addEventListener('click', function(e) {
      e.stopPropagation();
      userTrigger.classList.toggle('open');
      const bell = document.getElementById('notificationBell');
      if (bell) bell.classList.remove('open');
    });
  }

  const bell = document.getElementById('notificationBell');
  if (bell) {
    bell.addEventListener('click', function(e) {
      e.stopPropagation();
      bell.classList.toggle('open');
      if (userTrigger) userTrigger.classList.remove('open');
    });
  }

  document.addEventListener('click', function(e) {
    if (userTrigger && !userTrigger.contains(e.target)) {
      userTrigger.classList.remove('open');
    }
    if (bell && !bell.contains(e.target)) {
      bell.classList.remove('open');
    }
  });

  // Global Search across table rows and card titles
  const searchInput = document.getElementById('globalSearchInput');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('table tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = (q === '' || text.includes(q)) ? '' : 'none';
      });
    });
  }
});

// ================= MOBILE NAVIGATION =================
function toggleMobileMenu() {
  const nav = document.getElementById('sidebarNav');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (nav) {
    const isOpen = nav.classList.toggle('open');
    if (backdrop) backdrop.classList.toggle('active', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
  }
}

function closeMobileNav() {
  const nav = document.getElementById('sidebarNav');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (nav) nav.classList.remove('open');
  if (backdrop) backdrop.classList.remove('active');
  document.body.style.overflow = '';
}

// ================= GLOBAL CONFIRMATION MODAL =================
let pendingConfirmAction = null;

function showConfirmModal(event, targetAction, options = {}) {
  if (event && event.preventDefault) event.preventDefault();
  pendingConfirmAction = targetAction;

  const modal = document.getElementById('uapConfirmModal');
  const title = document.getElementById('uapConfirmTitle');
  const message = document.getElementById('uapConfirmMessage');
  const okBtn = document.getElementById('uapConfirmOkBtn');

  if (modal && title && message && okBtn) {
    title.textContent = options.title || 'Confirm Action';
    message.textContent = options.message || 'Are you sure you want to proceed?';
    okBtn.textContent = options.confirmText || 'Confirm';

    okBtn.className = 'btn btn-sm ' + (options.btnClass || 'btn-success');
    modal.style.display = 'flex';
  }
}

function closeUapConfirmModal() {
  const modal = document.getElementById('uapConfirmModal');
  if (modal) {
    modal.style.display = 'none';
  }
  pendingConfirmAction = null;
}

document.addEventListener('DOMContentLoaded', function() {
  const okBtn = document.getElementById('uapConfirmOkBtn');
  if (okBtn) {
    okBtn.addEventListener('click', function() {
      if (typeof pendingConfirmAction === 'function') {
        const action = pendingConfirmAction;
        closeUapConfirmModal();
        action();
      }
    });
  }

  const modal = document.getElementById('uapConfirmModal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === this) closeUapConfirmModal();
    });
  }

  // Intercept forms with data-confirm
  document.addEventListener('submit', function(e) {
    const form = e.target;
    if (form && form.getAttribute && form.getAttribute('data-confirm')) {
      if (form.dataset.confirmed === 'true') {
        form.dataset.confirmed = 'false';
        return true;
      }
      e.preventDefault();
      const message = form.getAttribute('data-confirm');
      const title = form.getAttribute('data-confirm-title') || 'Confirm Action';
      const btnText = form.getAttribute('data-confirm-btn') || 'Confirm';
      const btnClass = form.getAttribute('data-confirm-class') || 'btn-success';

      showConfirmModal(e, () => {
        form.dataset.confirmed = 'true';
        form.submit();
      }, { title, message, confirmText: btnText, btnClass });
    }
  });
});
</script>

<!-- Global Confirmation Modal Markup -->
<div id="uapConfirmModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.75);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);">
  <div style="background:var(--card-bg, #131d33);border:1px solid var(--border-color, rgba(255,255,255,0.12));border-radius:16px;max-width:440px;width:100%;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.6);color:var(--text-primary);">
    <div style="padding:24px;">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
        <div id="uapConfirmIcon" style="width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:rgba(245,158,11,0.15);color:var(--accent-primary);flex-shrink:0;">
          <?php echo function_exists('icon') ? icon('alert', '', 20) : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'; ?>
        </div>
        <h3 id="uapConfirmTitle" style="margin:0;font-size:17px;font-weight:700;color:var(--text-primary);">Confirm Action</h3>
      </div>
      <p id="uapConfirmMessage" style="margin:0 0 24px 0;font-size:14px;line-height:1.5;color:var(--text-secondary);"></p>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" onclick="closeUapConfirmModal()" class="btn btn-sm btn-secondary" style="padding:8px 16px;">Cancel</button>
        <button type="button" id="uapConfirmOkBtn" class="btn btn-sm btn-success" style="padding:8px 18px;font-weight:700;">Confirm</button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
