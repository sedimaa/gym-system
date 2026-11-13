# CLAUDE.md: Gym Management System

A gym management system for a small local gym in our barangay (Philippines).
The first build is a **working mock-up with realistic seeded data** for an owner demo. It is real,
working software (not static screens) and is meant to grow into the final system.

## Stack and environment
- Laravel (latest stable), PHP 8.3+, MySQL
- Blade + Tailwind CSS, Laravel Breeze (Blade stack) for auth
- Chart.js for charts, bundled locally through Vite (no CDN)
- Target machine: Windows with Laragon (MySQL, Composer, Node). Runs on the gym PC at localhost;
  may be hosted online later
- **Offline-first:** nothing may depend on a constant internet connection (no CDN fonts, scripts or
  APIs at runtime)
- Timezone: `Asia/Manila` (set in `config/app.php`). Currency: Philippine peso, shown as `₱1,234.00`
- Money is stored as `DECIMAL(10,2)`

## Packages
| Purpose | Package |
|---|---|
| Auth scaffolding | laravel/breeze (Blade) |
| Activity log | spatie/laravel-activitylog |
| Excel export | maatwebsite/excel |
| PDF export, receipts | barryvdh/laravel-dompdf |
| Backups | spatie/laravel-backup |
| Charts | chart.js (npm) |

Any package swap must be raised with the user first.

## Scope

### In scope
1. Member management: register, auto `member_code`, search/filter, edit, profile, deactivate
2. Membership plans (admin-managed): monthly, quarterly, yearly, per-session, day pass
3. Payments, with automatic create/extend of memberships, receipts and history
4. Walk-ins (day pass), stored in a separate `walkins` table
5. Manual attendance with expired-member deny/override
6. Expiry tracking (expiring soon and expired, with a Renew shortcut)
7. Dashboard and reports (income, attendance, new members/renewals), Excel and PDF export
8. Settings and safety: gym info, rules, manual backup/restore, scheduled daily backup

### Out of scope (do NOT build)
- QR/barcode member IDs, QR generation, ID card printing, check-in scanner page
- Gate or turnstile control
- SMS or Messenger reminders
- Member self-service portal

### Keep the design ready for later
- `members.member_code` is unique, format `GYM-0001`
- `attendance.status`: `allowed`, `denied`, `manual`
- `attendance.source`: `manual` now, `scanner` later

Do not invent extra features. Ask the user before any major decision not covered here.

## Roles
| | Admin/Owner | Staff |
|---|---|---|
| Register/edit members, view members | yes | yes |
| Record payments, walk-ins, attendance | yes | yes |
| Add/correct attendance records (with required note) | yes | no |
| Deactivate members | yes | no |
| Delete any record | yes | no |
| Plans, prices, settings | yes | no |
| Backups and restore | yes | no |
| Staff accounts | yes | no |

- Every staff member has their own login. No shared accounts.
- `users.role` is `admin` or `staff`. Permissions go through **policies and gates**, never ad-hoc
  role checks in views or controllers.
- **Activity log** (who, what, when) for: payments, edits, manual attendance, plan changes,
  deactivations.

## Business rules
- **Membership status is computed, never stored by hand.** From the expiry date:
  - `active`: expiry date + grace days >= today, and not within the expiring-soon window
  - `expiring`: expiry is within `expiring_soon_days` (default 7) of today
  - `expired`: today > expiry date + `grace_days` (default 0)
  - `inactive`: the member is deactivated (`is_active = false`), which overrides the others
- **Expiry date** = start date + plan `duration_days`.
- **Renewal start** follows the `renewal_start_mode` setting:
  - `from_payment_date` (default): the new period starts on the payment date
  - `from_old_expiry`: the new period starts the day after the old expiry (if it is still in the
    future; otherwise from the payment date)
- **Saving a member payment creates or extends the membership** in one database transaction.
- **Walk-ins** default their amount to the day-pass plan price. The amount can only be edited if
  the day-pass pricing setting allows it. They count toward income and the daily attendance total,
  labeled separately from members in reports.
