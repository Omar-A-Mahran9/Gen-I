# GEN-I — Database Design Strategy

| | |
|---|---|
| Engine | MySQL 8.x, InnoDB, `utf8mb4` (TD-02). Local: Homebrew `mysql@8.4`. XAMPP MariaDB 10.4 is **not** a supported target |
| Tests | SQLite in-memory for portable tests; MySQL test database for engine-specific behaviour |
| Source model | S2 §8–10: 102 proposed logical tables, written for PostgreSQL |
| Rule | Tables are created **per module, when that module's phase starts**, from approved requirements. Twasol's schema is not reused (TD-03). |

## 1. Conventions

| Topic | Convention | Status |
|---|---|---|
| Table names | Plural `snake_case`; S2 names kept where possible | Approved direction |
| Primary keys | `id BIGINT UNSIGNED AUTO_INCREMENT` + `uuid CHAR(36) UNIQUE` public identifier on API-exposed entities | **Proposed (OT-01)**; S2 specifies uuid PKs |
| Tenant scope | `organization_id` FK on tenant tables, first column of composite indexes | **Proposed (OT-02)** |
| Foreign keys | `foreignId()->constrained()`; explicit `restrictOnDelete` for business records; `cascadeOnDelete` only for pure child rows | Approved direction |
| Money | `DECIMAL(14,2)` amount + `CHAR(3)` currency; rates/percentages `DECIMAL(9,4)`; never FLOAT | Proposed (BR03) |
| Time | Stored in UTC (`timestamp`/`datetime`), rendered in branch/customer timezone | Proposed (OT-08) |
| States & types | `VARCHAR` column + PHP backed enum cast; no MySQL `ENUM` (easier migrations) | Proposed |
| Bilingual text | `name_ar` / `name_en` paired columns | Proposed (OT-05) |
| Soft deletes | Only on master data (catalog, branches, users). **Never** on ledgers, versions, events, audit | Proposed |
| Immutability | Version tables (quote_versions, repair_estimates, document_versions, policy_versions, model_versions) are insert-only; edits create a new version | From S2 |
| History | Append-only event tables (repair_events, inventory_movements, audit_events, payment_events) | From S2 |
| Optimistic locking | `version INT` column on records with concurrent state transitions (repairs, quotes) | Proposed (S2 "current record version") |
| Migration naming | One migration per table group per module; no edits to migrations already merged to `develop` | Approved direction |

## 2. Framework and package tables (Phase 1–3)

