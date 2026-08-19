# Deploying Attendance Device Enforcement to Live

## What changed
- New DB table `nexopos_attendance_devices` + 2 columns on `nexopos_attendance` (`clock_in_device_id`, `clock_out_device_id`)
- New menu `Attendance → Clock-in Devices` (visible only to `admin` / `nexopos.developer`)
- Frontend assets rebuilt (`public/build/` already committed — no `npm` needed on live if you `git pull`)

## Do you need to run `php artisan migrate`?
**Yes.** Run the project's update script instead — it handles both Laravel and NexoPOS migrations + cache clear:

```bash
php run-pending-migrations.php
```

This does:
1. `php artisan migrate --force` (applies `2026_08_18_000000_create_attendance_devices_table.php`)
2. NexoPOS pending migrations (if any)
3. `cache:clear`

You can also run `php artisan migrate --force` alone, but `run-pending-migrations.php` is the repo's intended command (see its header comment — ideal for Hostinger/shared hosting).

## Recommended deploy steps (from project root, SSH)

```bash
# 0. Optional: backup
# Hostinger → phpMyAdmin → Export, or via CLI if available:
# mysqldump -uUSER -p DBNAME > backup_$(date +%F).sql

# 1. Maintenance mode (optional but avoids writes mid-migration)
php artisan down

# 2. Get the code
git fetch origin
git checkout feat/attendance-device-enrollment
# or if live is on main/ui/pos-revamp-loyverse and you merge:
# git checkout main && git merge feat/attendance-device-enrollment --no-ff

git pull

# 3. Install deps only if composer.json/lock changed (this feature didn't change them)
# composer install --no-dev --optimize-autoloader

# 4. Run migrations (REQUIRED)
php run-pending-migrations.php
# Expected output:
# [1/3] Running standard Laravel migrations... → ...2026_08_18_000000_create_attendance_devices_table
# [2/3] Checking for pending NexoPOS migrations...
# [3/3] Clearing cache...

# 5. Clear compiled caches (run-pending-migrations already clears cache, but for good measure)
php artisan optimize:clear
# or at minimum:
# php artisan view:clear
# php artisan config:clear
# php artisan route:clear

# 6. Verify
php artisan route:list --path=attendance
# should list: attendance/devices, attendance/enroll-device, attendance/generate-enrollment-code

# 7. Back up
php artisan up
```

No `npm run build` needed on live — `public/build/manifest.json` + hashed assets are already committed. If you prefer to build on the server, you *can* run `npm ci && npm run build` instead.

## Post-deploy check
1. Sign in as `admin` → `Attendance → Clock-in Devices` should be visible.
2. Sign in as `ciara`/`jane_23`/`RenzoOng` (cashier) → that submenu should be **hidden**.
3. On the store tablet, open `Attendance → Clock In/Out` → if unregistered, it shows “Device Not Registered”. Generate a code on the admin page and enroll the tablet.
4. Cashier on a personal phone → clock-in should return `403 Clock in/out is only available on the store's registered device.`

## Rollback
```bash
git checkout <previous-branch>
php artisan migrate:rollback --step=1  # rolls back 2026_08_18_000000_create_attendance_devices_table
php artisan optimize:clear
php artisan up
```

## Notes
- No new `attendance.*` permissions were added, so no permission re-seed is needed.
- Clearing browser localStorage on the tablet loses the device identity → re-enroll with a fresh code.
