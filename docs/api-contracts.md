# GEN-I — API Conventions and Contracts

| | |
|---|---|
| Base path | `/api/v1` (TD-01) |
| Consumer | Separate React public frontend (out of scope here), and future approved integrations |
| Status | Conventions are *proposed for approval*. Endpoint families come from S2 §7 and are refined per module phase |

## 1. General conventions

| Topic | Convention |
|---|---|
| Format | JSON only. Requests `Content-Type: application/json` (multipart for uploads). Responses via Laravel `JsonResource` |
| Versioning | URI prefix `/api/v1`. Breaking changes require `/api/v2`. Additive changes allowed in v1 |
| Naming | Plural kebab-case resources (`/repair-bookings`), `snake_case` JSON keys |
| Identifiers | Public `uuid` in URLs and payloads (OT-01). Internal numeric IDs never exposed |
| Locale | `Accept-Language: ar` (default) or `en`. Translated fields returned in the requested locale; admin-only endpoints may return both |
| Money | `{ "amount": "1234.50", "currency": "EGP" }`. Amount as a **string** decimal (never float). Currency per BD-04 |
| Dates | ISO-8601 with offset, UTC (`2026-11-01T10:00:00Z`) |
| Freshness | Price/stock facts include `observed_at` and `expires_at` (S2 §7, FR-10) |
| Pagination | `?page=&per_page=` (max 100). Response includes `data`, `links`, `meta` (Laravel paginator) |
| Filtering/sorting | `?filter[field]=value&sort=-created_at` on documented fields only (allow-list) |
| Request ID | Client may send `X-Request-Id`; server always returns one |
| Idempotency | All non-idempotent `POST` mutations that create bookings, reservations, RFQs, quote actions or consents **require** `Idempotency-Key`. A replay with the same key and body returns the original response; the same key with a different body returns `409` |
| Rate limiting | Named limiters: `api-public` (per IP), `api-auth` (per user), stricter `api-forms` for RFQ/booking submissions |
| Authentication | Sanctum (TD-11). SPA cookie vs bearer token per OT-06 |

## 2. Response envelope

Success (single resource):
```json
{ "data": { "id": "9b1d…", "type": "repair_booking", "reference": "RB-2026-000123", "status": "confirmed" } }
```

Error (all errors use this shape):
```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "details": { "mobile": ["The mobile format is invalid."] },
    "request_id": "01JC…"
  }
}
```

| HTTP | `code` | Meaning (S2 §7 distinctions) |
|---|---|---|
| 400 | `bad_request` | Malformed request |
| 401 | `unauthenticated` | Missing or invalid credentials |
| 403 | `forbidden` | Authenticated but not permitted / out of scope |
| 404 | `not_found` | Missing **or not visible in caller's scope** (prevents ID probing) |
| 409 | `conflict` | State/version conflict, slot taken, idempotency mismatch |
| 422 | `validation_failed` | Form Request validation |
| 429 | `rate_limited` | Throttled (`Retry-After` header) |
| 502 / 503 | `provider_failure` / `service_unavailable` | External provider failure / retryable outage. Never reported as success (BR10) |
| 500 | `server_error` | Unexpected; details withheld |

Long-running operations (exports, assistant runs) return `202 Accepted` with a job reference and a status URL.

## 3. Endpoint families (from S2 §7, refined per module)

| Family | Endpoints (indicative) | Auth | Scope | Module |
|---|---|---|---|---|
| Auth & profile | `POST /auth/login`, `POST /auth/logout`, `GET/PATCH /me`, `GET/PUT /me/consents` | public → Sanctum | C | MOD01 |
| Settings (public subset) | `GET /settings/public` (locale options, contact, store-facing config) | public | C | Platform |
| Catalog | `GET /categories`, `GET /brands`, `GET /products`, `GET /products/{id}`, `GET /variants/{id}` | public | C | MOD02 |
| Stores | `GET /stores`, `GET /stores/{id}` (hours, directions) | public | C | MOD02 |
| Offers / content | `GET /promotions`, `GET /policies/{type}` (delivery, returns, warranty, trade-in, installments content) | public | C | MOD02/03 |
| Prices & availability | `GET /prices?variant_ids=`, `GET /availability?variant_ids=&branch_id=` (with freshness) | public | C | MOD03 |
| Comparison | `POST /comparisons` (variant IDs, requested attributes) → facts, differences, missing values | public | C | MOD03 |
| Reservations | `POST /reservations`, `GET /reservations/{reference}` | public + verification / Sanctum | C | MOD03 |
| Repair catalogue | `GET /repair-services?device_model=`, `GET /repair-slots?branch_id=&service_id=&date=` | public | C | MOD04 |
| Repair bookings | `POST /repair-bookings`, `GET /repair-bookings/{reference}`, `POST …/{reference}/reschedule`, `POST …/{reference}/cancel` | public + verification / Sanctum | C | MOD04 |
| Repair estimates | `GET /repair-jobs/{id}/estimates/current`, `POST /repair-jobs/{id}/estimates/{version}/consent` | Sanctum / verified link | C | MOD04 |
| Corporate RFQ | `POST /rfqs` (company, contact, items, quantities, requirements, files), `GET /rfqs/{reference}` | public / Sanctum company member | C | MOD05 |
| Quotations | `GET /quotes/{id}`, `GET /quotes/{id}/versions/{n}`, `POST /quotes/{id}/accept`, `POST /quotes/{id}/revision-requests` | Sanctum company member | C (accept → order **Cond**) | MOD05 |
| Assistant | `POST /assistant/runs`, `GET /assistant/runs/{id}` | public / Sanctum | C (blocked by BD-07) | MOD06 |
| Analytics ingest | `POST /analytics/events` (consent-aware) | public | C (BD-09) | MOD09 |
| Exports | `POST /exports`, `GET /exports/{id}`, `GET /exports/{id}/download` (signed, expiring) | Sanctum | C | MOD10 |
| Carts, checkout, payments, provider events | `POST /carts`, `POST /checkouts`, `POST /provider-events/{provider}` | — | **Cond — not built** | — |