| Table(s) | Origin |
|---|---|
| `users`, `password_reset_tokens`, `sessions` | Laravel skeleton (`users` extended for GEN-I) |
| `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Laravel skeleton |
| `personal_access_tokens` | Sanctum (TD-11) |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Spatie (TD-10). Replace S2's `roles`, `permissions`, `user_roles`, `role_permissions` (scoping per OT-03) |
| `notifications` | Laravel database notifications channel (in-app). **Name clash:** S2's delivery-attempt table `notifications` will be named `notification_deliveries` |
| `settings` | GEN-I `SettingsService` (TD-12): `key` unique, `value` JSON/text, `type`, `group`, timestamps |
| `idempotency_keys` | API idempotency (architecture §6): `scope`, `key`, `request_hash`, `response_code`, `response_body`, `expires_at`; unique (`scope`,`key`) |

## 3. Domain table groups (from S2 §8) and planned phase

**Scope:** C = committed, Cond = conditional (tables are not created until the module is enabled).

| Group | Tables (S2 names) | Scope | Module / phase |
|---|---|---|---|
| Identity & organization | organizations, branches, departments, users*, customers | C | MOD01 · Phase 3 |
| Corporate identity & service | companies, company_members, addresses, consents, support_cases | C | MOD01/MOD05 · Phase 3 + MOD05 |
| Catalog | categories, brands, products, product_variants, attribute_defs, variant_attributes, media_assets, product_media | C | MOD02 |
| Pricing & policies | policy_versions, price_lists, prices, promotions, promotion_variants | C | MOD02/03 |
| Inventory | inventory_balances, inventory_movements, stock_holds, serialized_units | C (write mode depends on BD-03) | MOD03 |
| Store reservation | reservations | C | MOD03 |
| Retail orders & billing | carts, cart_items, orders, order_items, payments, payment_events, shipments, shipment_items, invoices, invoice_items, credit_notes | **Cond** | — |
| Returns, trade-in, installments | returns, return_items, refunds, tradein_requests, tradein_offers, installment_applications | **Cond** | — |
| Repair operations | customer_devices, repair_services, repair_service_models, repair_resources, booking_slots, repair_bookings, repair_jobs, repair_estimates, repair_parts, repair_events | C | MOD04 |
| Corporate procurement | rfqs, rfq_items, rfq_files, quotes, quote_versions, quote_items | C | MOD05 |
| Corporate credit | credit_accounts, credit_reviews | **Cond** | — |
| Workflow & delivery | workflow_tasks, approval_requests, approval_decisions, notification_deliveries (S2 `notifications`), outbox_events | C | Phase 4 + modules |
| Integration & knowledge | connectors, ingestion_runs, documents, document_versions, knowledge_approvals, chunks, search_entries | C | MOD06 |
| AI configuration | model_profiles, model_versions, ai_policies, agent_definitions, agent_bindings | C | MOD07 |
| AI execution & evaluation | ai_sessions, ai_messages, agent_runs, tool_calls, retrieval_runs, evidence_items, response_claims, audit_reviews, human_reviews, evaluation_sets, evaluation_cases, evaluation_results | C | MOD06/07 |
| Audit, analytics, continuity | audit_events, analytics_events, metric_definitions, metric_snapshots, retention_policies, backup_runs | C | Phase 3 (audit_events) · MOD09 · MOD11 |

\* `users` already exists in the skeleton. S2's roles/permissions junctions are covered by Spatie (§2).

The detailed typed columns are in S2 §9 (the typed data dictionary). Each module phase transcribes that module's tables from S2 §9, adapts them per §4 below, and records any deviation in that phase's pull request and in `business-decisions.md`.

## 4. PostgreSQL → MySQL adaptations

| S2 PostgreSQL feature | MySQL approach | Verification |
|---|---|---|
| Row-Level Security per `org_id` | `BelongsToOrganization` global scope + Policies + Services that always set `organization_id` from the authenticated context (never from client input) | Cross-scope feature tests (N01) |
| Composite tenant FKs `(org_id, parent_id)` | Standard FK on `parent_id` + Service/Request validation that parent and child share `organization_id`; optional composite unique (`organization_id`,`id`) where needed | Unit + feature tests |
| Partial unique index (one active booking per slot) | Generated column `active_slot_id = IF(status IN ('held','confirmed','checked_in'), slot_id, NULL)` with a UNIQUE index (MySQL allows multiple NULLs) + `lockForUpdate` on the slot | MySQL integration test with concurrent attempts |
| Exclusion constraint on overlapping slot ranges | Serialized allocator in `SlotService` (generates non-overlapping slots per resource under lock) | MySQL integration test |
| `NULLS NOT DISTINCT` scoped role uniqueness | Normalized non-null scope key column (e.g. `scope_key` = `'org:1'`, `'branch:4'`) | Unit test |
| `timestamptz` | UTC storage + app timezone conversion | — |
| `numeric` | `DECIMAL` | — |
| GIN index on `chunks.search_vector` | InnoDB `FULLTEXT` index; Arabic tokenization quality validated in MOD06 | Search golden tests |
| Pending-outbox partial index | Regular index on (`published_at`, `next_retry_at`) | — |
| CHECK constraints | MySQL 8.0.16+ enforces `CHECK`; also validated in Form Requests/Services. Cross-row invariants (reserved ≤ on_hand, refunds ≤ captured) enforced in transactional Services + reconciliation jobs (as S2 §10 already requires) | Unit + integration tests |

## 4a. Blueprint deviation register

The 102 S2 tables are the **primary reference**. They are **not** created in bulk. Each module phase reviews its tables against the checklist in §4b. Every deviation from S2 is listed here with its technical reason. Proposed deviations need approval before the owning module's migrations are written.

| # | S2 design | GEN-I deviation | Technical reason | Status |
|---|---|---|---|---|
| DV-01 | All 102 tables as one logical schema | Created **per module phase**; conditional tables (retail orders, returns/trade-in/installments, credit) are not created until activated | Keeps unreviewed, unused structures out of production; S3 already scopes conditional modules out of the baseline | Approved (owner, 2026-11-01) |
| DV-02 | `uuid` PK on every table | `BIGINT` PK + unique public `uuid` on API-exposed tables | InnoDB clusters rows by PK, so random/36-char PKs inflate every secondary index. Spatie and Sanctum morph keys default to big integers. Public UUIDs still prevent ID guessing | Proposed (OT-01) |
| DV-03 | `org_id` + PostgreSQL RLS (`FORCE ROW LEVEL SECURITY`) | `organization_id` + Eloquent global scope + Policies + Services that derive scope server-side | MySQL has no row-level security | Proposed (OT-02) |
| DV-04 | Composite tenant FKs `(org_id, parent_id) → parent(org_id, id)` | Single-column FK + same-organization validation in Services/Form Requests; composite unique (`organization_id`,`id`) only where a child must be pinned | Composite FKs are possible in MySQL but make Eloquent relations awkward. Validation plus cross-scope tests give the same guarantee at the app boundary | Proposed |
| DV-05 | `roles`, `permissions`, `user_roles`, `role_permissions` | Spatie tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`; scope via membership tables | Approved use of Spatie (TD-10). S2's scoped assignment is mapped per OT-03 | Approved package; scope mapping proposed (OT-03) |
| DV-06 | Partial unique index on `repair_bookings(org_id, slot_id) WHERE status IN (…)` | Stored generated column `active_slot_id` + UNIQUE index | MySQL has no partial indexes, and its unique indexes ignore NULLs | Proposed |
| DV-07 | Exclusion constraint on overlapping slot ranges | Serialized slot allocator under `lockForUpdate` | MySQL has no exclusion constraints | Proposed |
| DV-08 | `NULLS NOT DISTINCT` for optional scope uniqueness | Non-null normalized `scope_key` column | MySQL unique indexes treat NULLs as distinct | Proposed |
| DV-09 | `timestamptz` | UTC `timestamp`/`datetime` + application timezone | MySQL has no timezone-aware column type | Proposed |
| DV-10 | `numeric` money | `DECIMAL(14,2)` + `CHAR(3)` currency | Equivalent exact type; precision to be confirmed with BD-04 | Proposed |
| DV-11 | GIN index on `chunks.search_vector` (tsvector) | InnoDB `FULLTEXT` index | MySQL has no tsvector/GIN. Arabic tokenization quality must be validated in MOD06 | Proposed |
| DV-12 | Partial index on pending `outbox_events` | Composite index (`published_at`, `next_retry_at`) | No partial indexes in MySQL | Proposed |
| DV-13 | Table `notifications` (delivery attempts) | Renamed `notification_deliveries` | Laravel's database notification channel already uses `notifications` | Proposed |
| DV-14 | Lifecycle values as PostgreSQL enums/lookup checks | `VARCHAR` + PHP backed enums (+ `CHECK` where useful) | Avoids `ALTER TABLE … ENUM` migrations; keeps one source of truth in PHP | Proposed |
| DV-15 | Separate runtime/migration DB roles (RLS bypass protection) | Separate DB users for app runtime vs migrations in production (least privilege) | RLS-specific concern no longer applies; least-privilege still applies | Proposed (MOD11) |

