# GEN-I — Catalog Import Contract (v1)

| | |
|---|---|
| Task | S1-D2-01 "API conventions, validation, seeds and catalog import contract" (S3 plan; acceptance: consistent errors, pagination and correlation IDs; no real PII in seeds; UC04/UC19) |
| Sources | S2 §9.3 data dictionary (categories, brands, products, product_variants, attribute_defs, variant_attributes); BR01, BR02; UC19 ("validate required fields types units and source"; "invalid batch quarantined"); S2 migration guidance ("quarantine invalid units, identifiers and missing references") |
| Code | `App\Services\Business\CatalogImportService`, `App\Services\Business\Import\*`, `App\Http\Requests\Business\CatalogImport*Request`, `Api\V1\CatalogImportController`, `Admin\Business\CatalogImportController` |
| Status | Implemented and tested (`tests/Feature/Business/CatalogImportTest.php`). Limits in §2 are technical defaults, adjustable in `config/admin.php` → `catalog_import` |

## 1. Scope

One CSV row = **one exact saleable variant** (BR02) plus the product (model identity) it belongs to, and optionally its typed specifications.

| In scope | Out of scope (never imported) |
|---|---|
| Products (create as `draft`, update details) | Prices, tax, promotions (BR03, PricingService only) |
| Variants (create `active`, update name/kind/condition) | Stock quantities (InventoryService only; source authority BD-03) |
| Typed specifications (`variant_attributes`) | Product/variant statuses and publication (UC19 approval via `catalog.approve`) |
| | Categories, brands and attribute definitions (referenced, **never created**) |
| | Warranty policies, images, organizations |

## 2. File format

| Item | Rule |
|---|---|
| Format | CSV only (RFC 4180): comma delimiter, `"` enclosure, `""` escapes a quote, quoted cells may contain commas and line breaks. XLSX is not accepted — save as "CSV UTF-8" (no spreadsheet dependency is installed) |
| Encoding | UTF-8; a leading BOM is ignored. Other encodings are rejected |
| Extension | `.csv` (or `.txt`) |
| Size | ≤ 5 120 KB (`catalog_import.max_kb`) |
| Rows | 1 to 5 000 data rows (`catalog_import.max_rows`); blank lines are ignored |
| Header | Required, first non-blank line. Names are case-insensitive and trimmed. Duplicate, blank or unknown columns reject the file |
| Row numbers | Reported as CSV record numbers: header = row 1, first data row = row 2 |
| Template | `GET /api/v1/catalog/imports/template[?category_code=…]` or the admin page's "Download template" (adds the category's `spec:` columns) |

## 3. Columns

| Column | Req. | Type / rule | Maps to |
|---|---|---|---|
| `category_code` | ✔ | existing category code in the organization; `[a-z0-9_-]`, ≤ 50; lower-cased | `products.category_id` |
| `brand_name` | ✔ | existing brand, matched by normalized name (case and spacing ignored); ≤ 100 | `products.brand_id` |
| `model_code` | ✔ | string ≤ 100 | `products.model_code` |
| `product_name_en` | ✔ | string ≤ 200 | `products.name_en` |
| `product_source_reference` | ✔ | string ≤ 2 000 (S2: "required approved source") | `products.source_reference` |
| `sku` | ✔ | `[A-Za-z0-9._-]`, ≤ 80; upper-cased | `product_variants.sku` |
| `variant_name` | ✔ | string ≤ 160 | `product_variants.variant_name` |
| `item_kind` | ✔ | `device` \| `accessory` \| `spare_part` | `product_variants.item_kind` |
| `condition` | ✔ | `new` \| `refurbished` \| `used` | `product_variants.condition` |
| `product_name_ar` | | string ≤ 200 | `products.name_ar` |
| `product_description` | | string ≤ 10 000 | `products.description` |
| `spec:<attribute_code>` | | one column per attribute; value typed per definition: `number` (≤ 6 decimals, \|x\| < 10¹²), `boolean` (`true`/`false`/`1`/`0`), `text` (≤ 2 000) | `variant_attributes` |
| `spec_source_reference` | if any spec value | string ≤ 2 000 (S2: source_reference required) | `variant_attributes.source_reference` |
| `spec_verified_at` | | date ≤ now; defaults to import time for new/changed values | `variant_attributes.verified_at` |

The `<attribute_code>` must exist in the organization (else the file is rejected) **and** apply to the row's category or one of its ancestors (else the row fails). The nearest category wins when codes repeat in the lineage. Units come from the attribute definition (`attribute_defs.unit`); values carry no unit text.

## 4. Identity, creation and update

| Entity | Natural key | Behaviour |
|---|---|---|
| Product | organization + brand + `model_code` (case-insensitive) | Missing → created as **draft**. Existing → product columns updated. A category change is refused while the product has specifications (existing rule) |
| Variant | organization + `sku` | Missing → created **active** under the row's product. Existing under the same product → name/kind/condition updated. Existing under **another** product → row fails (`sku_belongs_to_other_product`); an import never moves a variant, its prices or its stock |
| Specification | variant + attribute | Merged: supplied values are created/updated; columns left empty or absent are untouched (never deleted). Same value and source → unchanged (keeps `verified_at`) |

