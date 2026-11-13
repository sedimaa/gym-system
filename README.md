# Gym Management System

A gym management system for a small local gym in our barangay. Laravel 13, MySQL, Blade + Tailwind,
built to run offline on the gym PC (Laragon on Windows). The full spec is in [`CLAUDE.md`](CLAUDE.md).

## Build progress

| # | Module | Status |
|---|---|---|
| 1 | Auth, roles, and layout | done |
| 2 | Plans | next |
| 3 | Members | |
| 4 | Memberships and payments | |
| 5 | Walk-ins | |
| 6 | Attendance | |
| 7 | Dashboard and reports | |
| 8 | Settings and backups | |
| 9 | Seeders and polish | |

## First-time setup (Windows + Laragon)

Requirements: Laragon with PHP 8.3+, MySQL, Composer, Node and Git.

1. Clone the repository into Laragon's `www` folder, e.g. `C:\laragon\www\gym-system`.
2. Start Laragon (**Start All**). Open **Terminal** from Laragon and go to the folder:
   ```
   cd C:\laragon\www\gym-system
   ```
3. Install and configure:
   ```
   composer install
   copy .env.example .env
   php artisan key:generate
   ```
4. Create the database: in Laragon, **Database** → (HeidiSQL) → create a database named `gym_system`
   (or run `mysql -u root -e "CREATE DATABASE gym_system"` in the Laragon terminal).
   `.env` already points to it (`root`, no password: Laragon's default).
5. Create the tables and the demo logins:
   ```
   php artisan migrate --seed
   ```
6. Build the front-end assets (needed once, and again after pulling changes):
   ```
   npm install
   npm run build
   ```
7. Open **http://gym-system.test** (Laragon creates this address automatically; reload Laragon if it
   doesn't work yet). Or run `php artisan serve` and open http://127.0.0.1:8000.

After the first `composer install`, remove the `/composer.lock` line from `.gitignore` and commit
the generated `composer.lock`.

### Demo logins

| Role | E-mail | Password |
|---|---|---|
| Admin / Owner | admin@gym.test | password |
| Staff | staff@gym.test | password |

Change these passwords before real use (top-right menu → **My profile**).

## Updating after new modules

```
git pull
composer install
php artisan migrate
npm install
npm run build
```

## Tests

```
php artisan test
```

Tests run on an in-memory SQLite database, so they never touch your MySQL data.
