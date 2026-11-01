# GEN-I — Business Modules (Phase 2)

| | |
|---|---|
| Delivered | 2026-11-01, branch `develop` |
| Source of truth | S2 blueprint §9 data dictionary (`docs/source/GENI_Full_Business_and_Software_Blueprint.pdf`), S1 FR-01…FR-27, S3 modules MOD01–MOD03 |
| Surfaces | Metronic admin (`/admin/...`) and REST API (`/api/v1/...`), both calling the same services |

Status labels used below:
- **Implemented** — code complete.
- **Tested** — covered by automated tests (SQLite and MySQL) and by the headless-browser walkthrough.
- **Partial** — only part of the scope exists.
- **Deferred** — not built in this phase.
- **Requires approval** — blocked on a GEN-I business decision.

## 1. Module map and blueprint reconciliation

| # | Requested module | Blueprint tables used | Key reconciliation |
|---|---|---|---|
| 1 | Organizations & Companies | `organizations`, `companies`, `company_members`, `addresses`, `users.organization_id` | An **organization** is the operating tenant (S2 "GEN-I operating organization"). A **company** is a corporate *customer* account inside an organization (S2 "corporate accounts and procurement entities"). Companies are **not** parents of branches. |
| 2 | Branches | `branches` | S2 defines branches as "Stores and service branches" and scopes them to the organization. |
| 3 | Product Catalog | `categories`, `brands`, `products`, `product_variants`, `attribute_defs`, `variant_attributes`, `media_assets`, `product_media` | Price and stock attach to the **exact variant** (BR02). Every saleable item therefore needs at least one variant; a single-SKU product has exactly one. |
| 4 | Stores, Pricing & Inventory | `policy_versions`, `price_lists`, `prices`, `inventory_balances`, `inventory_movements` | **Stores are branches**: no separate stores table (DV-29). Store-facing views are the branch profile (hours, location, stock) plus `GET /api/v1/branches/{id}/inventory`. Prices require an approved `tax_policy_id`, so a minimal policy-version module is included. |

**Dependency order:** organizations → (users, companies, branches, categories, brands, policies) → products → variants → (specifications, media, prices, stock).

## 2. Data model (as built)

All tables use `bigint` primary keys (OT-01 is still open) and carry `organization_id`, except `organizations` itself. Statuses are `VARCHAR` columns cast to PHP enums (`app/Enums`).

| Table | Main columns | Constraints and indexes |
|---|---|---|
| organizations | name (160), timezone, currency char(3), status `active\|suspended` | unique name |
| users (+) | organization_id nullable FK (restrict) | super administrators may have no organization |
| companies | legal_name (200), tax_reference (80, nullable), status `pending\|verified\|suspended`, account_owner_id → users (null on delete) | unique (org, legal_name); unique (org, tax_reference); index (org, status) |
| company_members | company_id, user_id, member_role `buyer\|approver\|viewer`, approval_limit decimal(18,2), currency, status | unique (company_id, user_id) |
| addresses | company_id (cascade), label, address_text, city, postal_code, country_code char(2), contact_phone | company-owned only (DV-17) |
| branches | code (30), name (120), address_text, city, phone, email, latitude/longitude, opening_hours JSON, status `active\|inactive` | unique (org, code); index (org, status) |
| policy_versions | policy_code, version, rule_body JSON, status `draft\|approved\|retired`, created_by, approved_by, approved_at, effective_from/to | unique (org, policy_code, version) |
| categories | parent_id (self, restrict), code, name_en, name_ar, status | unique (org, code); cycle-free (service) |
| brands | name, name_normalized, status | unique (org, name_normalized) |
| products | category_id, brand_id (restrict), model_code, name_en/ar, description, status `draft\|approved\|published\|retired`, source_reference | unique (org, brand_id, model_code) |
| product_variants | product_id, sku (80), variant_name, item_kind `device\|accessory\|spare_part`, condition `new\|refurbished\|used`, warranty_policy_id, status `active\|unavailable\|retired` | unique (org, sku) |
| attribute_defs | category_id, code, label, label_ar, value_type `text\|number\|boolean`, unit, comparison_order | unique (category_id, code) |
| variant_attributes | variant_id (cascade), attribute_id, text_value / number_value decimal(18,6) / boolean_value, source_reference, verified_at | unique (variant_id, attribute_id); exactly one typed value |
| media_assets | object_key (private `local` disk), mime_type, sha256, byte_size, classification `public\|internal\|restricted`, scan_status `pending\|clean\|blocked`, rights_reference | index (org, sha256) |
| product_media | product_id (cascade), variant_id (must belong to product), asset_id, alt_text, display_order | unique (product_id, asset_id) |
| price_lists | code, company_id (nullable, not exposed — DV-20), currency, status | unique (org, code) |
| prices | price_list_id, variant_id, min_quantity, unit_price decimal(18,2), tax_policy_id, valid_from, valid_to, created_by | index (list, variant, min_qty, valid_from); MySQL CHECK unit_price ≥ 0, min_quantity > 0, valid_to > valid_from |
| inventory_balances | branch_id, variant_id, on_hand, reserved, source_updated_at, version | unique (branch_id, variant_id); MySQL CHECK on_hand ≥ 0 and 0 ≤ reserved ≤ on_hand |
| inventory_movements | branch_id, variant_id, quantity_delta, on_hand_after, reason, source_event_id, note, created_by, created_at | unique (org, source_event_id); MySQL CHECK delta ≠ 0; immutable (model guard) |

