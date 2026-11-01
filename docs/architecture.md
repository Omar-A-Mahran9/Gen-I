# GEN-I — Application Architecture

| | |
|---|---|
| Status | Approved direction (TD-01…TD-17). Items marked *Proposed* await approval (see `business-decisions.md` §B). |
| Stack | Laravel 13 · PHP 8.3 · MySQL 8.x · Metronic 8.2.0 (Bootstrap 5.3) admin · Sanctum · Spatie Permission |

## 1. System context

```
            ┌───────────────────────┐        ┌──────────────────────────────┐
 Customers ─▶ React public frontend  │──HTTPS▶│  GEN-I Laravel application   │
            │ (separate app, out of │ /api/v1│                              │
            │  scope here)          │        │  • REST API  /api/v1         │
            └───────────────────────┘        │  • Metronic admin  /admin    │◀── Staff (browser)
                                             │  • Queue workers, scheduler  │
                                             └──────┬───────────────┬───────┘
                                                    │               │
                                            MySQL (primary)   External adapters (when approved):
                                                                X01 payment/finance   (conditional)
                                                                X02 ERP / carrier
                                                                X03 SMS / email gateway
                                                                X04 online model service
                                                                X05 local model runtime
```

The system is a single deployable Laravel application (modular *by folder*, not by service). External systems are reached only through adapter classes behind interfaces. No provider is chosen yet (BD-03, BD-07, BD-08).

## 2. Request flow and layering

```
Route ─▶ Middleware (auth, locale, throttle, request-id)
      ─▶ Controller (Admin\… or Api\V1\…)      thin: no business rules
      ─▶ FormRequest                            validation + authorize() delegating to Policy
      ─▶ Policy / Gate                          capability (Spatie permission) + scope (branch/company/own record)
      ─▶ Service (App\Services\<Domain>)        business rules, transactions, state transitions
      ─▶ Model (Eloquent)                       relations, casts, scopes — no workflow logic
      ─▶ Events → Listeners / Jobs / Notifications (after or inside commit, see §6)
Response: Admin → Blade view / JSON for AJAX tables; API → JsonResource / ResourceCollection
```

