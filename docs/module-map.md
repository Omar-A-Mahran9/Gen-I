# GEN-I — Module Map

| | |
|---|---|
| Purpose | Maps business domains to source requirements, Laravel code areas, admin workspaces, API families and scope class |
| Scope classes | **C** = committed release baseline (S3) · **Cond** = conditional, needs a sized change request and management decision · **Def** = deferred · **Platform** = shared technical foundation |

Domains below come from the approved domain list and S3 MOD01–MOD11. Each must still be validated against the approved business documentation before its implementation phase starts.

## 1. Overview

| # | Domain | S3 module | Use cases (S2) | FRs | Scope | Laravel domain folder |
|---|---|---|---|---|---|---|
| 1 | Identity & Access Management | MOD01 | UC01 UC02 UC32 | FR-17, NFR-04 | C | `Identity` |
| 2 | Organizations, Companies & Branches | MOD01 | UC01 UC02 | — (enabler) | C | `Organization` |
| 3 | Settings | Platform | UC19 UC32 | FR-20, NFR-08 | Platform | `Settings` |
| 4 | Audit Logging | MOD01 | UC23 UC32 | NFR-04, N01 | C | `Audit` |
| 5 | Notifications | MOD01 / UC20 | UC20 | FR-18, FR-27 | C (SMS depends on BD-08) | `Notifications` |
| 6 | Product Catalog (categories, brands, products, variants, attributes, media) | MOD02 | UC04 UC19 | FR-01 FR-02 FR-04 FR-09 FR-23 | C | `Catalog` |
| 7 | Store Information (branches as stores, hours, directions) | MOD02 | UC04 UC19 | FR-20 | C | `Organization` / `Catalog` |
| 8 | Offers, Pricing & Commercial Policies | MOD02/03 | UC05 UC19 | FR-04 FR-10 FR-21 | C (content/pricing); financing/trade-in transactions **Cond** | `Commerce` |
| 9 | Product Comparison | MOD03 | UC25 | FR-07…FR-12 | C | `Catalog` |
| 10 | Inventory & Reservations | MOD03 | UC05 UC08 | FR-10 FR-11 | Reservation C; checkout/payment **Cond** | `Inventory` |
| 11 | Repair Services & Management | MOD04 | UC10 UC11 UC12 UC27 | FR-13…FR-19 | C | `Repairs` |
| 12 | Repair Bookings, Jobs & Estimates | MOD04 | UC10 UC11 | FR-16…FR-19 | C | `Repairs` |
| 13 | Parts & Warranties | MOD04 | UC11 UC12 UC27 | FR-15 | C | `Repairs` |
| 14 | Corporate Solutions & B2B Requests (RFQ) | MOD05 | UC13 | FR-24…FR-27 | C | `Corporate` |
| 15 | Corporate Quotations & Versions | MOD05 | UC14 | FR-27 | C; quote→order and credit **Cond** (UC15, UC26) | `Corporate` |
| 16 | Knowledge Base | MOD06 | UC21 | NFR-10 | C | `Knowledge` |
| 17 | AI Assistant & Agentic RAG | MOD06 | UC16 UC17 UC18 UC31 | FR-05…FR-07 FR-12 | C (provider decision BD-07 blocks) | `Ai` |
| 18 | Prompt & Specialist Role Registry | MOD07 | UC22 UC28 | — | C | `Ai` |
| 19 | AI Tools & Execution Auditing | MOD06/07 | UC17 UC23 | NFR-10 | C | `Ai` |
| 20 | Admin Operations (workspaces) | MOD08 | UC19 UC27 | FR-23 | C | cross-module (`Admin` controllers) |
| 21 | Analytics & Reporting | MOD09 | UC24 | FR-22, NFR-12 | C (commerce metrics only when the source module is enabled) | `Analytics` |
| 22 | CSV & Word Exports | MOD10 | UC24 | — | C | `Exports` |
| 23 | Production continuity (backup, deploy, runbooks) | MOD11 | UC29 UC30 | NFR-03 | C | ops (no domain folder) |
| — | Retail cart, checkout, payments, fulfilment, returns/refunds | — | UC07 UC08 UC09 | FR-11 (purchase part) | **Cond** | `Commerce` (inactive) |
| — | Corporate ordering & credit | — | UC15 UC26 | — | **Cond** | `Corporate` (inactive) |
| — | Native apps, voice/social agents, personalization | — | — | — | **Def** | — |

## 2. Module responsibilities

### 2.1 Identity & Access (MOD01)
- `users` (staff and customers), authentication, password reset, profile.
- Roles and permissions (Spatie), user activation/deactivation.
- Consent records per purpose (S2 `consents`).
- **Admin:** Users, Roles & Permissions, Profile.
- **API:** auth/session, own profile, own consents.
- **Depends on:** Organization.