**Admin-only operations** (catalog maintenance, slot management, quote preparation, knowledge review, AI policy activation) are served by the **Metronic admin routes** (`/admin/...`, session auth). They are not public API in v1, unless an approved integration requires it.

Customer verification for unauthenticated booking/reservation lookups (e.g. reference + mobile OTP) depends on BD-08 (SMS provider) and is specified in the MOD03/MOD04 phases.

## 4. Contract documentation per module

Each module phase adds a section to this file listing, for every endpoint:
- request fields and validation rules (mirroring the Form Request),
- the response resource shape,
- error codes and idempotency behaviour,
- the authorizing Policy and permission,
- the linked FR/UC IDs.

Feature tests assert these shapes.

## 5. Implemented endpoints (foundation)

| Method & path | Auth | Throttle | Request | Success | Errors |
|---|---|---|---|---|---|
| `POST /api/v1/auth/token` | public | `api-token`: 5/min per email+IP, 20/min per IP | `email`, `password`, `device_name` (≤100) | `201 {data: {token, token_type: "Bearer", user}}` | `422 validation_failed` (bad credentials or inactive account → `details.email`), `429 rate_limited` |
| `GET /api/v1/user` | Bearer token, active account | `api`: 60/min per user | — | `200 {data: UserResource}` | `401 unauthenticated` |
| `DELETE /api/v1/auth/token` | Bearer token | `api` | — | `204` (current token revoked) | `401 unauthenticated` |

`UserResource`: `id`, `name`, `email`, `locale`, `is_active`, `email_verified_at`, `roles` (names), `created_at` (ISO-8601 UTC).
Every response carries `X-Request-Id`; API responses carry `Content-Language`. Error bodies follow §2 (verified by `tests/Feature/Api/ApiFoundationTest.php`).

Note: `id` is currently the numeric primary key; switch to a public UUID if OT-01 is approved before any public client depends on it.

## 6. Business module endpoints (Phase 2)

All endpoints below require `Authorization: Bearer <token>` (Sanctum), an **active** account, and the listed permission. Rate limit: `api` (60/min per user). Records outside the caller's organization respond **404** (`not_found`), so IDs cannot be probed. Business-rule refusals respond **422** with `error.code = "business_rule_violation"` (or **409** `conflict`). Validation errors use `validation_failed` with `details`.

**List conventions:** `?search=` · `?filter[field]=value` (allow-listed) · `?sort=field` or `?sort=-field` · `?per_page=` (≤ 100). Responses are `{data: [...], links: {...}, meta: {current_page, per_page, total, ...}}`.

