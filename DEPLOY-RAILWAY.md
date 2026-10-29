# 🚀 Deploying UAP Quezon to Railway

This guide walks you through deploying the complete **UAP Quezon Chapter Platform** (React 18 + Vite Frontend, PHP REST API, Member & Admin Portal, and MySQL Database) to [Railway](https://railway.app).

---

## 🏗 Architecture Overview

- **Frontend**: React 18 + Vite Single Page Application (`website/`) compiled into static assets served at the root `/`.
- **Backend**: PHP 8.2 with Apache serving the Member Portal (`/member`), Admin Dashboard (`/admin`), Auth routes (`/auth`), and REST API (`/api`).
- **Database**: Cloud MySQL database provisioned directly on Railway with auto-migrations and seed scripts on container startup.
- **Dynamic Port**: Configured to dynamically bind to Railway's `$PORT` environment variable.

---

## 📋 Step-by-Step Deployment Guide

### Step 1: Push Code to GitHub

Make sure your changes are pushed to your GitHub repository:
```bash
git add .
git commit -m "Add Dockerfile, React website, and Railway deployment config"
git push origin main
```

---

### Step 2: Create a New Project on Railway

1. Go to [Railway Dashboard](https://railway.app/dashboard).
2. Click **"+ New Project"**.
3. Select **"Deploy from GitHub repo"** and choose your `UAP-Quezon` repository.

---

### Step 3: Add a MySQL Database on Railway

1. In your Railway project view, click **"+ Create"** (or press `Ctrl + K` / `Cmd + K`).
2. Select **"Database"** → **"Add MySQL"**.
3. Railway will provision a managed MySQL database and automatically inject the connection variables:
   - `MYSQLHOST`
   - `MYSQLPORT`
   - `MYSQLDATABASE`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`
   - `DATABASE_URL`

> [!NOTE]
> The application's `includes/config.php` automatically detects Railway's `MYSQLHOST` and `DATABASE_URL` environment variables. You do not need to configure them manually!

---

### Step 4: Automatic Database Migration & Seeding

When the Docker container starts up, `docker-entrypoint.sh` automatically executes `database/setup.php`, which runs:
- `database/migrations/` (Creates all tables: users, dues, payments, qr_codes, site_settings, sponsors, news, website_members, directory_applications).
- `database/seeds/` (Seeds default admin, initial dues, chapter settings, sponsors, and announcements).

---

### Step 5: (Critical) Add a Persistent Volume for Image Uploads

In Railway, containers use ephemeral storage by default (any uploaded files are wiped whenever Railway redeploys or restarts your service). **Attaching a Persistent Volume preserves all member profile photos, showcase images, receipts, and logos permanently.**

#### How to Add the Volume in Railway:
1. Open your Railway Project dashboard and click on your **Web Service** container.
2. Navigate to the **"Volumes"** tab (or click the **"+ Create"** button in your canvas and select **"Volume"**).
3. Click **"Add Volume"** and set the **Mount Path** to:
   ```
   /var/www/html/uploads
   ```
4. Set your desired disk size (e.g. `1 GB` to `5 GB` — you can always expand it later).
5. Click **"Save"** or **"Attach Volume"**.

#### What gets preserved inside `/var/www/html/uploads`:
- `/var/www/html/uploads/avatars/` — Admin & Member profile pictures.
- `/var/www/html/uploads/members/` — Architect Directory project showcase photos.
- `/var/www/html/uploads/sponsors/` — Sponsor & Partner corporate logos.
- `/var/www/html/uploads/receipts/` — Member payment receipts and GCash/Maya transfer proofs.

> [!TIP]
> The container's startup script (`docker-entrypoint.sh`) automatically creates all these subdirectories and configures Linux permissions (`chown www-data:www-data`, `chmod 775`) on every boot, so volume mounts work out of the box without any manual Linux configuration!

---

### Step 6: Generate Your Public Domain

1. In your Railway Web Service settings, go to the **"Networking"** tab (or **"Settings"** → **"Public Networking"**).
2. Click **"Generate Domain"** (e.g. `uap-quezon-production.up.railway.app`).
3. Open the domain in your browser!

---

## 🔑 Default Credentials

- **Public Website**: `https://your-domain.up.railway.app/`
- **Member / Admin Login**: `https://your-domain.up.railway.app/auth/login.php`
- **Default Admin Account**:
  - **ID Number**: `ADMIN001`
  - **Password**: `admin123`
  *(Be sure to change the password immediately after logging in!)*

---

## 🛠 Local Docker Testing (Optional)

If you have Docker Desktop installed locally, you can build and test the image:

```bash
# Build image
docker build -t uap-quezon .

# Run container
docker run -p 8080:80 \
  -e DB_HOST=host.docker.internal \
  -e DB_NAME=dues_system \
  -e DB_USER=root \
  -e DB_PASS= \
  uap-quezon
```
Then visit `http://localhost:8080`.