### 2.2 Organizations, Companies & Branches (MOD01)
- **Organization:** the operating entity (single GEN-I org, OT-02).
- **Branches:** stores and service branches.
- **Departments:** staff authorization units.
- **Companies:** corporate customers.
- **Company members:** membership and spending authority.
- Addresses.
- Provides scope for every other module.

### 2.3 Settings (platform)
- Key/value settings with typed casting and caching through `SettingsService`.
- **General:** name, logos, contact.
- **Localization:** default locale, display timezone, currency.
- **Theme:** default theme mode.
- Secrets are **not** stored here.

### 2.4 Audit Logging (MOD01)
- Append-only `audit_events`.
- Records actor, action, subject, before/after summary, request ID and policy version.
- Written by Services and listeners.
- **Admin:** read-only audit viewer for authorized auditors (A17).

### 2.5 Notifications (UC20)
- Laravel Notifications with database + mail channels first.
- An SMS channel adapter only after BD-08.
- Each attempt is recorded (`notifications` delivery status).
- Notifications never change business state.

### 2.6 Catalog (MOD02)
- Categories (hierarchical), brands, products, exact variants (SKU).
- Typed attribute definitions and variant attributes (category-specific vocabulary, BR02).
- Media assets.
- Controlled publication (draft → published) with source references (BR01).

### 2.7 Comparison (MOD03)
- Compares exact variants using the attribute dictionary.
- Returns differences, missing values, and price/stock freshness (`observed_at`, `expires_at`).
- No AI dependency for the comparison data itself.

### 2.8 Offers, Pricing & Commercial Policies (MOD02/03)
- `policy_versions`, `price_lists`, effective-dated `prices`, `promotions` + eligible variants.
- Delivery, returns, warranty, trade-in and installment **content** per approved terms (FR-21).
- Money handled per BR03.

### 2.9 Inventory & Reservations (MOD03)
- Branch inventory balances, an immutable movement ledger, time-limited stock holds.
- Store pickup reservations (staff-assisted).
- The authoritative source per BD-03 (ERP vs GEN-I managed) decides whether GEN-I writes balances or only mirrors them.

### 2.10 Repairs (MOD04)
- **Catalogue:** repair services, supported models, resources (technicians/workstations) and bookable slots.
- **Booking:** atomic, exclusive slot (BR04), contact snapshot, consent, reference number, reschedule/cancel.
- **Execution:**
  - jobs following the S2 lifecycle,
  - versioned estimates with consent bound to the exact version (BR06),
  - parts consumption,
  - append-only repair events,
  - warranty record.

### 2.11 Corporate (MOD05)
- RFQ header + exact item lines + uploaded files (quarantined).
- Owner/sales queue.
- Quotes with **immutable versions**: draft → approved → issued → accepted / declined / expired / superseded (BR08).
- Discount approval separated from author (A05 rule).
- Confirmation and follow-up notifications.

### 2.12 Knowledge Base (MOD06)
- Documents and immutable versions, classification and scope.
- Domain-reviewer approval.
- Chunking and FULLTEXT index.
- Withdrawal invalidates derived indexes.

### 2.13 AI Assistant, Registry, Tools & Auditing (MOD06/07)
- Model profiles and versions, AI policies, agent definitions/bindings.
- Prompt template registry (77 templates, 22 roles per S4).
- Sessions/messages, agent runs, tool calls, evidence, claims.
- Audit reviews, human review queue, evaluation sets.
- **Implementation is blocked until BD-07 and OT-10 are decided.**

### 2.14 Analytics & Reporting (MOD09)
- Consent-aware `analytics_events`.
- Metric definitions M01–M20 as versioned, deterministic queries.
- Metric snapshots and reports R01–R10, scoped to the requesting user's permissions.

### 2.15 Exports (MOD10)
- Asynchronous export jobs.
- CSV with formula-injection safety (R11).
- DOCX per approved templates (R12).
- Scoped, expiring downloads.

### 2.16 Admin Operations (MOD08)
- Not a separate data module.
- The Metronic workspaces for each module, delivered with that module (S2 §7 workspace list).

## 3. Dependency order

```
Platform (settings, UI kit, DataTables, audit, notifications base)
   └─ Identity & Access ── Organizations/Branches/Companies
         ├─ Catalog ─ Offers/Pricing ─ Comparison ─ Inventory & Reservations
         ├─ Repairs (needs Branches, Catalog variants for device models, Notifications)
         ├─ Corporate RFQ/Quotes (needs Companies, Catalog variants, Pricing)
         ├─ Knowledge ─ AI Registry ─ AI Assistant/Tools/Auditing (needs Catalog, Repairs, Corporate tools)
         └─ Analytics ─ Exports (need stable operational entities)
```
