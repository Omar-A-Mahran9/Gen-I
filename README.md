# GEN-I Backend

Laravel 13 backend for GEN-I: the **Metronic 8.2 admin dashboard** (Arabic RTL / English LTR) and the versioned REST API (`/api/v1`).
The public React website is a separate application.

| | |
|---|---|
| Stack | PHP 8.3 · Laravel 13 · MySQL 8.4 · Sanctum · Spatie Permission · Metronic 8.2.0 (Bootstrap 5.3) · Vite |
| Admin | `/admin` (session login) |
| API | `/api/v1` (Sanctum bearer tokens) |
| Docs | [`docs/`](docs/) — architecture, decisions, database, API, Metronic migration, roadmap |

## Requirements

- PHP 8.3 with `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`
- Composer 2.x
- MySQL 8.4 (this team uses Homebrew `mysql@8.4` on port **3307**)
- Node.js 20+ for building the admin assets

## First-time setup

```bash
# 1. Dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
```

### 3. MySQL

Start your local server (Homebrew example, current session only):

```bash
brew services run mysql@8.4
```

Create the two databases and a dedicated, least-privilege user (run once as the MySQL admin user; choose your own strong password):

```sql
CREATE DATABASE geni         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE geni_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'geni'@'127.0.0.1' IDENTIFIED BY '<strong-password>';
GRANT ALL PRIVILEGES ON `geni`.*         TO 'geni'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `geni_testing`.* TO 'geni'@'127.0.0.1';
```

Then set `DB_PASSWORD` in `.env` (never commit it). The defaults in `.env.example` already point to `127.0.0.1:3307`, database `geni`, user `geni`.

### 4. Database schema, roles and storage link

```bash
php artisan migrate
php artisan db:seed          # roles & permissions only — safe to re-run, creates no users
php artisan storage:link     # public URLs for uploaded branding files
```

### 5. Create the first administrator

```bash
php artisan app:create-admin
```

You are prompted for name, email and password (input hidden; at least 10 characters with upper- and lower-case letters and a number). The account receives the protected **super-admin** role. No default or demo credentials exist.

### 6. Build the admin assets

```bash
npx vite build        # or: npm run build (npm ≥ 7)
```

> The repository's `.npmrc` sets `ignore-scripts=true`. With npm 6 this also blocks `npm run build`, which then exits silently — use `npx vite build` or upgrade npm.

### 7. Run

```bash
php artisan serve
```

Open **http://localhost:8000** → you are redirected to **http://localhost:8000/admin/login**. Sign in with the account from step 5 to reach the dashboard at **http://localhost:8000/admin**.

For live asset rebuilding during development run `npx vite` in a second terminal.

## Tests and quality checks

```bash
php artisan test                     # fast suite on in-memory SQLite
composer test:mysql                  # same suite on MySQL (geni_testing)
vendor/bin/pint --test               # code style
composer validate && composer audit
```

`tests/TestCase.php` refuses to run against any database other than `:memory:` or one ending in `_testing`, so the development database can never be wiped by tests.

## What is included

- Metronic 8.2.0 admin layout (header, permission-aware sidebar, toolbar/breadcrumbs, footer), light/dark/system theme, Arabic RTL default + English LTR, language switcher.
- Secure admin login (rate-limited, session regeneration, remember-me, inactive-account lock-out), logout.
- Users: server-side DataTable (search, filters, sorting, paging), create/edit/view, roles, activate/deactivate, admin password reset, delete — with privilege-escalation and last-super-admin protection.
- Roles & permissions (Spatie): grouped permission editor, protected super-admin role.
- Dashboard with live counts, recent activity and sign-ins, system status — no sample business data.
- Settings (native `settings` table + cached `SettingsService`): name, contacts, logos/favicon uploads, languages, theme, timezone and formats.
- Profile (name, email with password confirmation, language, password change).
- Database notifications (dropdown, list, mark read / all read) and an append-only audit log.
- API foundation: token issue/revoke, current user, consistent JSON error envelope, rate limiting, `Accept-Language`.
- **Business modules (Phase 2)** — admin pages and `/api/v1` endpoints for:
  - organizations and companies (members, addresses);
  - branches/stores (hours, location, stock);
  - the product catalog (categories, brands, products, variants, typed specifications, reviewed images);
  - policy versions (independent approval);
  - price lists with effective-dated prices (never overwritten);
  - inventory with an immutable, idempotent movement ledger.

  See [`docs/business-modules.md`](docs/business-modules.md).

See [`docs/implementation-roadmap.md`](docs/implementation-roadmap.md) for what comes next.

## Confidential documents

`docs/source/` holds confidential GEN-I source documents and is git-ignored. Never commit it.
