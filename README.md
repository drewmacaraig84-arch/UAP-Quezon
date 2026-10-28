# UAP Quezon Chapter Management Portal & Super Admin Hub

A modern PHP + MySQL management portal and public architectural website designed for the **United Architects of the Philippines (UAP) - Quezon Chapter**, featuring a dedicated **Super Admin Suite** (System Health, Crash Logs, Soft Deleted Files Quarantine, Audit Trail, and 1-Click Database Backups).

---

## 🎨 Visual Identity & Color Palette

Derived directly from the official **UAP Quezon Chapter Logo**:
- **Radiant Sunset Gold / Amber**: `#F59E0B` / `#E58A13` (Top arch, sun, architectural highlights)
- **Quezon Mountain Emerald Green**: `#15803D` / `#16A34A` / `#22C55E` (Mt. Banahaw ridges and lush Quezon slopes)
- **Architectural Charcoal & Slate**: `#0B1120` / `#111827` / `#1E293B` (Ionic column fluting, surfaces, cards)
- **Crisp Mountain White**: `#FFFFFF` (Separators, typography, badges)
- **Signature Gradient**: `linear-gradient(135deg, #E58A13 0%, #15803D 100%)`

---

## ⚡ Default Login Credentials

| Role | PRC ID / ID Number | Default Password | Initial Destination |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `SUPERADMIN01` | `admin123` | [System Health & Admin Dashboard](http://localhost/UAP-Quezon/admin/dashboard.php) |
| **Chapter Admin** | `ADMIN001` | `admin123` | [Admin Dashboard](http://localhost/UAP-Quezon/admin/dashboard.php) |
| **Member Architect** | `UAPQ-2026-001` | `admin123` | [Member Dues & Receipts](http://localhost/UAP-Quezon/member/dashboard.php) |

---

## 🛡️ Super Admin Architecture & Capabilities

1. **System Health & Diagnostic Telemetry** (`admin/system_health.php`):
   - Real-time Health Score (`100%`)
   - CPU and Memory allocated, Peak Memory, Execution time limits
   - Live Database latency ping (ms), MySQL version, table-by-table storage breakdown
   - Disk space utilization (Total, Free, Used)
   - Storage breakdown for `uploads/`, `uploads/trash/` (quarantine), and `logs/`
   - Core PHP extension verification (PDO, PDO MySQL, GD, OpenSSL, cURL, Fileinfo)
   - One-click MySQL table optimization and index rebuild

2. **Automated System Crash & Error Logs** (`admin/crash_logs.php`):
   - Custom global error & exception watchdog (`includes/logger.php`)
   - Traps fatal errors, uncaught exceptions, and database errors (`PDOException`)
   - Logs timestamp, severity (`CRITICAL`, `ERROR`, `WARNING`, `INFO`), exception class, file path, line number, full stack trace, HTTP method, URL, client IP, and authenticated user ID
   - Super Admin UI with filtering by severity, status, search, and CSV export
   - One-click error resolution notes and "Trigger Test Crash" tool

3. **Soft-Deleted Files & Quarantine Hub** (`admin/soft_deleted_files.php`):
   - Non-destructive deletion workflow (`includes/soft_delete.php`)
   - When an avatar, payment proof, or document is removed, it is quarantined to `uploads/trash/` with metadata instead of permanent loss
   - Soft-deleted database entities across `users`, `dues`, `payments`, `news_announcements`, and `sponsors`
   - One-click file and database record restoration
   - One-click permanent purge / Empty Trash action

4. **Forensic Audit Trail** (`admin/audit_logs.php`):
   - Chronological logging of administrative actions, login attempts, payment verifications, and system optimizations
   - Filterable by Category (`AUTH`, `PAYMENTS`, `MEMBERS`, `STORAGE`, `SYSTEM`, `MAINTENANCE`)
   - Export audit logs to CSV for board reporting

5. **Disaster Recovery & Database Backups** (`admin/database_backup.php`):
   - 1-Click full SQL database dump generation and download (`.sql`)
   - Optimization tools per table to eliminate overhead

---

## 🏛️ Public Architectural Website & Flow

- **Homepage** (`public/homepage.php`):
  - Hero banner with UAP Quezon branding and Mt. Banahaw narrative
  - Live chapter member directory with search and PRC registration badges
  - Chapter news and events from MySQL database
  - Chapter milestones timeline (1984 chartering to present)
  - Corporate partners and sponsors dock
  - Direct inquiry submission form connected to `contact_inquiries`
- **Member Portal** (`member/`):
  - View unpaid, pending, and partial dues
  - Submit proof of payment (GCash, Maya, Bank Transfer)
  - Generate and print sequential official receipts (`UAPQ-2026-XXXXX`)
  - Manage directory profile and credentials
- **Admin Portal** (`admin/`):
  - Verify and approve/reject pending payment proofs
  - Approve new member registrations
  - Configure dues packages, QR codes, announcements, and chapter settings

---

## 📁 System Folder Structure

```
UAP-Quezon/
├── index.php                      (Central router)
├── schema.sql                     (Full MySQL database schema & seed data)
│
├── public/                        (Public website & assets)
│   ├── homepage.php               (Quezon architectural homepage)
│   ├── logo.jpg                   (Official UAP Quezon logo)
│   └── member_profile.php         (Public member profile view)
│
├── admin/                         (Admin & Super Admin Hub)
│   ├── dashboard.php              (Executive command center & Super Admin HUD)
│   ├── system_health.php          (★ Super Admin: Server, DB & storage health)
│   ├── crash_logs.php             (★ Super Admin: Live exception & error tracker)
│   ├── soft_deleted_files.php     (★ Super Admin: Trash bin quarantine & recovery)
│   ├── audit_logs.php             (★ Super Admin: Compliance activity log)
│   ├── database_backup.php        (★ Super Admin: 1-Click SQL dump & optimization)
│   ├── account_manager.php        (Account manager & role assignments)
│   ├── approvals.php              (Member registration verification)
│   ├── payments.php               (Dues payment proof verification)
│   ├── dues.php                   (Dues packages configuration)
│   ├── members.php                (Chapter directory)
│   ├── reports.php                (Financial reporting & CSV export)
│   ├── qr_codes.php               (Payment QR codes)
│   └── settings.php               (Chapter branding & website CMS)
│
├── member/                        (Member Self-Service)
│   ├── dashboard.php              (Assigned dues & payment status)
│   ├── pay.php                    (Payment submission with proof upload)
│   ├── history.php                (Payment history & official receipt viewer)
│   ├── website_directory.php      (Directory listing application & profile)
│   └── profile.php                (Profile photo & personal info)
│
├── auth/                          (Authentication)
│   ├── login.php                  (PRC ID & password login)
│   ├── register.php               (New architect registration)
│   └── logout.php                 (Session termination)
│
├── includes/                      (Shared Core Services)
│   ├── config.php                 (Database connection, base URLs, helpers)
│   ├── auth.php                   (Authentication guards: member, admin, super_admin)
│   ├── logger.php                 (★ Real-time exception & error logger)
│   ├── soft_delete.php            (★ Quarantine file & soft delete service)
│   ├── header.php                 (Adaptive navbar with Super Admin Engine)
│   ├── footer.php                 (Portal footer)
│   ├── theme.css                  (Design system with Quezon Gold & Mountain Green)
│   ├── icons.php                  (Scalable vector SVG icons)
│   ├── csrf.php                   (Security CSRF protection)
│   └── dues_service.php           (Receipt generator & balance calculations)
│
├── uploads/                       (Media Storage)
│   ├── trash/                     (★ Quarantined soft-deleted files)
│   ├── proofs/                    (GCash/Bank payment receipts)
│   ├── avatars/                   (Member profile photos)
│   ├── qr_codes/                  (Payment QR images)
│   ├── news/                      (Event announcement photos)
│   └── sponsors/                  (Partner logos)
│
└── logs/                          (Diagnostics)
    └── crash.log                  (Physical system error & crash log file)
```
