# GEN-I — Decision Register

| | |
|---|---|
| Updated | 2026-11-01 |
| Legend | **Approved** = confirmed by the project owner. **Proposed** = recommendation awaiting approval. **Open** = needs a GEN-I business owner decision. |

Approved technical decisions override the technology proposals in the blueprint (S2) and the agile plan (S3). They do **not** change approved business requirements.

## A. Approved technical decisions

| ID | Decision | Approved | Notes / deviation from source docs |
|---|---|---|---|
| TD-01 | **Laravel 13 monolith, traditional MVC**, as the GEN-I backend: Metronic Blade admin + RESTful API under `/api/v1`. No microservices. | 2026-11-01 | S2 proposed React + FastAPI (+ optional Node gateway). The public React frontend is a separate application, **out of scope** here. |
| TD-02 | **MySQL** is the primary database. SQLite may be used for lightweight automated tests. | 2026-11-01 | S2 and S3 proposed PostgreSQL with Row-Level Security. RLS, exclusion constraints, partial indexes and GIN/tsvector must be replaced by application-level equivalents (see `database-design.md` §4). |
| TD-03 | Database structures derive only from approved GEN-I requirements; **no Twasol schema reuse**. | 2026-11-01 | — |
| TD-04 | Independent Git repository inside `Gen-I/`. Remote `https://github.com/Omar-A-Mahran9/Gen-I.git`. Branches `main` (stable) and `develop` (integration). No force push without explicit approval. Each completed and tested phase is a separate commit. | 2026-11-01 | Gen-I currently sits untracked inside a broken home-directory repo (`~/.git`). Do not touch that repo. |
| TD-05 | **Metronic 8.2.0** (Bootstrap 5.3.0) precompiled static bundles from the Twasol backup, copied to `public/assets/admin/` with a `VERSION` file. The Twasol backup stays unchanged. | 2026-11-01 | Licence confirmation is not blocking, per the owner's instruction. |
| TD-06 | Admin layout, header, sidebar, toolbar, footer, auth layout and components are rebuilt with Laravel 13 conventions. Navigation is configurable. No Twasol names, routes, permissions or branding are kept. | 2026-11-01 | — |
| TD-07 | Arabic (RTL) is the **default locale**; English (LTR) is supported. Light and dark themes. | 2026-11-01 | Matches the S3 "Arabic English" and S2 N12 direction. Final translation scope remains a business sign-off (BD-10). |
| TD-08 | Tailwind dependencies stay installed but must not load on Metronic admin pages. | 2026-11-01 | — |
| TD-09 | **Unified `users` table** with roles and permissions, not separate admin/customer auth tables, unless approved requirements establish otherwise. | 2026-11-01 | Consistent with S2 (`users` = staff and authenticated customer principals). S2's separate `customers` contact table (which includes guests) is kept as a domain table, not an auth table. |
| TD-10 | **spatie/laravel-permission** for RBAC, after compatibility verification. | 2026-11-01 | Verified 2026-11-01: latest 8.3.2 requires `php ^8.3`, `illuminate/* ^12.0\|^13.0`. Installed 8.3.2 in Phase 1 (config + migration published; default bigint keys, teams disabled). |
| TD-11 | **Laravel Sanctum** for API authentication where required. | 2026-11-01 | Verified 2026-11-01: v4.3.3 supports `illuminate/* ^13.0`. Installed 4.3.3 in Phase 1 via `install:api`; API prefix set to `api/v1`. |
| TD-12 | Native `settings` table + dedicated `SettingsService`. | 2026-11-01 | Twasol's `anlutro/l4-settings` is not used. |
| TD-13 | In-house server-side DataTables service. | 2026-11-01 | No yajra package for now. |
| TD-14 | PHP **8.3** is the development target. | 2026-11-01 | Local CLI is PHP 8.3.27. Use Homebrew Composer `/opt/homebrew/bin/composer` 2.11.1. The shell alias `composer` points to a missing `~/composer.phar`. |
| TD-15 | Shared business logic in **Services**, used by both Admin and API controllers. **Form Requests** for validation, **Policies/Gates** for authorization, plus Events, Listeners, Jobs and Notifications where appropriate. | 2026-11-01 | — |
| TD-16 | Verify every dependency before installation. Keep secrets and environment credentials out of Git. | 2026-11-01 | — |
| TD-17 | **Laravel Boost** dev dependency, as required by `CLAUDE.md`. | 2026-11-01 | Installed 2.10.4 in Phase 1 (guidelines, skills, MCP for Claude Code + Codex). |
| TD-18 | Confidential source documents are copied to `docs/source/`, which is git-ignored and never committed or pushed. | 2026-11-01 | Resolves OT-09. |
| TD-19 | `CLAUDE.md` and `AGENTS.md` are kept identical. GEN-I rules take precedence over generic Boost guidelines and skills. | 2026-11-01 | — |
| TD-21 | Local MySQL: the existing Homebrew `mysql@8.4` 8.4.11 on 127.0.0.1:3307 is started per session (`brew services run`, no login auto-start); the server config is unchanged. Databases `geni` and `geni_testing` (utf8mb4 / utf8mb4_unicode_ci). App user `geni`@`127.0.0.1` has `ALL PRIVILEGES` on those two databases only (no global privileges, no GRANT OPTION). The password lives only in the local `.env`. Root is used only for the one-time setup, never by the app. | 2026-11-01 | Isolation verified: `geni` is denied on other databases and on `mysql.user`. |
| TD-22 | Test databases: `php artisan test` uses SQLite `:memory:`. `composer test:mysql` (`phpunit.mysql.xml`) uses MySQL `geni_testing`. `Tests\TestCase` refuses to run against any database not named `:memory:` or `*_testing`. | 2026-11-01 | Protects `geni` from `RefreshDatabase`. |
| TD-23 | Foundation implementation authorised and executed in one pass (owner's "Full Backend Foundation Implementation" instruction, 2026-11-01). | 2026-11-01 | See roadmap "Foundation delivery". |
| TD-24 | OT-04 implemented as recommended: no `Gate::before` bypass; super-admin holds every permission explicitly. | 2026-11-01 | Within TD-23 authorisation. |
| TD-25 | Sanctum accepts bearer tokens only (`sanctum.guard = []`) so admin sessions never authenticate the API. | 2026-11-01 | Revisit with OT-06. |
| TD-20 | The 102 S2 tables are the primary database reference but are not created in bulk. Each module reviews its tables first, and deviations are recorded in `database-design.md` §4a. | 2026-11-01 | — |

## B. Open technical decisions (need your approval)

| ID | Question | Recommendation | Blocks |
|---|---|---|---|
| OT-01 | Primary-key strategy. S2 specifies `uuid` PKs on every table. | **Proposed:** `bigint` auto-increment internal PK + a unique `uuid` public identifier on entities exposed through the API. Reasons: InnoDB clusters on the PK, and Spatie's pivot tables default to big integers. Exposed IDs stay non-guessable. | Phase 3 migrations |
| OT-02 | Multi-tenancy depth. S2 puts `org_id` on every tenant table, but S1 describes one GEN-I business. | **Proposed:** keep `organization_id` on tenant tables (cheap now, costly to add later), with a single seeded GEN-I organization. Branch, company and own-record scope are enforced by policies. | Phase 3 |
| OT-03 | Mapping S2's scoped roles (`user_roles` with org/branch/department/company scope) onto Spatie. | **Proposed:** Spatie roles/permissions for *capabilities*. Branch/department/company *scope* comes from membership tables plus Policies. Evaluate Spatie "teams" only if per-branch roles become a requirement. | Phase 3 |
| OT-04 | Super-admin bypass (`Gate::before`). | **Proposed:** no blanket business bypass. S2 requires separation of duties. A system-administrator role gets explicit permissions only. | Phase 3 |
| OT-05 | Bilingual content storage. | **Proposed:** paired `*_ar` / `*_en` columns for the fixed two-language scope (no extra package, indexable, FULLTEXT-friendly). Alternative: JSON columns. | Phase 5 (catalog) |
| OT-06 | API authentication mode for the separate React frontend. | Sanctum **SPA cookie auth** if it shares the top-level domain, **personal access tokens** otherwise. Depends on hosting/domains (BD-11). | Customer-facing authenticated API |
| OT-07 | Docker, CI and branch protection. S3 task S1-D1-01 assumes Docker + CI. | Decide the CI provider (e.g. GitHub Actions) and whether a Docker local stack is required. | Later (optional) |
| OT-12 | ~~Local MySQL for GEN-I~~ | **Resolved → TD-21.** | — |
| OT-08 | Timezone and currency defaults. S2 N12 mentions **EGP**. | Proposed `APP_TIMEZONE=UTC` storage, with display timezone and currency held in settings. GEN-I to confirm the market (BD-04). | Phase 3 |
| OT-09 | ~~Source documents location~~ | **Resolved → TD-18.** | — |
| OT-10 | AI runtime inside a Laravel monolith. S2 references Python OpenAI Agents SDK and Ollama. | **Proposed:** a Laravel `AiGateway` interface with provider adapters via the HTTP client. Provider, models and online/offline policy stay open (BD-07). No implementation before approval. | MOD06/07 phases |
| OT-11 | Outbox implementation (BR11). | **Proposed:** database queue on the same MySQL connection, with jobs dispatched *inside* the business transaction (atomic commit), plus an `outbox_events`/audit record where S2 requires evidence. | Phase 4 |

## C. Open business decisions (GEN-I owners — from S1 §9, S2 §15, S5)

| ID | Decision | Owner (per source) | Affects |
|---|---|---|---|
| BD-01 | Retail launch model: store reservation / staff-assisted vs full online checkout | CEO, commercial ops | MOD03; EX04 conditional |
| BD-02 | B2B launch model: quote-first RFQ (recommended by S2/S5) vs corporate purchasing/credit | CEO, sales, finance | MOD05 |
| BD-03 | Source authority and refresh rules for specs, prices, inventory, appointment capacity (ERP vs GEN-I managed) | Stock and integration owners | MOD02/03/04 |
| BD-04 | Target market, currency, tax/invoice requirements, shipping coverage | CEO, finance | Money, tax, settings |
| BD-05 | Repair operations: supported models, services, slot/resource rule, parts, diagnosis terms, consent, warranty, cancellation/no-show policy | Repair operations, finance | MOD04 |
| BD-06 | Offer rules: delivery, returns, trade-in inspection, installments provider, discounts | Commercial, finance | FR-21 |
| BD-07 | AI data policy: online / offline / hybrid; providers, models, auditors, budgets, human gates | CEO, AI/security, domain reviewers | MOD06/07 |
| BD-08 | SMS/notification provider, sender, templates, reminder behaviour | Operations | FR-18, notifications |
| BD-09 | Analytics events, KPI definitions, consent handling, reporting owner | Product owner | MOD09 |
| BD-10 | Final language scope, translations, controlled terminology | Business owner, UI/UX | Localization |
| BD-11 | Hosting, domain, deployment ownership, support SLA, backup/recovery targets | Technical lead, CEO | MOD11, OT-06 |
| BD-12 | Brand guidelines: final logo assets (light/dark), favicon, colour palette, typography | CEO, UI/UX | Admin branding (placeholders until supplied) |
| BD-13 | Legal retention periods, privacy notices, legal pages | GEN-I legal | N09, audit retention |
| BD-14 | Staff role catalogue and company approval model (which actors A05–A18 exist at launch) | Business owner | RBAC seed data |

## D. Change log

| Date | Change |
|---|---|
| 2026-11-01 | Register created from the audit and the owner's approved architecture decisions. |
| 2026-11-01 | Phase 1: TD-10/11/17 installed; TD-18–TD-20 added; OT-09 resolved; OT-12 added. |
| 2026-11-01 | OT-12 approved and executed → TD-21, TD-22. |
| 2026-11-01 | Foundation delivered → TD-23–TD-25; DV-16 (`audit_logs`) recorded in database-design.md. Default roles admin/manager/staff are placeholders pending BD-14. |