- Empty optional cells **never clear** existing values.
- Each variant row gets its own specification values, so different variants never share prices or specifications.
- Re-importing an unchanged file is idempotent: every row is reported `unchanged`.
- All writes go through `ProductService` and are audited (`product.created/updated`, `variant.created/updated`, `variant.specifications_updated`), plus one `catalog.imported` entry per non-dry run.

## 5. Validation order and duplicate detection

1. **File** (422 `validation_failed`, `errors.file`): type/size, readable, UTF-8, header present, comma delimiter, no duplicate/blank/unknown columns, required columns present, known `spec:` codes, 1 ≤ rows ≤ max.
2. **Row fields**: cell count, required values, types, lengths, enums, formats, date.
3. **References**: category and brand exist **in the target organization**; specifications apply to the category and match their type; source given.
4. **Cross-row**: a repeated SKU rejects **every** occurrence (`duplicate_in_file`); rows of the same product with different non-empty product values are all rejected (`conflicting_product_data`); existing SKUs owned by another product are rejected.
5. **Write**: business rules checked by the services (e.g. category locked by specifications) and write conflicts.

## 6. Modes and transactions

| Field | Values | Default |
|---|---|---|
| `mode` | `atomic`: any invalid row, or any failure while writing, rolls back the whole batch → `quarantined`. `partial`: valid rows commit, each in its own transaction; invalid rows are skipped and reported | `atomic` |
| `dry_run` | `true`: run every check, write nothing, status `validated` | `false` |

Super administrators must send `organization_id`; other users always import into their own organization (organization scope, TD-27).

## 7. API

`POST /api/v1/catalog/imports` — `multipart/form-data` (`file`, `mode?`, `dry_run?`, `organization_id?`). Needs a Sanctum token, an active account and the `catalog.import` permission (super-admin only by default, TD-28). Throttle: 10/min.

| Outcome | HTTP | Body |
|---|---|---|
| committed / partially_committed / validated (dry run) | 200 | `{"success": true, "data": <report>, "message": "…"}` |
| quarantined (nothing saved) | 422 | `{"success": false, "message": "…", "code": "import_rejected", "data": <report>, "request_id": "…"}` |
| file-level problem | 422 | `{"success": false, "code": "validation_failed", "errors": {"file": ["…"]}, …}` |
| missing permission | 403 | `forbidden` |

Report:
```json
{
  "status": "partially_committed", "mode": "partial", "dry_run": false, "contract_version": "1",
  "file": {"name": "catalog.csv", "sha256": "…"},
  "summary": {"total_rows": 4, "valid_rows": 3, "created_rows": 3, "updated_rows": 0, "unchanged_rows": 0,
              "failed_rows": 1, "duplicate_rows": 0, "products_created": 2, "products_updated": 0, "specifications_written": 4},
  "errors": [{"row": 5, "column": "condition", "code": "invalid_value", "message": "The selected condition is invalid.", "value": "broken"}],
  "error_count": 1, "errors_truncated": false
}
```
At most 1 000 error entries are listed; the counters always cover every row.

Error codes: `required`, `too_long`, `invalid_format`, `invalid_value`, `invalid_date`, `invalid_type`, `malformed_row`, `unknown_category`, `unknown_brand`, `attribute_not_applicable`, `duplicate_in_file`, `conflicting_product_data`, `sku_belongs_to_other_product`, `business_rule_violation`, `conflict`.

`GET /api/v1/catalog/imports/template[?category_code=…&organization_id=…]` → `text/csv` header row (UTF-8 BOM).

**Admin:** *Catalog → Import catalog* (`/admin/catalog/import`) is an AJAX form (`data-geni-ajax-form`): file errors appear inline under the field, the button shows a loading state, and after processing the page reloads to render the same report (rejected imports show an error toast).

## 8. Example

```csv
category_code,brand_name,model_code,product_name_en,product_source_reference,sku,variant_name,item_kind,condition,spec:storage_gb,spec_source_reference
phones,Example Brand,EX-1,Example Phone,Supplier sheet 2026-11,EX1-128-BLK,128GB Black,device,new,128,Spec sheet A
phones,Example Brand,EX-1,Example Phone,Supplier sheet 2026-11,EX1-256-BLK,256GB Black,device,new,256,Spec sheet A
```
(Synthetic illustration. The category `phones`, the brand and the attribute `storage_gb` must exist first.)

## 9. Open points

| Item | Status |
|---|---|
| S2 `connectors` / `ingestion_runs` (ERP/connector imports with cursors) | Not built: it needs connectors (UC20, MOD06) and source authority (BD-03). File imports are traced through the audit log instead |
| Approved four-category attribute vocabulary (phones, TVs, smart home, appliances) | S1-D2-02; categories and attributes are created by staff until approved |
| Warranty policy and image columns | Not in v1 (needs approved policy codes / media rights workflow) |
| XLSX input | Not supported (would need a new dependency, which needs approval) |
| Row/size limits | Technical defaults; confirm with operations |