**Delete behaviour:**
- Business records use `restrictOnDelete`.
- Only pure child rows cascade: addresses, specifications, product media placements.
- Records that have dependents cannot be deleted. They are deactivated or retired instead.
- No soft deletes are used: the blueprint models archival through `status`.

## 3. Business rules enforced

| Rule | Where enforced |
|---|---|
| Organization scope: users see and change only their organization; super admins are platform-wide; users without an organization see no business data | `BelongsToOrganization::scopeForUser`, route model binding (out-of-scope → **404**), `OrganizationScopedPolicy`, form request `exists` rules filtered by organization |
| Cross-organization links are impossible (category/brand/owner/member/tax policy/variant/branch) | Form requests, plus a check in `InventoryService` |
| Creating or deleting organizations is platform-level (super admins); nobody can suspend their own organization | `OrganizationPolicy`, `OrganizationService` |
| Company status: pending → verified/suspended, verified → suspended, suspended → verified | `CompanyService::TRANSITIONS` |
| Company members are users of the same organization; one membership per user; deletion blocked while members exist; moving a user to another organization blocked while they hold memberships | Requests, `CompanyService`, `UserService` |
| Branch code unique per organization; opening hours: 7 days, up to 3 intervals per day, `HH:MM`, close after open | `BranchRequest` |
| Branch deletion blocked if any stock record exists | `BranchService` |
| Category hierarchy has no cycles; deletion blocked if it has children, products or attributes | `CatalogService` |
| Brand name unique after normalisation (case and spacing) | `BrandRequest` + `name_normalized` |
| Product lifecycle (UC19 controlled publication): draft → approved → published → retired (→ draft); approved → draft. Transitions need `catalog.approve`. Publishing needs an active category, an active brand and at least one active variant. Only drafts can be deleted | `ProductService`, `ProductPolicy::approve` |
| Variant lifecycle: active ↔ unavailable, either → retired (final); deletion blocked once prices or stock exist | `ProductService` |
| Specifications: attribute must belong to the product's category or an ancestor; value must match `value_type`; exactly one typed value; source reference required | `VariantAttributesRequest`, `ProductService` |
| A category cannot change while specifications exist; a value type cannot change once values exist | Services |
| Images: JPG/PNG/WebP only (no SVG), ≤ 5 MB, 64–8000 px; stored privately with SHA-256; new images are `pending`; marking clean or blocked needs `catalog.approve`; blocked images are never served | `ProductMediaRequest`, `ProductService`, preview/download routes |
| Policy versions: auto-incremented version per code; drafts editable; approval needs `policies.approve` **by a different user than the author**; approved versions are immutable; retire only approved ones | `PolicyService`, `PolicyVersionPolicy` |
| Prices (BR03): decimal amounts (2 places); `min_quantity` ≥ 1; approved same-organization tax policy required; new prices cannot start in the past; a new open-ended price closes the current open-ended one (history kept); any other overlap per list/variant/quantity is rejected; effective prices are immutable; only future prices can be edited or deleted; list currency is fixed once prices exist | `PriceRequest`, `PricingService` (list row lock serialises changes) |
| Stock (BR04, BR11): manual reasons are only `receipt` (positive) and `adjustment` (± with a required note); `sale`/`return`/`consumption` are reserved for future modules; branch must be active; no receipts for retired variants; on_hand ≥ 0 and ≥ reserved | `InventoryAdjustmentRequest`, `InventoryService`, MySQL CHECK |
| Stock changes are atomic and race-safe: a single transaction with a row lock (`SELECT … FOR UPDATE`) on the balance, version increment, immutable movement with a unique `source_event_id`, idempotent replays (`Idempotency-Key`) and 409 on key reuse with a different payload | `InventoryService` |
| Every create/update/status/delete, specification, media, price and stock change is audited (actor, subject, metadata; secrets redacted) | `AuditLogger` |

## 4. Permission matrix

Permissions are seeded idempotently from `config/admin.php`. **Only `super-admin` receives the new business permissions by default.** The existing `admin`, `manager` and `staff` roles are unchanged: assigning business permissions to real staff roles requires GEN-I's role catalogue (BD-14). Super admins can build such roles today in **Roles & permissions**.

| Group | view | create | update | delete | other |
|---|---|---|---|---|---|
| organizations | list/show | create (super admin only) | edit, status | delete (super admin only, empty org) | — |
| companies | list/show, members, addresses | create | edit, status, members, addresses | delete (no members/price lists) | — |
| branches | list/show | create | edit, status | delete (no stock) | — |
| catalog | categories, brands, products, variants, images | create | edit, variants, specifications, upload images | delete (draft / unused) | **approve**: product status transitions, image review |
| policies | list/show | — | **manage**: create/edit/delete drafts | — | **approve**: approve/retire (not own drafts) |
| pricing | price lists, prices | — | **manage**: lists, add/edit/delete future prices | — | — |
| inventory | balances, movements, branch stock | — | — | — | **adjust**: receipts/adjustments |

