# GEN-I — Implementation Roadmap

| | |
|---|---|
| Updated | 2026-11-01 |
| Status | Proposed — **awaiting explicit approval** before Phase 1 starts |
| Note | The owner's instruction message was truncated during Phase 1 ("Verify `.git…"). Phase 1 below completes it; Phases 2+ are drafted from the audit plan and S3 module order and **need confirmation** |

## Ground rules (every phase)

- Work only inside `Gen-I/`. Never modify `twasol_backend_backup_20261101_025513/` or the home-directory Git repo.
- Each phase ends with:
  - `php artisan test` green,
  - `pint --test` clean,
  - docs updated,
  - one commit (or a small series) on `develop`,
  - a review stop for the owner.
- Dependencies are verified (Packagist constraints vs Laravel 13 / PHP 8.3) and listed before installation.
- No business module starts until its blocking decisions in `business-decisions.md` are resolved, or the owner explicitly accepts synthetic fixtures (S3 RK02 approach).
- No force push. `main` is updated only from a tested `develop` at a release point.

## Phase 0 — Architecture Finalization  *(this deliverable)*

- [x] Architecture documentation (`docs/architecture.md`).
- [x] Module map, database strategy, API conventions, Metronic migration plan.
- [x] Record approved technical decisions (TD-01…TD-17) and open decisions (`docs/business-decisions.md`).
- [x] Identify blocking business questions (BD-01…BD-14).
- [x] Confirm access to source documentation: five PDFs + one XLSX found in `~/Downloads/` (outside the repo); text extracted and reviewed.
- [ ] Owner decision on OT-09 (copy source docs into `docs/source/` and whether to commit them).
- [ ] Owner approval of this roadmap.

## Phase 1 — Git and Project Foundation

1. **Repository**
   - `git init -b main` inside `Gen-I/`.
   - Verify `git rev-parse --show-toplevel` = `…/Gen-I` and that `Gen-I/.git` exists. This ensures the home repo is no longer used.
   - Review `.gitignore` (`.env`, `vendor`, `node_modules`, `public/build`, `database/*.sqlite`, `.DS_Store`). Add `docs/source/` if OT-09 says don't commit.
2. **Remote**
   - `git remote add origin https://github.com/Omar-A-Mahran9/Gen-I.git`.
   - `git ls-remote origin` to confirm reachability and detect existing commits/branches.
   - **If the remote has commits: stop and report**, with no push and no merge until the owner decides.
3. **Initial commit**
   - The Laravel skeleton + `docs/` + updated `CLAUDE.md`.
   - Then create `develop` from `main`.
   - Push `main` and `develop` (no force).
4. **Tooling**
   - Install Laravel Boost (dev) (TD-17), then re-read the generated guidelines. Boost only rewrites its own `<laravel-boost-guidelines>` block; the GEN-I section in `CLAUDE.md` must survive. Verify this.
5. **Environment**
   - `.env.example`: `APP_NAME="GEN-I"`, `APP_LOCALE=ar`, `APP_FALLBACK_LOCALE=en`, `DB_CONNECTION=mysql`, `DB_PORT=3307` (local Homebrew MySQL 8.4) with **empty** credentials.
   - Local `.env` adjusted to match (never committed).
   - Create the local `geni` and `geni_testing` databases.
6. **Dependencies (verified 2026-11-01, to be re-checked at install time)**
   - `laravel/sanctum` via `php artisan install:api` (creates `routes/api.php`; prefix set to `api/v1`).
   - `spatie/laravel-permission` (publish config + migration; adjust key types per OT-01).
7. **Verification:** `php artisan about`, `migrate:fresh` on MySQL, `php artisan test`, `pint --test`.
8. **Commit:** "Phase 1: repository, environment and core dependencies".

*Blocking: none beyond approval. OT-07 (CI/Docker) can follow later.*

## Phase 2 — Metronic Admin Foundation  *(drafted — confirm)*

