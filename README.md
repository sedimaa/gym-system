# Gym Management System

A gym management system for a small local gym in our barangay. Laravel 13, MySQL, Blade + Tailwind,
built to run offline on the gym PC (Laragon on Windows). The full spec is in [`CLAUDE.md`](CLAUDE.md).

## What it does

| Area | Who | What |
|---|---|---|
| Dashboard | everyone | Today's income and visits, members by status, 30-day income and 14-day visit charts, expiring-soon list with Renew, recent payments |
| Members | everyone | Register (auto code `GYM-0001`), search by name/code/phone, filter by status, edit, profile with membership, payments and attendance. Admin: deactivate/reactivate, delete (only members with no history) |
| Payments | everyone | Record a payment → the membership period is created automatically. 80 mm PDF receipt. GCash/Maya/bank need a reference no. Admin: backdate, delete the latest payment |
| Walk-ins | everyone | Day-pass guests, priced from the Day Pass plan (fixed unless Settings allow changes) |
| Attendance | everyone | Check-in desk: green for active members, red warning for expired ones with **Deny** or **Override (with note)**. Per-session bundles use one session per visit. Admin: add missed records, correct, delete |
| Expiry | everyone | Expiring soon / expired lists with a Renew button |
| Reports | everyone | Income, attendance, new members & renewals; Excel and PDF export |
| Plans & prices | admin | Monthly, quarterly, yearly, per-session bundle, day pass |
| Staff accounts | admin | One login per staff member; disable, reset passwords |
| Settings | admin | Gym info, logo, receipt text, rules, grace days, expiring-soon window, renewal mode, walk-in price rule, backup folder |
| Backups | admin | Back up now, download, restore (type RESTORE; a safety backup is made first), daily automatic backup |
| Activity log | admin | Who did what and when: payments, edits, overrides, plan and settings changes, deactivations |

## First-time setup (Windows + Laragon)

Requirements: Laragon with PHP 8.3+, MySQL, Composer, Node and Git. In Laragon → PHP → Extensions,
make sure **zip, gd, fileinfo, intl, mbstring, openssl, pdo_mysql** are checked.

1. In the Laragon terminal:
   ```
   cd C:\laragon\www
   git clone https://github.com/sedimaa/gym-system.git
   cd gym-system
   composer install
   copy .env.example .env
   php artisan key:generate
   ```
2. Create the database `gym_system` (Laragon → Database → right-click the connection → Create new → Database),
   or run `mysql -u root -e "CREATE DATABASE gym_system"`.
3. Load the demo data and build the screens:
   ```
   php artisan migrate --seed
   npm install
   npm run build
   ```
4. Open **http://gym-system.test** (right-click Laragon → Reload if it doesn't open yet),
   or run `php artisan serve` and open http://127.0.0.1:8000.

After the first `composer install`, remove the `/composer.lock` line from `.gitignore` and commit
the generated `composer.lock`.

### Demo logins

| Role | E-mail | Password |
|---|---|---|
| Admin / Owner | admin@gym.test | password |
| Staff | staff@gym.test | password |

The demo has 40 members (active, expiring soon, expired, no plan yet and deactivated), about
3 months of payments, attendance and walk-ins. Dates are relative to the day you run the seeder, so
re-run `php artisan migrate:fresh --seed` before a demo to make it look current.

## Backups

**1. Tell the system where `mysqldump` is.** Find Laragon's MySQL folder, e.g.
`C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin`, and put it in `.env`:
```
DB_DUMP_BINARY_PATH="C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin"
```
Then try **Admin → Backups → Back up now**.

**2. Pick a safe backup folder** in **Settings → Backup folder**, ideally another drive or a USB
drive (e.g. `D:\GymBackups`). Default: `storage\app\backups` inside the project.

**3. Turn on the daily automatic backup** with Windows Task Scheduler. Open **Command Prompt as
Administrator** and run (adjust the PHP and project paths if yours differ):
```
schtasks /create /tn "Gym System Backup" /sc hourly /ru SYSTEM /tr "\"C:\laragon\bin\php\php-8.3.36-Win32-vs16-x64\php.exe\" \"C:\laragon\www\gym-system\artisan\" gym:backup --if-missing-today"
```
It runs every hour but only makes a backup if there isn't one from today yet, so a backup still
happens on days the PC is switched on late. MySQL (Laragon) must be running. Old backups are
thinned out automatically (all from the last 7 days, then daily for 30 days, then weekly/monthly).

**Restore:** Backups → Restore → type `RESTORE`. The current data is backed up first, then replaced
by the chosen backup, and everyone logs in again.

## Going live (real use, no demo data)

```
php artisan migrate:fresh --seeder=EssentialsSeeder
```
This creates only the two logins and the five plans. Then:
1. Log in as `admin@gym.test`, open **My profile** and change the e-mail and password.
2. **Settings**: gym name, address, logo, receipt text, rules, grace days, renewal mode.
3. **Plans & prices**: adjust prices.
4. **Staff accounts**: change the staff login's password, or add one account per staff member.

`migrate:fresh` deletes everything in the database. Only run it before real data exists.

## Updating

```
git pull
composer install
php artisan migrate
npm install
npm run build
```

## How membership status works

Status is computed from the member's latest membership period (never typed in by hand):

| Status | Meaning |
|---|---|
| Active | Expiry is more than *expiring-soon days* (default 7) away |
| Expiring soon | Expiry is within that window, or within the grace period after it |
| Expired | Past expiry + grace days (default 0), or a per-session bundle with no sessions left |
| Deactivated | The admin deactivated the member (overrides the rest) |
| No plan yet | Registered but never paid |

Expiry = start + plan days. Renewing the **same plan** starts on the payment date (default) or the day
after the old expiry (Settings → renewal mode). **Changing plans** always starts on the payment date,
with no credit for remaining days.

## Tests

```
php artisan test
```
Tests run on an in-memory SQLite database, so they never touch your MySQL data. (The backup test
needs the `sqlite3` command-line tool and is skipped if it isn't installed.)
