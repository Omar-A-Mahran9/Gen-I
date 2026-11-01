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