- Copy assets per the manifest in `docs/metronic-migration.md` §1. Write `VERSION` with checksums. Twasol unchanged-check before and after.
- Separate Vite admin entry (`resources/css/admin.css`, `resources/js/admin/app.js`), Tailwind-free.
- Blade layout components: app layout, auth layout, header, sidebar (config-driven), toolbar/breadcrumbs, footer, theme switching, responsive drawer.
- Locale: `SetLocale` middleware, language switch route, `lang/ar` + `lang/en` (publish framework lang files, Arabic validation messages).
- Placeholder GEN-I branding (BD-12 pending).
- Tests: layout renders in `ar`/RTL and `en`/LTR, the menu filters by permission, and no Tailwind in the admin CSS build.

## Phase 3 — Identity, Access and Organization (MOD01 core)  *(drafted — confirm)*

- **Decisions needed first:** OT-01, OT-02, OT-03, OT-04; BD-14 (initial role catalogue; a bootstrap `system-administrator` role is enough to start).
- `users` extensions, plus organizations, branches, departments, companies, company_members, addresses, consents (per S2 §9, MySQL-adapted).
- Admin login/logout/password reset (throttled), profile, users CRUD, roles & permissions UI, activation/deactivation.
- Spatie permissions seeded per module. Policies with scope checks. `audit_events` + an audit writer for auth and permission changes.
- API: `/api/v1/auth/*`, `/api/v1/me`, consents.
- Tests: the authorization matrix and cross-scope denial (N01).

## Phase 4 — Shared Platform Services and UI Kit  *(drafted — confirm)*

- `SettingsService` + settings admin page (general, localization, theme).
- In-house `DataTableService` (server-side paging/search/sort/filter on allow-listed columns) + `<x-admin.datatable>` + `datatable.js`.
- AJAX form helpers, SweetAlert/toast wrappers, Laravel 422 error rendering, image/file upload component (private disk, validation), Select2 AJAX component.
- API foundation: error envelope handler, request-ID middleware, `Idempotency-Key` middleware + `idempotency_keys` table, named rate limiters.
- Notifications base (database + mail channels), `notification_deliveries`, failed-jobs admin view (OT-11 outbox approach).
- Tests for each service.

**→ End of foundation.** Owner review before any business module.

## Phase 5+ — Business modules (each a separate phase, gated by decisions)

| Order | Phase | S3 module | Must be decided first |
|---|---|---|---|
| 5 | Catalog, brands, variants, attributes, media, stores, offers content, pricing | MOD02 (+ pricing part of MOD03) | BD-03, BD-04, OT-05; approved category attribute vocabularies |
| 6 | Comparison, availability/freshness, inventory & store reservations | MOD03 | BD-01, BD-03 |
| 7 | Repairs: services, models, resources, slots, bookings, jobs, estimates & consent, parts, warranty, events | MOD04 | BD-05, BD-08 |
| 8 | Corporate RFQ and versioned quotations | MOD05 | BD-02 |
| 9 | Knowledge base | MOD06 (part) | BD-07 (data classes), BD-13 |
| 10 | AI gateway, prompt/role registry, assistant runs, tools, audits, human review | MOD06/MOD07 | BD-07, OT-10 |
| 11 | Analytics & reporting (M01–M20, R01–R10) | MOD09 | BD-09 |
| 12 | CSV & Word exports | MOD10 | Approved templates and branding |
| 13 | Production continuity: deployment, backups, monitoring, runbooks | MOD11 | BD-11, OT-07 |

Admin workspaces (MOD08) are delivered inside each module phase, not as a separate phase.

**Conditional scope** (checkout/payments, returns/refunds, corporate ordering/credit, live trade-in/financing/carrier integrations) is **not scheduled**. It is activated only by a sized change request (S3 §4).

## Schedule note

The S3 agile plan baselines Sprint 1 at 2026-11-01 → 2026-11-12 with PostgreSQL, Docker and CI in task S1-D1-01. Approved decisions TD-01/TD-02 change that stack, so the S3 task list and dates need a rebaseline by the product owner. This roadmap orders work but **does not commit dates**.