- **Attendance** takes date and time from the server clock. Expired members show a red warning, and
  staff either deny (logged `denied`) or override with a required note (logged `manual`).
- Deactivating keeps all records. Use an `is_active` flag or soft deletes where records must be kept.

## Decisions (confirmed by the user)
- **Per-session plan** is a bundle of X sessions (each check-in uses one session), not a single visit.
- **Plan upgrades/changes** start from the payment date; no credit for remaining days.
- **Staff can edit member details**; only the admin deactivates members.
- **Restore** is admin-only, needs a typed confirmation, and makes an automatic backup first.
- Accounts are created by the admin (no public registration). No e-mail password reset or e-mail
  verification (gym PC may be offline); the admin resets forgotten passwords. Accounts are
  disabled (`users.is_active`), never deleted, and an admin can't disable or demote themselves.
- Fonts: system font stack (no web fonts), to stay offline-first.

## Decisions made during the build (change if the owner disagrees)
- Extra status **"No plan yet"** (`none`) for members who registered but never paid.
- "Expiring soon" also covers the grace period after expiry (still allowed in, shown in amber).
- Member payments: amount defaults to the plan price but can be changed (discounts); the receipt and
  activity log show it. Staff payments are always dated today; only the admin can backdate.
- Day passes are only sold as walk-ins, never as a member plan. Deactivated members can't pay or
  check in until the admin reactivates them.
- Deleting (admin): only a member's **latest** payment, which also removes the period it created;
  members only if they have no payments or attendance (otherwise deactivate); plans only if never sold.
- Attendance: one check-in per member per day. Overrides and admin-added records never use a
  per-session bundle session; deleting a check-in gives its session back.
- Reports are open to both roles (they are not on the admin-only list); gate `view-reports` in
  `AppServiceProvider` makes them admin-only with a one-line change. The activity log page is admin-only.
- Backups: database + uploaded logo. The daily backup runs hourly but only once per day
  (`gym:backup --if-missing-today`). Restore extracts the dump, makes a safety backup, then replaces
  the database and logs everyone out.

## Data model
Tables: `users` (with `role`), `members`, `plans`, `memberships`, `payments`, `walkins`,
`attendance`, `settings` (key/value), `activity_logs`.
- Foreign keys on every relationship
- Indexes on `members.member_code`, `memberships.expiry_date`, and the attendance date/time column

## Settings (key/value, admin only)
Gym name, logo, receipt details, `grace_days` (0), `expiring_soon_days` (7),
`renewal_start_mode` (`from_payment_date`), day-pass pricing rule, backup folder path.

## Code conventions
- **Thin controllers.** Membership, renewal and status logic lives in a service class
  (`app/Services/MembershipService.php`).
- **Form Request** classes for all validation.
- **Eloquent relationships** for all associations; avoid raw queries unless needed for reports.
- **Policies/gates** for all permission checks.
- Responsive UI: front-desk desktop first, phone second.

## Testing
Feature tests (PHPUnit) must cover:
- Expiry status (active, expiring, expired, grace days, inactive)
- Renewal in both `renewal_start_mode` values
- The walk-in flow
- Role restrictions (staff blocked from admin-only actions)

Run `php artisan test` after every module.

## Seed data
Admin user, staff user, 5 plans, ~40 realistic Filipino-named members with a mix of active,
expiring and expired statuses, 3 months of payments, attendance history, and some walk-ins.

## Workflow
Build one module at a time, in this order:
1. Auth, roles, and layout
2. Plans
3. Members
4. Memberships and payments (with expiry logic)
5. Walk-ins
6. Attendance
7. Dashboard and reports
8. Settings and backups
9. Seeders and polish

After each module: run migrations and tests, explain how to open and try it, **commit to Git with a
clear message, then stop and wait for the user's go-ahead** before starting the next module.