**Rules:**
1. **Admin and API controllers call the same Service methods.** Neither duplicates business logic.
2. Services receive validated data plus the acting user. They never read `request()` directly.
3. All state transitions (repair, quote, knowledge, AI run) go through a Service method. That method checks the actor, the current state and the record version, and writes the transition history (S2 §2).
4. Money is computed only in Services, with decimal-safe arithmetic (BR03). It is never computed in JavaScript or Blade.
5. Keep standard Laravel conventions. Add no repository layer, CQRS or DTO framework unless a concrete need is approved (TD-15, owner's rule 8).

## 3. Directory structure

```
app/
  Enums/                       PHP backed enums (states, types)
  Events/  Listeners/  Jobs/  Notifications/  Mail/
  Http/
    Controllers/
      Admin/                   Metronic dashboard controllers (session auth)
        Auth/                  Login, logout, password
      Api/V1/                  REST controllers (Sanctum where required)
    Middleware/                SetLocale, AssignRequestId, EnsureUserIsActive …
    Requests/
      Admin/<Domain>/          Admin form requests
      Api/V1/<Domain>/         API form requests (may extend shared base requests)
    Resources/Api/V1/          JsonResources
  Models/
  Policies/
  Services/<Domain>/           e.g. Settings/SettingsService, DataTables/DataTableService
  Support/                     Locale/Direction helper, AdminMenu builder, Money value object
  View/Components/Admin/       Blade component classes
config/
  admin-menu.php               configurable sidebar (label key, route, icon, permission, children)
  genI.php                     app-specific non-secret config (locales, defaults)
lang/{ar,en}/ + lang/{ar,en}.json
resources/
  views/admin/{layouts,partials,auth,dashboard,profile,settings,users,roles,…}/
  views/components/admin/
  css/admin.css                GEN-I overrides for Metronic (NO Tailwind)
  js/admin/                    GEN-I admin JS (ajax forms, datatables init, alerts)
  css/app.css, js/app.js       Tailwind — non-admin pages only (TD-08)
public/assets/admin/           Metronic 8.2.0 static bundles + VERSION
routes/
  web.php                      root / health pages
  admin.php                    /admin, name prefix admin., middleware web+auth+locale
  api.php                      /api/v1, name prefix api.v1.
docs/                          this documentation
tests/Feature/{Admin,Api}/…  tests/Unit/…
```

Domain folders (`<Domain>`) follow `module-map.md`: `Identity`, `Organization`, `Catalog`, `Commerce`, `Inventory`, `Repairs`, `Corporate`, `Knowledge`, `Ai`, `Analytics`, `Exports`, `Notifications`, `Audit`, `Settings`.

Routes are registered in `bootstrap/app.php` with `->withRouting(...)` (the Laravel 11+ convention; there is no `RouteServiceProvider` or `Http/Kernel.php`).

## 4. Authentication

| Surface | Mechanism | Notes |
|---|---|---|
| Admin dashboard | `web` guard, session cookie, Metronic login page | Rate-limited login; only users with an active staff role may enter (`EnsureUserCanAccessAdmin`). MFA "where configured" (S2 §7) is a later hardening item |
| API `/api/v1` | Laravel Sanctum (TD-11) | Public read endpoints (catalog, stores, comparison, RFQ submission) are unauthenticated with throttling. Authenticated customer/company endpoints use Sanctum. SPA-cookie vs token mode is decision OT-06 |
| Machine callbacks (conditional) | Signed provider webhooks | Signature verification + idempotent event store (BR05); only when a provider is approved |

There is one `users` table for staff and authenticated customers (TD-09). Whether a user is staff, a customer or a company member is decided by roles and membership, **not** by separate auth tables.

## 5. Authorization

- **Capabilities:** Spatie `permission` names in the form `<module>.<action>` (e.g. `catalog.products.update`, `repairs.bookings.reschedule`). Roles are bundles of permissions and are seeded from the approved role catalogue (BD-14).
- **Scope:** Policies check organization, branch, department, company membership and own-record ownership (S2 §3, BR07). *Proposed:* `organization_id` on tenant tables, enforced by a `BelongsToOrganization` global scope plus composite checks in Services (OT-02).
- **Separation of duties:** there is no blanket `Gate::before` bypass (OT-04). Approval actions (discount approval, quote issue, knowledge approval, AI policy activation) check that the approver is not the author where S2 requires it.
- **Same checks everywhere:** admin pages, API, exports and AI tools reuse the same Policies (S2 §3: "Staff tools and exports use the same scope checks as the interface").
- **Menu visibility is not authorization.** The sidebar hides items the user cannot access, but every route still enforces its Policy.

## 6. Transactions, concurrency and events

- `DB::transaction()` wraps every state change. Contended resources (repair slot, stock balance, quote version) use `lockForUpdate()` (BR04).
- **Exclusive slot rule:** S2 uses a PostgreSQL partial unique index. The MySQL equivalent is a unique index on a generated column that is NULL when the booking is inactive (see `database-design.md` §4).
- **Idempotency:** mutating API endpoints accept an `Idempotency-Key` header, stored in `idempotency_keys` (scope, key, request hash, response) (S2 §7).
- **Durable events (BR11, OT-11 proposed):** jobs go on the `database` queue (same MySQL connection) and are dispatched *inside* the transaction, so the job row commits or rolls back with the business change. Notifications run from those jobs, so notifications never decide state.
- Workers are idempotent. Failed jobs are visible in the admin "failed jobs / quarantine" view with retry (S2 §7, N10).
- Database locks are never held across network calls to providers or models.

## 7. Localization and theming

- Locales: `ar` (default, RTL) and `en` (LTR) (TD-07). Resolution order:
  - **Admin:** session value, then user preference, then `config('app.locale')`.
  - **API:** the `Accept-Language` header, then the default.
- Translation files live in `lang/ar`, `lang/en` and the JSON files. Validation messages are translated.
- Direction-aware assets: the admin layout loads `*.rtl.css` bundles when the locale is RTL.
- Theme modes: light, dark and system (Metronic `data-bs-theme`). The preference is kept in the browser and optionally on the user.
- Bilingual database content uses `*_ar`/`*_en` columns (*proposed*, OT-05).

## 8. Frontend asset organization (admin)

| Layer | Location | Loaded |
|---|---|---|
| Metronic core (plugins.bundle, style.bundle, scripts.bundle, fonts) | `public/assets/admin/…` | Every admin page via the layout `<head>`/footer |
| Page plugins (DataTables, form repeater, editor, lightbox) | `public/assets/admin/plugins/custom/…` | Only when a page asks for them (`@push('vendor-styles')` / `@push('vendor-scripts')`, or `<x-admin.plugins :use="['datatables']"/>`) |
| GEN-I overrides and shared JS | `resources/css/admin.css`, `resources/js/admin/*.js` via a separate Vite entry | Every admin page |
| Tailwind | `resources/css/app.css` | **Never** on admin pages |

There are no global CDN scripts. Details are in `metronic-migration.md`.

## 9. Security requirements (NFR-04, NFR-05, S2 N01/N09)

- HTTPS in all non-local environments; secure, HttpOnly, SameSite cookies; CSRF on all web forms.
- Validation via Form Requests only; explicit `$fillable`; no `$guarded = []`.
- Rate limits on login, password reset, public forms (RFQ, repair booking) and API.
- Secrets live only in `.env` or the deployment secret store. `.env` is git-ignored. Admin screens show secret *references*, never values (S2 §7).
- File uploads go to a private disk, with MIME/size validation and quarantine status before use (S2 knowledge/RFQ uploads). No public URLs for private files; downloads go through signed, authorized routes.
- Audit log (`audit_events`) for authentication, permission changes, approvals and state transitions. It is append-only by application policy.
- Data minimization; consent recorded per purpose; retention jobs follow approved policies (BD-13).
- Security test suite: cross-scope access with guessed IDs, exports and caches must return 403/404 (N01).

## 10. Testing strategy

| Layer | Tooling | Database |
|---|---|---|
| Unit (services, value objects, state machines, money) | PHPUnit 12 | none / SQLite in-memory |
| Feature: admin routes, API endpoints, policies | PHPUnit + `RefreshDatabase` | SQLite in-memory where SQL is portable |
| Integration: locking, generated columns, FULLTEXT, concurrency | `composer test:mysql` (`phpunit.mysql.xml`) | MySQL `geni_testing` |
| Authorization matrix | Data-provider tests per role × action × scope | as above |
| API contract | Response-shape assertions per `api-contracts.md` | as above |
| Style | Laravel Pint | — |

Each phase must pass `php artisan test`, `composer test:mysql` and `pint --test` before it is committed. `Tests\TestCase` refuses to run against any database other than `:memory:` or `*_testing` (TD-22). CI is decision OT-07.

## 11. Observability (N10)

- A request ID middleware adds `X-Request-Id`, propagated into logs and jobs.
- Structured logging channel; failed-job tracking; queue-lag and failed-notification counts shown on the admin dashboard once those modules exist.

## 12. AI subsystem (MOD06/MOD07) — placeholder

These modules are not designed in detail until BD-07 and OT-10 are decided. Constraints carried from S2 and S4:
- AI calls go through an internal gateway with provider adapters.
- Tools execute with the initiating user's permissions.
- Every release passes deterministic validation.
- The assistant never confirms price, stock or bookings by itself (NFR-10, BR10).
- The prompt registry is versioned.

## 13. Deviations from the blueprint (S2) — summary

| S2 proposal | GEN-I approved | Mitigation |
|---|---|---|
| React + FastAPI backend | Laravel 13 monolith (TD-01) | Same API families under `/api/v1` |
| PostgreSQL + RLS | MySQL (TD-02) | Global scopes, Policies, composite checks, cross-scope test suite |
| Partial unique index / exclusion constraint | Generated column + unique index; serialized allocator | MySQL integration tests |
| GIN / tsvector lexical search | InnoDB FULLTEXT | Arabic tokenization to be validated in MOD06 |
| `uuid` PK everywhere | *Proposed* bigint PK + public UUID (OT-01) | Public UUIDs keep IDs non-guessable in the API |
| Python agents SDK | *Proposed* Laravel AI gateway (OT-10) | Decision pending |

## 14. As built (foundation, 2026-11-01)

- **Routing:** `routes/web.php` (root → `/admin`), `routes/admin.php` (registered in `bootstrap/app.php` → `withRouting(then:)` with `web` middleware, `/admin` prefix, `admin.` names), `routes/api.php` (`/api/v1`, `api.v1.` names).
- **Middleware:** `AssignRequestId` (global), `SetLocale` (web) and `SetApiLocale` (api) — both placed before authentication in the priority list so auth/throttle errors are translated; `admin.access` (`EnsureUserCanAccessAdmin`: active + `dashboard.view`); `active` (`EnsureTokenUserIsActive`); `auth.session` on admin routes so password changes sign out other sessions.
- **Separate auth behaviour:** Sanctum `guard` is set to `[]` — the admin web session never authenticates `/api/v1`; the API accepts bearer tokens only (revisit with OT-06).
- **Services:** `Services/Admin/{UserService, RoleService, PrivilegeGuard}`, `Services/Settings/SettingsService` (singleton, cached in the default cache store, flushed on update), `Services/Audit/AuditLogger` (redacts password/token/secret keys).
- **Support:** `Support/DataTable` (server-side DataTables protocol with allow-listed search/sort, capped page size), `Support/AdminMenu`, `Support/Locale`, `Support/helpers.php` (`settings()`, `format_date()`, `format_datetime()` with RTL isolation).
- **Authorization:** `UserPolicy`, `RolePolicy` + Spatie permission gates. There is **no** `Gate::before` bypass: `super-admin` simply holds every permission (seeder re-syncs it). Privilege rules: grant/remove only roles/permissions you hold; no self role changes, self deactivation or self deletion; only super admins manage super admins; the last active super admin cannot be deactivated, deleted or demoted; protected roles cannot be edited or deleted; roles in use cannot be deleted.
- **Admin front-end:** Metronic bundles + `resources/js/admin/*` (Vite entry `resources/js/admin/app.js`): `data-geni-ajax-form`, `data-geni-action` (+ `data-geni-confirm`), `<x-admin.datatable>`, Select2 AJAX (`data-geni-select2-ajax`), toastr for results, SweetAlert2 for confirmations.
- **Errors:** `resources/views/errors/*` use `admin.layouts.error`, which needs no session, CSRF token or Vite manifest.