| Method & path | Permission | Notes |
|---|---|---|
| `GET /organizations` · `GET /organizations/{id}` | organizations.view | filters: status, currency |
| `POST /organizations` | organizations.create **and** super admin | name, timezone, currency |
| `PUT /organizations/{id}` · `PATCH /organizations/{id}/status` | organizations.update | status `active\|suspended`; own organization cannot be suspended |
| `DELETE /organizations/{id}` | organizations.delete + super admin | only when it has no dependent records |
| `GET\|POST /companies` · `GET\|PUT\|DELETE /companies/{id}` | companies.* | filters: status, organization_id, account_owner_id |
| `PATCH /companies/{id}/status` | companies.update | pending→verified/suspended, verified→suspended, suspended→verified |
| `GET\|POST /companies/{id}/members` · `PUT\|DELETE /companies/{id}/members/{member}` | companies.view / update | user must belong to the company's organization |
| `GET\|POST /companies/{id}/addresses` · `PUT\|DELETE /companies/{id}/addresses/{address}` | companies.view / update | — |
| `GET\|POST /branches` · `GET\|PUT\|DELETE /branches/{id}` · `PATCH /branches/{id}/status` | branches.* | filters: status, organization_id, city |
| `GET /branches/{id}/inventory` | branches.view + inventory.view | product availability at the store |
| `GET\|POST /categories` · `GET\|PUT\|DELETE /categories/{id}` · `PATCH …/status` | catalog.* | filters: status, parent_id, root=1 |
| `POST /categories/{id}/attributes` · `PUT\|DELETE /categories/{id}/attributes/{attribute}` | catalog.update | typed attribute dictionary |
| `GET\|POST /brands` · `GET\|PUT\|DELETE /brands/{id}` · `PATCH …/status` | catalog.* | — |
| `GET\|POST /products` · `GET\|PUT\|DELETE /products/{id}` | catalog.* | filters: status, category_id, brand_id, item_kind; search covers name, model code and SKU |
| `PATCH /products/{id}/status` | catalog.approve | lifecycle rules in docs/business-modules.md §3 |
| `GET\|POST /products/{id}/variants` · `PUT\|DELETE /variants/{id}` · `PATCH /variants/{id}/status` | catalog.* | — |
| `PUT /variants/{id}/specifications` | catalog.update | `{values: [{attribute_id, value, source_reference, verified_at?}]}` replaces the set |
| `POST /products/{id}/media` (multipart, 30/min) · `PUT\|DELETE /products/{id}/media/{media}` · `GET …/media/{media}/download` | catalog.update (review: catalog.approve) | images are private; download is authenticated; blocked images return 404 |
| `GET\|POST /policies` · `GET\|PUT\|DELETE /policies/{id}` | policies.view / manage | update/delete drafts only |
| `POST /policies/{id}/approve` · `POST /policies/{id}/retire` | policies.approve | author cannot approve own draft |
| `GET\|POST /price-lists` · `GET\|PUT\|DELETE /price-lists/{id}` · `PATCH …/status` | pricing.view / manage | `company_id` is prohibited (conditional scope) |
| `GET\|POST /price-lists/{id}/prices` · `PUT\|DELETE /prices/{id}` | pricing.view / manage | filter[period]=current\|future\|past; only future prices editable |
| `GET /inventory` · `GET /inventory/movements` | inventory.view | balance filters: branch_id, variant_id, stock=available\|out; movement filters: branch_id, reason, from, to |
| `POST /inventory/adjustments` (60/min) | inventory.adjust | optional `Idempotency-Key` header (8–100 chars `[A-Za-z0-9_-:.]`) |

### Examples

Create a branch:
```http
POST /api/v1/branches
Authorization: Bearer 1|…
Accept-Language: en
Content-Type: application/json

{"code": "cai-01", "name": "Cairo Festival City", "address_text": "Ring Road, New Cairo", "city": "Cairo",
 "latitude": 30.0287, "longitude": 31.4086,
 "opening_hours": {"sat": [{"open": "10:00", "close": "22:00"}], "fri": [{"open": "13:00", "close": "23:00"}]}}
```
```json
201 Created
{"data": {"id": 7, "organization_id": 1, "code": "CAI-01", "name": "Cairo Festival City", "address_text": "Ring Road, New Cairo",
  "city": "Cairo", "phone": null, "email": null,
  "location": {"latitude": 30.0287, "longitude": 31.4086, "directions_url": "https://www.google.com/maps/search/?api=1&query=30.0287000,31.4086000"},
  "opening_hours": {"sat": [{"open": "10:00", "close": "22:00"}], "sun": [], "mon": [], "tue": [], "wed": [], "thu": [], "fri": [{"open": "13:00", "close": "23:00"}]},
  "status": "active", "created_at": "2026-11-01T06:12:00Z", "updated_at": "2026-11-01T06:12:00Z"}}
```

Add a price:
```http
POST /api/v1/price-lists/3/prices
{"variant_id": 41, "unit_price": "52999.99", "min_quantity": 1, "tax_policy_id": 2}
```
```json
201 Created
{"data": {"id": 90, "price_list_id": 3, "variant": {"id": 41, "sku": "IP16-128-BLK", "name": "128GB Black"}, "min_quantity": 1,
  "unit_price": {"amount": "52999.99", "currency": "EGP"}, "tax_policy_id": 2,
  "valid_from": "2026-11-01T06:15:00Z", "valid_to": null, "state": "current", "created_at": "2026-11-01T06:15:00Z"}}
```

Record a stock receipt (idempotent):
```http
POST /api/v1/inventory/adjustments
Idempotency-Key: rcpt-2026-11-01-0001
{"branch_id": 7, "variant_id": 41, "quantity": 10, "reason": "receipt"}
```
```json
201 Created   (a replay with the same key and payload → 200 with the same movement; a different payload → 409)
{"data": {"id": 512, "branch": {"id": 7, "code": "CAI-01"}, "variant": {"id": 41, "sku": "IP16-128-BLK"},
  "quantity_delta": 10, "on_hand_after": 10, "reason": "receipt", "source_event_id": "manual:rcpt-2026-11-01-0001",
  "note": null, "created_by": 2, "created_at": "2026-11-01T06:20:00Z"}}
```

Business-rule refusal:
```json
422 Unprocessable Content
{"error": {"code": "business_rule_violation", "message": "Insufficient stock: only 10 unit(s) on hand.", "request_id": "01JC…"}}
```
