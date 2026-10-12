</div>
<footer class="footer" style="padding: 24px 28px; border-top: 1px solid var(--border-color); color: var(--text-secondary); font-size: 13px; margin-top: 40px;">
    <div style="max-width: 1380px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            &copy; <?php echo date('Y'); ?> <strong>United Architects of the Philippines &bull; Mindoro Chapter</strong>
        </div>
        <div style="font-size: 12px; color: var(--text-secondary);">
            UAP-MC Portal &bull; Designed &amp; Developed by <strong>Aries King Nieto</strong> and <a href="https://portfolio-drew-c5ac.vercel.app/#home" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: underline; text-underline-offset: 2px;"><strong>Drew Macaraig</strong></a>
        </div>
    </div>
</footer>

<script>
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
  if (nav) {
    nav.classList.toggle('open');
  }
}

// ================= GLOBAL CONFIRMATION MODAL =================
let pendingConfirmAction = null;

function showConfirmModal(event, targetAction, options = {}) {
  if (event && event.preventDefault) event.preventDefault();
  
  const title = options.title || 'Confirm Action';
  const message = options.message || 'Are you sure you want to proceed?';
  const confirmText = options.confirmText || 'Confirm';
  const btnClass = options.btnClass || 'btn-success';

  document.getElementById('uapConfirmTitle').textContent = title;
  document.getElementById('uapConfirmMessage').textContent = message;

  const okBtn = document.getElementById('uapConfirmOkBtn');
  okBtn.textContent = confirmText;
  okBtn.className = 'btn btn-sm ' + btnClass;
  
  const iconContainer = document.getElementById('uapConfirmIcon');
  if (iconContainer) {
    if (btnClass.includes('btn-danger')) {
      iconContainer.style.background = 'rgba(239, 68, 68, 0.15)';
      iconContainer.style.color = '#ef4444';
    } else {
      iconContainer.style.background = 'rgba(245, 158, 11, 0.15)';
      iconContainer.style.color = 'var(--accent-primary, #f59e0b)';
    }
  }

  if (typeof targetAction === 'function') {
    pendingConfirmAction = targetAction;
  } else if (targetAction && targetAction.tagName === 'FORM') {
    pendingConfirmAction = () => targetAction.submit();
  } else {
    pendingConfirmAction = null;
  }

  const modal = document.getElementById('uapConfirmModal');
  if (modal) modal.style.display = 'flex';
  return false;
}

function closeUapConfirmModal() {
  const modal = document.getElementById('uapConfirmModal');
  if (modal) modal.style.display = 'none';
  pendingConfirmAction = null;
}

document.addEventListener('DOMContentLoaded', function() {
  const okBtn = document.getElementById('uapConfirmOkBtn');
  if (okBtn) {
    okBtn.addEventListener('click', function() {
      if (pendingConfirmAction) {
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
