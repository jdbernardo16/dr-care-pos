# Backup & Restore

## Overview

This POS uses `spatie/laravel-db-snapshots` for database snapshots (DB only). A custom `backup:full` command also archives uploaded images. Backups are stored in `storage/snapshots/`.

| What | Backed up? | How |
|------|-----------|-----|
| Products, orders, customers, settings | ✅ | `snapshot:create` (MySQL dump) |
| Product images, uploaded media | ✅ | `backup:full` tars `storage/app/public/` |
| Both together | ✅ | `php artisan backup:full` |
| Automation | ✅ | Single cron entry runs all scheduled tasks |

---

## Hostinger cPanel Setup (One Time)

### Step 1: Find the correct PHP path

In cPanel, go to **Advanced → Cron Jobs**. At the bottom, under **Add New Cron Job**, the page shows your PHP path. It's usually:
```
/usr/bin/php
```
Or look under **Advanced → PHP Selector** to confirm your PHP version and path.

If you have SSH access, run `which php` to confirm.

### Step 2: Find the correct project path

Your project is likely at:
```
/home/username/htdocs/dr-care-pos/
```

Replace `username` with your actual cPanel username. To confirm you have the right path, check if this file exists:
```
/home/username/htdocs/dr-care-pos/artisan
```

### Step 3: Add the cron job

1. Go to **cPanel → Advanced → Cron Jobs**
2. Under **Add New Cron Job**:
   - **Common Settings**: `Once Per Minute` (`* * * * *`)
   - **Command**:
     ```
     /usr/bin/php /home/username/htdocs/dr-care-pos/artisan schedule:run >> /dev/null 2>&1
     ```
3. Click **Add New Cron Job**

> **⚠️ Important:** The schedule MUST run every minute. Even though most tasks run hourly/daily/weekly, Laravel needs to evaluate every minute to catch the right timing. The cron health-check also runs every minute — without it, the POS dashboard will show a cron warning.

### Step 4: Verify it's working

Wait 2–3 minutes, then check:

- **Option A — Dashboard:** Look for a cron warning banner at the top of the POS dashboard. If there's no warning, it's working.
- **Option B — SSH or terminal:**
  ```bash
  php artisan schedule:list
  # You should see all tasks listed with their next due time
  ```

---

## Automated Backup Schedule

The following tasks run automatically once the cron job is set up:

| Frequency | Task | What it does |
|-----------|------|-------------|
| Every minute | Cron health-check | Prevents dashboard warning |
| Every 5 min | Detect scheduled transactions | Runs user-created scheduled transactions |
| Hourly | Check symbolic links | Ensures storage link isn't broken |
| Hourly | Combined product history | Generates daily product history summary |
| Daily (00:02) | Detect low stock products | Triggers low-stock notifications |
| Daily (00:05) | Auto-stocking procurement | Updates procurement status |
| Daily (13:00) | Track layaway orders | Sends layaway reminders |
| Daily (14:00) | Clear expired hold orders | Releases held order stock |
| Daily (15:00) | Purge order storage | Cleans up temporary order data |
| Daily (08:00) | Check for updates | Checks if a new version is available |
| **Weekly (Sun 02:00)** | **Full backup** | **DB snapshot + image archive, keeps 60 days** |
| Weekly | Clear module temp files | Cleans up after module installs |
| Monthly / Custom | Recurring transactions | Based on user-defined schedules |

---

## Manual Commands

### Create a full backup (DB + images)

```bash
php artisan backup:full
```

### Create only a DB snapshot

```bash
php artisan snapshot:create my-snapshot-name
```

### List available snapshots

```bash
php artisan snapshot:list
```

### Restore a DB snapshot

```bash
php artisan snapshot:load my-snapshot-name
```

### Restore images from a backup archive

```bash
# Extract the latest image archive to the public storage
tar -xzf storage/snapshots/images-backup-*.tar.gz -C storage/app/public/

# Ensure storage link exists
php artisan storage:link
```

### Full manual restore from combined backup

```bash
# 1. List snapshots to find the one you need
php artisan snapshot:list

# 2. Restore the database
php artisan snapshot:load backup-20260728-020001

# 3. Extract images (match the date to your snapshot)
tar -xzf storage/snapshots/images-backup-20260728-020001.tar.gz -C storage/app/public/

# 4. Link storage
php artisan storage:link
```

---

## Downloading Backups Off-Server

All backups are stored at `storage/snapshots/`. Periodically download them for off-site safekeeping. You can use:

- **cPanel → File Manager** — navigate to `storage/snapshots/`, select files, click **Download**
- **FTP/SFTP** — connect to your hosting and download the entire folder
- **SSH**:
  ```bash
  tar -czf offsite-backup.tar.gz -C storage/snapshots .
  # Then download offsite-backup.tar.gz
  ```

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| Dashboard shows cron warning | Cron job not set or wrong path | Verify the cron job in cPanel, check PHP path and project path |
| Snapshot command fails (MySQL) | Database credentials mismatch | Check `.env` DB settings match Hostinger's MySQL |
| "No such file or directory" in cron | Wrong project path in cron command | Verify `artisan` exists at the path you entered |
| Images not showing after restore | `storage:link` not run | Run `php artisan storage:link` |