## 4b. Per-module table review checklist

Before writing migrations for a module, produce a short review in the module's PR covering:
1. **Entities and relationships:** transcribe the module's tables from S2 §9 and confirm each FK target and its cardinality (unique FK = 0..1).
2. **Foreign keys:** required vs nullable; delete behaviour (`restrict` for business records); cross-module FKs only to already-existing tables (module dependency order, `module-map.md` §3).
3. **Unique constraints:** composite uniques from S2 §10 (role-permission pairs, memberships, SKUs, branch/variant inventory, version numbers, provider/source event keys).
4. **Indexes:** FKs, `organization_id`-first composites, the status/date indexes listed in S2 §10.
5. **MySQL compatibility:** apply DV-01…DV-15; flag any new S2 feature that has no direct MySQL equivalent.
6. **Business rules:** which BR01–BR12 the tables support, and which invariants must be enforced in Services instead of constraints.
7. **Dependencies:** which approved business decisions (BD-xx) the table content depends on.

## 5. Indexing (S2 §10)

- Index every high-volume FK. Put `organization_id` first where queries are tenant-scoped.
- Selective composite indexes:
  - booking (`slot_id`, `status`);
  - RFQ (`company_id`, `owner_id`, `status`);
  - quote (`quote_id`, `version`), (`expires_at`);
  - active holds (`expires_at`);
  - AI runs (`state`, `deadline_at`);
  - failed `notification_deliveries`.
- Partition audit/analytics/AI traces only after measured need.
- Validate query plans with representative data.

## 6. Seeding

| Seeder | Content | Environment |
|---|---|---|
| `OrganizationSeeder` | Single GEN-I organization (OT-02) | all |
| `PermissionSeeder` | Permission names per enabled module (idempotent, `firstOrCreate`) | all |
| `RoleSeeder` | Roles from the approved role catalogue (BD-14); until approved, only a `system-administrator` role for bootstrap | all |
| `AdminUserSeeder` | Bootstrap admin from `.env` values (`GENI_ADMIN_EMAIL`, `GENI_ADMIN_PASSWORD`). **No hard-coded credentials** | local / first deploy |
| `SettingsSeeder` | Default settings keys (locale `ar`, theme `light`) | all |
| Demo/fixture seeders | Synthetic data only, clearly marked, never run in production | local / test |

## 7. Data protection

- Personal data columns are documented per table (S2 restricted data).
- Device serials, customer contacts and company data need scoped access.
- Retention jobs follow `retention_policies` once BD-13 is approved.
- Deletion propagates to search/derived indexes (S2).
- Backups and restore drills belong to MOD11.