## 5. Admin pages

| Module | Pages |
|---|---|
| Organizations | List (search, status), create/edit, profile (counts, activity), status, delete |
| Companies | List (search, status, organization), create/edit, profile with members (add/remove) and addresses (add/remove), status transitions, delete |
| Branches | List (search, status, organization), create/edit with a 7-day hours editor and coordinates, profile (details, hours, directions, stock totals, recent stock), status, delete |
| Categories | List (search, status, top level), create/edit (parent), profile (children, own and inherited attributes, add/remove attribute) |
| Brands | List, create/edit (with status) |
| Products | List (search incl. SKU, status, category, brand, item kind), create/edit, profile: variants (add, status, delete), specification editor per variant, image upload/review/remove, lifecycle actions |
| Policies | List, draft create/edit (JSON rule body), profile with approve/retire |
| Price lists | List, create/edit, profile with prices table (period filter) and add-price modal |
| Inventory | Stock levels (branch, stock filter, search) with adjustment modal; movements ledger (branch, reason, date range, search) |

Every page has Arabic (default, RTL) and English (LTR) text, light and dark themes, permission-aware buttons and menu, confirmation dialogs, toasts, inline validation, empty states and DataTables loading states.

## 6. Deviations from the blueprint

See `docs/database-design.md` §4a, **DV-16 to DV-29**.

## 7. Open decisions, limitations, deferred and conditional scope

| Item | Status | Notes |
|---|---|---|
| Staff role catalogue (who gets which business permission) | **Requires approval (BD-14)** | Only super-admin holds business permissions by default |
| Authoritative stock source (ERP vs GEN-I) | **Requires approval (BD-03)** | Manual receipts and adjustments are interim, behind `inventory.adjust`; the source notice is shown in the UI |
| Tax/warranty rule schema (`rule_body`) | **Requires approval (BD-04/BD-06)** | Structure is validated as a non-empty JSON object; no tax calculation is performed |
| Automated malware scanning of uploads | **Requires decision (OT-13)** | Interim: manual review sets `scan_status`; files are private until marked clean |
| Public identifiers (UUIDs) in the API | **Open (OT-01)** | API exposes numeric IDs |
| Store-specific (branch) pricing | Not documented in S2 → **not implemented** | Prices are per price list and variant |
| Contract (company) price lists, approval limits, corporate purchasing/credit | **Conditional (S3)** | Columns exist; not exposed or enforced |
| Promotions / promotion_variants | **Deferred** (MOD02 offers) | — |
| Stock holds, store reservations, `reserved` quantity changes | **Deferred** (MOD03 reservation workflow) | `reserved` is always 0 until then |
| Serialized units | **Deferred** (source-dependent) | — |
| Departments | **Deferred** (OT-03 scoped roles) | — |
| Customer-owned addresses | **Deferred** (needs customers table) | Addresses are company-owned only |
| Public (unauthenticated) catalog/store API for the React site | **Deferred** | All business API endpoints currently require Sanctum and staff permissions |
| Notifications for business events | Not implemented | No documented notification requirement for these modules |

## 8. Module completion checklist

| Capability | M1 Org/Company | M2 Branches | M3 Catalog | M4 Pricing/Inventory |
|---|---|---|---|---|
| Migrations, FKs, uniques, indexes (MySQL verified) | ✅ Tested | ✅ Tested | ✅ Tested | ✅ Tested (+ CHECK constraints) |
| Models, relationships, enums | ✅ | ✅ | ✅ | ✅ |
| Form requests (shared admin/API) | ✅ Tested | ✅ Tested | ✅ Tested | ✅ Tested |
| Services with transactions and audit | ✅ Tested | ✅ Tested | ✅ Tested | ✅ Tested |
| Policies (permission + organization scope) | ✅ Tested | ✅ Tested | ✅ Tested | ✅ Tested |
| Status transitions | ✅ Tested | ✅ Tested | ✅ Tested (product, variant, image) | ✅ Tested (policy, list) |
| Delete protection | ✅ Tested | ✅ Tested | ✅ Tested | ✅ Tested |
| Admin pages (list/create/edit/show, filters) | ✅ Browser-verified | ✅ Browser-verified | ✅ Browser-verified | ✅ Browser-verified |
| REST API + resources + pagination | ✅ Tested | ✅ Tested | ✅ Tested | ✅ Tested |
| Arabic/English, RTL/LTR | ✅ | ✅ | ✅ | ✅ |
| Cross-organization isolation | ✅ Tested | ✅ Tested | ✅ Tested | ✅ Tested |
| Idempotent stock changes | — | — | — | ✅ Tested |
| Contract pricing, credit, holds/reservations, promotions | — | — | — | ⏸ Deferred / conditional |
