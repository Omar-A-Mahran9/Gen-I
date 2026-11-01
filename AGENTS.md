# GEN-I Backend — Project Instructions

GEN-I is a technology retail and service platform. It covers:
- a product catalog and comparison,
- store reservation,
- repair booking,
- corporate RFQ and quotations,
- a governed AI assistant.

This repository is the **Laravel 13 backend**: a Metronic admin dashboard plus a REST API at `/api/v1`. The public React frontend is a separate application and is out of scope here.

## Read first
- `docs/business-decisions.md`: approved technical decisions (TD-*) and open decisions (OT-*, BD-*). Approved decisions override the technology proposals in the source PDFs.
- `docs/architecture.md`, `docs/module-map.md`, `docs/database-design.md`, `docs/api-contracts.md`, `docs/metronic-migration.md`.
- `docs/implementation-roadmap.md`: the current phase and its gates. **Do not start a phase without the owner's explicit approval.**
- `docs/business-requirements.md`: consolidated FR/NFR/business rules, with sources.

## Hard rules
- Work only inside `Gen-I/`. The sibling folder `twasol_backend_backup_20261101_025513/` is **read-only**. It may be read as a reference for Metronic UI assets, never modified. Never copy its business code, schema, routes, permissions or branding.
- Git must run inside `Gen-I/` only. Check that `git rev-parse --show-toplevel` ends in `/Gen-I` before any git command. Never touch the home-directory repo or the Twasol repo. Never force push without explicit approval.
- Branches: `main` holds stable releases, `develop` holds integration work. Commit each completed and tested phase separately.
- Do not invent business workflows, providers, credentials, policies or numeric commitments. Unapproved items stay marked *proposed* or *open*.
- Keep secrets out of Git. `.env` is never committed, and seeders read bootstrap credentials from env.
- Verify every package against PHP 8.3 / Laravel 13 before installing it.

## Stack and conventions
- PHP 8.3 (`php` on PATH), Composer via `php ~/composer.phar`, MySQL 8.4 (local Homebrew on port 3307). SQLite is allowed for portable tests.
- Traditional MVC:
  - Controllers are thin, split into `Admin\*` and `Api\V1\*`.
  - Validation lives in Form Requests.
  - Authorization uses Policies/Gates plus Spatie permissions (`<module>.<resource>.<action>`).
  - Business logic lives in `App\Services\<Domain>` and is shared by Admin and API controllers.
  - Use Events, Jobs and Notifications for side effects.
- One `users` table for staff and customers. Roles and memberships decide access. There is no blanket `Gate::before` bypass.
- State changes go through Services inside DB transactions, with row locks on contended resources.
- Money uses DECIMAL and string amounts in the API. Never use floats.
- Locales: Arabic `ar` is the default and RTL; English `en` is LTR. Admin supports light and dark themes.
- Admin UI: Metronic 8.2.0 static bundles live in `public/assets/admin/`. Page plugins load only on the pages that use them. Load no CDN scripts globally. **Never load Tailwind on admin pages.** Tailwind (`resources/css/app.css`) is for non-admin pages only.
- Tests: `php artisan test` must pass, and Pint must be clean, before each phase commit.

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
