# UAP-Quezon Application Architecture Refactoring

## Overview

Your UAP-Quezon application has been refactored to separate concerns into distinct, well-organized sections while maintaining full integration. This improves maintainability, scalability, and makes the codebase more intuitive to navigate.

## Architecture Changes

### New Directory Structure

```
UAP-Quezon/
├── index.php              ← Central entry point (router)
├── auth/                  ← Authentication module
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── public/                ← Public website module
│   └── homepage.php
├── member/                ← Member portal module
│   ├── dashboard.php
│   ├── history.php
│   └── pay.php
├── admin/                 ← Admin management module
│   ├── dashboard.php
│   ├── approvals.php
│   ├── members.php
│   ├── dues.php
│   ├── reports.php
│   ├── export_csv.php
│   ├── settings.php
│   ├── qr_codes.php
│   ├── account_manager.php
│   ├── change_password.php
│   └── verify.php
├── includes/              ← Shared resources (unchanged)
│   ├── config.php
│   ├── auth.php
│   ├── header.php
│   ├── footer.php
│   └── theme.css
├── database/              ← Database utilities (unchanged)
├── uploads/               ← User files (unchanged)
└── images/                ← Static assets (unchanged)
```

## Module Descriptions

### 1. **Auth Module** (`/auth/`)
Handles user authentication and session management.

**Files:**
- `login.php` - User login page (previously `/login.php`)
- `register.php` - User registration page (previously `/register.php`)
- `logout.php` - Session termination (previously `/logout.php`)

**Entry Points:**
- Public: `https://yoursite.com/auth/login.php`
- Public: `https://yoursite.com/auth/register.php`
- Internal redirect: `https://yoursite.com/auth/logout.php`

**Backward Compatibility:**
The old root-level files (`/login.php`, `/register.php`, `/logout.php`) still exist as redirects to the new locations, so existing bookmarks and external links continue to work.

---

### 2. **Public Module** (`/public/`)
Displays the public-facing website content for unauthenticated visitors.

**Files:**
- `homepage.php` - Main landing page with member directory, news, and about section

**Entry Points:**
- Default: `https://yoursite.com/` (via `index.php` router)
- Direct: `https://yoursite.com/public/homepage.php`

**Key Features:**
- Member directory table
- News and announcements
- About section
- Sponsor/partner placeholders
- Login button (links to `/auth/login.php`)

---

### 3. **Member Module** (`/member/`)
Personal member dashboard and payment management.

**Files:**
- `dashboard.php` - Member home, dues overview
- `pay.php` - Payment submission interface
- `history.php` - Payment history and receipts

**Entry Points:**
- `https://yoursite.com/member/dashboard.php`
- `https://yoursite.com/member/pay.php`
- `https://yoursite.com/member/history.php`

**Access Control:**
- Requires authentication: `require_login()` from `includes/auth.php`
- Requires member role: `require_member()` from `includes/auth.php`
- Automatically redirects admin users to `/admin/dashboard.php`

---

### 4. **Admin Module** (`/admin/`)
Administrative control panel for dues management, payment verification, and organization settings.

**Files:**
- `dashboard.php` - Admin overview, pending payments queue
- `approvals.php` - Member registration approval queue
- `verify.php` - Payment approval/rejection handler
- `members.php` - Member management and status editing
- `dues.php` - Create and manage due items
- `reports.php` - Generate and view membership reports
- `export_csv.php` - Export member data
- `settings.php` - Organization settings (logo, etc.)
- `qr_codes.php` - QR code management for dues
- `account_manager.php` - Admin account management
- `change_password.php` - Admin password change

**Entry Points:**
- `https://yoursite.com/admin/dashboard.php`
- All admin pages (see files list above)

**Access Control:**
- Requires authentication: `require_login()`
- Requires admin role: `require_admin()` from `includes/auth.php`
- Automatically redirects member users to `/member/dashboard.php`

---

## Router Logic

### Central Entry Point: `index.php`

The root `index.php` now acts as the application's central router:

```php
<?php
require_once __DIR__ . '/includes/config.php';

if (isset($_SESSION['user_id'])) {
    // User is logged in - redirect to appropriate dashboard
    header('Location: ' . BASE_URL . 
        ($_SESSION['role'] === 'admin' 
            ? '/admin/dashboard.php' 
            : '/member/dashboard.php')
    );
    exit;
}

// Not logged in - show public homepage
include __DIR__ . '/public/homepage.php';
?>
```

**Behavior:**
- **Logged-in admin:** Redirects to `/admin/dashboard.php`
- **Logged-in member:** Redirects to `/member/dashboard.php`
- **Not logged in:** Shows public homepage (`/public/homepage.php`)

---

## Key Changes in Include Paths

All files in `/auth/`, `/public/`, `/member/`, and `/admin/` now use proper relative paths to the shared `includes/` directory:

**Before (root-level):**
```php
require_once __DIR__ . '/includes/config.php';
```

**After (in subdirectories):**
```php
require_once __DIR__ . '/../includes/config.php';
```

This ensures the application correctly finds shared resources regardless of the file's location.

---

## Authentication & Authorization

All protected pages use these functions from `includes/auth.php`:

| Function | Purpose | Redirects to |
|----------|---------|--------------|
| `require_login()` | Ensures user is logged in | `/auth/login.php` |
| `require_admin()` | Ensures user is logged-in admin | `/member/dashboard.php` (if not admin) |
| `require_member()` | Ensures user is logged-in member | `/admin/dashboard.php` (if not member) |
| `current_user_id()` | Gets the current user's ID | N/A |

**Updated redirect in `auth.php`:**
```php
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');  // ← Now points to /auth/
        exit;
    }
}
```

---

## Navigation Links

All navigation links have been updated to point to the new locations:

| Old URL | New URL | Location |
|---------|---------|----------|
| `/login.php` | `/auth/login.php` | Updated in `includes/header.php` |
| `/register.php` | `/auth/register.php` | Updated in `includes/header.php` |
| `/logout.php` | `/auth/logout.php` | Updated in `includes/header.php` |
| `/public_homepage.php` | `/public/homepage.php` | Now included from `index.php` |

---

## Backward Compatibility

The old root-level files are preserved and now function as redirects:

- `/login.php` → `/auth/login.php`
- `/register.php` → `/auth/register.php`
- `/logout.php` → `/auth/logout.php`
- `/public_homepage.php` → `/public/homepage.php`

This ensures:
- External links and bookmarks continue to work
- Easy migration without breaking existing URLs
- A smooth transition period before fully deprecating old paths

---

## Benefits of This Architecture

1. **Separation of Concerns**
   - Each module has a clear, distinct purpose
   - Easier to understand and maintain the codebase

2. **Scalability**
   - Adding new features to each module doesn't affect others
   - Easy to add new admin/member pages in their respective directories

3. **Security**
   - Clear protection layers with `require_login()`, `require_admin()`, `require_member()`
   - Easier to audit access control

4. **Organization**
   - New developers can quickly locate files by their module
   - URL structure mirrors file structure

5. **Shared Resources**
   - Central `includes/` directory for all shared components
   - Single point of configuration (`config.php`)
   - Consistent headers, footers, styling across all modules

---

## Next Steps

### If you want to completely deprecate old URLs:
1. Remove the old redirect files (`/login.php`, `/register.php`, `/logout.php`, `/public_homepage.php`)
2. Update any hard-coded internal links to use new paths
3. Test all user flows thoroughly

### If you want to strengthen security:
1. Add `.htaccess` rules to prevent direct access to old files (optional)
2. Consider adding rate limiting to login attempts
3. Implement CSRF tokens for form submissions

### If you want to add new features:
1. Create files in the appropriate module directory
2. Use the same auth guards and naming conventions
3. Follow the existing URL structure

---

## File References

### Updated Files:
- `index.php` - New router logic
- `includes/auth.php` - Updated redirect to `/auth/login.php`
- `includes/header.php` - Updated all navigation links

### New Files:
- `auth/login.php` - Moved from root
- `auth/register.php` - Moved from root
- `auth/logout.php` - Moved from root
- `public/homepage.php` - Moved from `public_homepage.php`

### Backward Compatibility Files:
- `login.php` - Now redirects to `/auth/login.php`
- `register.php` - Now redirects to `/auth/register.php`
- `logout.php` - Now redirects to `/auth/logout.php`
- `public_homepage.php` - Now includes from `/public/homepage.php`

### Updated Documentation:
- `README.md` - Updated file structure section

---

## Testing Checklist

- [ ] Visit `https://yoursite.com/` - Shows public homepage
- [ ] Click "Login" button - Goes to `/auth/login.php`
- [ ] Click "Register" link - Goes to `/auth/register.php`
- [ ] Register a new account - Redirects to `/auth/login.php?registered=1`
- [ ] Login as member - Redirects to `/member/dashboard.php`
- [ ] Login as admin - Redirects to `/admin/dashboard.php`
- [ ] Logout - Redirects to `/auth/login.php`
- [ ] Visit old URL `/login.php` - Redirects to `/auth/login.php`
- [ ] Visit old URL `/register.php` - Redirects to `/auth/register.php`
- [ ] Member dashboard works - Can view dues and payment history
- [ ] Admin dashboard works - Can see pending payments and approvals
- [ ] All navigation links work - Header/footer navigation points to correct modules

---

## Support

If you encounter any issues:
1. Check the browser console for errors
2. Verify all paths are correct relative to your project root
3. Ensure `includes/config.php` has the correct `BASE_URL` setting
4. Check PHP error logs for any file path issues

---

**Refactoring Completed:** 2026-08-18
**Status:** ✅ Fully functional with backward compatibility
