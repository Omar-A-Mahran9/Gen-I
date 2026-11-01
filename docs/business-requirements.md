# GEN-I — Business Requirements (Consolidated)

| | |
|---|---|
| Status | Consolidation of supplied GEN-I documents. **Not a new requirements baseline.** |
| Prepared | 2026-11-01 (Phase 0 — Architecture Finalization) |
| Rule | Nothing in this file is an approved business policy unless the source marks it as approved. Proposed targets stay labelled *proposed*. |

## 1. Source documents and authority

The source files are currently stored **outside the repository** in `~/Downloads/` (see decision OT-09 in `business-decisions.md`).

| Ref | File | Date / version | Status stated by the document | Use in this project |
|---|---|---|---|---|
| S1 | `GEN-I_Project_Documentation_B2B_Updated.pdf` | undated draft | "Confidential draft"; scope to be validated by GEN-I stakeholders | **Primary requirements baseline**: FR-01…FR-27, NFR-01…NFR-12 |
| S2 | `GENI_Full_Business_and_Software_Blueprint.pdf` | 2026-10-27, v1.0 | "Proposed design for review and scope approval" | Detailed specification: actors, use cases UC01–UC32, business rules, data model, API families |
| S3 | `GENI_Agile_Implementation_Project_Plan.pdf` / `.xlsx` | 2026-10-28, v1.0 | "Baseline proposed for execution approval" | Module boundaries MOD01–MOD11, committed vs conditional scope, sprints, milestones |
| S4 | `GENI_AI_Prompt_Playbook_and_Reporting_Catalogue.pdf` | 2026-10-28, v1.0 | "Proposed implementation specification" | AI prompt registry (77 templates, 22 roles), reports R01–R12, metrics M01–M20 |
| S5 | `GENI_Design_Review_and_Delivery_Recommendations.pdf` | 2026-10-27 | Submitted for management review | Design corrections and open decisions |

**Precedence when the documents disagree:**
1. Explicit decisions you have approved, recorded in `business-decisions.md`.
2. S1 (the baseline requirements).
3. S2 (the detailed design).
4. S3 and S4.

**Technology choices in S2 and S3 are superseded.** S2 and S3 propose React + FastAPI + PostgreSQL. The approved technical direction is Laravel 13 + MySQL + Metronic admin (see TD-01 and TD-02). The *business* content of S2 and S3 remains the reference.

> PDF text was extracted programmatically. Table layouts in the PDFs may have been flattened, so check any exact wording against the original before treating it as contractual.

## 2. Product summary (S1, S2)

GEN-I is a multi-category technology retail and service platform. It covers:
- **Product categories:** phones (with an iPhone focus in the designs), TVs, smart home and home appliances, plus audio and accessories.
- **Customer services:** product discovery and comparison, purchase or store reservation, trade-in and installment messaging, iPhone repair booking, and a B2B corporate supply / Request-a-Quote journey.
- **AI assistant:** the GEN-I Assistant gives guidance, but prices and stock are always confirmed by staff or by authoritative systems.

**Channels.** The public responsive web frontend (React, a separate application) is **out of scope** for this repository. This repository provides:
- the backend,
- the REST API `/api/v1`, and
- the Metronic staff administration dashboard.

## 3. Actors (S2 §3)

| ID | Actor | Primary concern |
|---|---|---|
| A01 | Visitor | Public catalog, comparison, assistant, stores, enquiries |
| A02 | Individual customer | Own contact/consent, reserve/purchase, own repairs, trade-in/financing referral |
| A03 | Corporate buyer | RFQs and own-company quotations |
| A04 | Corporate approver | Approves exact current quotation/purchase within delegated authority |
| A05 | Sales representative | Qualifies RFQs, prepares versioned quotes; cannot self-approve exceptional discounts |
| A06 | Catalog & pricing manager | Categories, specs, media, prices, offers with controlled publication |
| A07 | Store & warehouse operator | Branch stock, holds, pick/dispatch, returns inspection |
| A08 | Repair coordinator | Services, slots, bookings, parts/capacity |
| A09 | Repair technician | Diagnosis, estimates, parts consumption, tests; cannot replace customer consent |
| A10 | Finance officer | Reconciliation, refunds, corporate credit (conditional) |
| A11 | Customer support officer | Enquiries, AI escalations, permitted cancellations |
| A12 | Knowledge editor | Uploads/classifies knowledge sources |
| A13 | Domain reviewer | Approves knowledge and sensitive commercial text |
| A14 | AI administrator | Model profiles, agents, tools, audit routing, budgets; cannot grant own business authority |
| A15 | System administrator | Identity, connectors, secrets references, deployments, backups |
| A16 | Human AI reviewer | Reviews AI evidence and proposed payloads |
| A17 | Auditor & privacy officer | Read-only scoped audit evidence, retention |
| A18 | CEO & business analyst | Approved KPIs, risks, scope |
| X01–X05 | External systems | Payment/finance, inventory ERP/carrier, notification gateway, online model service, local model runtime |

**Authorization principle (S2 §3).** Access is decided by combining several scopes:
- organization,
- branch,
- department,
- company,
- capability (permission),
- own-record ownership.

**Separation of duties is mandatory.** A technical or AI role never confers refund, credit, price-change or customer-consent authority.

## 4. Functional requirements (S1 §4, mapped by S2 §3)

The **Scope** column comes from S3 §4: **C** = committed release baseline, **Cond** = conditional on a management decision, **Content** = presentation of approved content only.

| ID | Requirement (abridged) | Use cases | Module | Scope |
|---|---|---|---|---|
| FR-01 | Primary product categories and repair/support navigation | UC04 | MOD02 | C |
| FR-02 | Search entry point for products/services | UC04 | MOD02 | C |
| FR-03 | Clear CTAs for shopping and store visits | UC04 UC07 UC08 | MOD02/03 | C (shopping = reservation; checkout Cond) |
| FR-04 | Configured promotional content and featured products | UC04 UC05 UC19 | MOD02/03 | C |
| FR-05 | Assistant access from relevant pages | UC16 | MOD06 | C |
| FR-06 | Assistant supports product-discovery and repair-intent prompts | UC16 UC10 | MOD06/07 | C |
| FR-07 | Assistant guides users to comparison | UC16 UC25 | MOD06 | C |
| FR-08 | Comparison page shows two selected models side by side | UC25 | MOD03 | C |
| FR-09 | Agreed comparison attributes (camera, zoom, display, design, battery) | UC25 UC19 | MOD02/03 | C |
| FR-10 | Configured price/availability with clear freshness rules | UC05 | MOD03 | C |
| FR-11 | Proceed from comparison to purchase or store reservation, *subject to backend capability* | UC07 UC08 | MOD03 | C for reservation; **Cond** for online purchase |
| FR-12 | Add another model or request assistant recommendation | UC25 UC16 | MOD03/06 | C |
| FR-13 | Initiate repair flow by describing/selecting need | UC10 | MOD04 | C |
| FR-14 | Collect/confirm device model and repair type | UC10 UC27 | MOD04 | C |
| FR-15 | Communicate diagnosis, readiness, warranty *where confirmed by operations* | UC10 UC11 UC12 | MOD04 | C |
| FR-16 | Select available date and time slot | UC10 UC27 | MOD04 | C |
| FR-17 | Collect and validate customer contact details | UC10 UC02 | MOD04 | C |
| FR-18 | Confirm booking; SMS *if an SMS provider is integrated* | UC10 UC20 | MOD04 + notifications | C (SMS depends on BD-08) |
| FR-19 | Explain whether diagnosis/payment is required before final price | UC11 | MOD04 | C |
| FR-20 | Store location, hours, directions when configured | UC04 UC19 | MOD02 | C |
| FR-21 | Trade-in, installments, delivery, returns, warranty, pickup offers per approved terms | UC06 UC09 | MOD02/03 | Content C; transactional trade-in/financing **Cond** |
| FR-22 | Analytics events for key funnel steps, subject to privacy/consent | UC24 | MOD09 | C |
| FR-23 | Authorized staff maintain products, prices, availability, repair types, slots, stores, promotions | UC19 UC27 | MOD08 (+ owning modules) | C |
| FR-24 | Homepage Business Solutions section linking to Corporate page | UC04 UC13 | MOD05 (content API) | C |
| FR-25 | Corporate Solutions page explains offering and categories | UC13 | MOD05 | C |
| FR-26 | Request-a-Quote journey: company/contact, products, quantities, supply requirements | UC13 | MOD05 | C |
| FR-27 | Confirm quote-request submission and agreed follow-up expectation | UC13 UC20 | MOD05 | C |

Several FRs are presentation requirements of the public frontend: FR-01…FR-05, FR-08, FR-24 and FR-25. For those, this backend provides **data, configuration and APIs**, not the screens.

## 5. Non-functional requirements

### 5.1 Baseline (S1 §5)

| ID | Area | Requirement (abridged) |
|---|---|---|
| NFR-01 | Usability & accessibility | Responsive; contrast, keyboard access, focus, labels, accessible errors |
| NFR-02 | Performance | Targets to be agreed after hosting/catalog/integrations; optimize images, defer non-critical assets |
| NFR-03 | Availability & resilience | Availability, backup, recovery, monitoring, incident targets to be agreed |
| NFR-04 | Security | HTTPS, role-based admin access, secure authentication, input validation, rate limiting, secrets management, secure integration credentials |
| NFR-05 | Privacy | Data minimization, privacy notices, retention/deletion rules, legal obligations confirmed by GEN-I |
| NFR-06 | Data accuracy | Authoritative sources and refresh/confirmation rules for prices, stock, repair prices, hours, slots, claims |
| NFR-07 | Compatibility | Agreed browsers/OS/breakpoints |
| NFR-08 | Maintainability | Documented components, configuration-driven content, environment separation, deployment/rollback procedures |
| NFR-09 | Integration reliability | Timeouts, retries, error handling, reconciliation, alerting |
| NFR-10 | AI transparency | Identify AI output, no unsupported guarantees, disclose uncertainty, route price/stock confirmation to authorized systems/staff |
| NFR-11 | Localization | Languages, currency, number/date formats, RTL if Arabic in scope |
| NFR-12 | Analytics governance | Event names, consent, report access, data ownership before tracking |

### 5.2 Proposed measurable targets (S2 §3, N01–N12) — *proposals, not commitments*

- **N01:** zero successful cross-company/cross-role disclosures in the security suite.
- **N02:** no double-confirmed slots or duplicate orders under concurrency or replay.
- **N03:** material AI claims require evidence. An unknown audit verdict never counts as a pass.
- **N04:** offline AI mode has no egress.
- **N05:** API p95 ≤ 800 ms excluding external calls. Assistant first status ≤ 2 s; total ≤ 25 s.
- **N06:** 99.5 % monthly storefront availability.
- **N07:** RPO ≤ 15 min, RTO ≤ 4 h.
- **N08:** accessibility.
- **N09:** privacy and retention.
- **N10:** observability.
- **N11:** versioned APIs, migrations and prompts.
- **N12:** English/Arabic, RTL, **EGP** currency, locale-aware dates and numbers.

S2 itself states these numeric values need GEN-I approval before they become commitments.

## 6. Extended design requirements (S2 §3)

| ID | Requirement | Scope (S2/S3) |
|---|---|---|
| EX01 | Identity, company membership, privacy choices, own-record tracking | C |
| EX02 | Cross-category exact-model comparison (TV, smart home, appliance attributes) | C |
| EX03 | Deterministic commercial engine, stock freshness, versioned policies | C |
| EX04 | Cart, checkout, payment reconciliation, store reservation, fulfilment *when enabled* | **Cond** (reservation part is C via MOD03) |
| EX05 | Repair capacity, parts, diagnosis consent, quality tests, collection, warranty history | C |
| EX06 | Corporate RFQ, versioned quotes, company approvals, optional credit purchasing | RFQ + quotes C; credit/purchasing **Cond** |
| EX07–EX10 | Agentic RAG: scoped retrieval, specialist tools, audits, fallback, human review, model administration | C (S3 MOD06/07) |
| EX11 | Knowledge scanning, classification, approval, indexing, withdrawal | C |
| EX12 | Source integrations, durable notifications, quarantine, retry | C |
| EX13 | KPI analytics, access audit, retention, backup/restore, deployment controls | C |

## 7. Business rules (S2 §2) — to be enforced in services, not UI

| ID | Rule |
|---|---|
| BR01 | Source authority: defined ownership of price/stock/service data; stale or conflicting evidence cannot support a guaranteed claim |
| BR02 | Exact product: one variant ID powers listing, details, comparison, price, stock and assistant evidence |
| BR03 | Deterministic money: prices, tax, discounts, totals, refunds, credit calculated in code with decimal arithmetic and versioned rules |
| BR04 | Resource exclusivity: repair slot or stock hold confirmed only inside a transaction that verifies current capacity |
| BR05 | Payment proof: only authenticated provider state proves payment (conditional module) |
| BR06 | Customer consent bound to exact versioned payloads; changed payload invalidates approval |
| BR07 | Company isolation: corporate prices, addresses, RFQs, quotes, credit, chat visible only to permitted members/staff |
| BR08 | Quote validity: only a current, unexpired, issued revision may be accepted; a quote is not a stock guarantee unless held |
| BR09 | Human authority: LLM audits are advisory; consent and approvals remain distinct |
| BR10 | Degraded service: unknown provider/model state is visible; fallback never manufactures a completed action |
| BR11 | Durable events: business change and outbox event written atomically; idempotent retries |
| BR12 | Policy change: knowledge, commercial rules, agent instructions, models and routing are approved and versioned |

## 8. Lifecycle state machines (S2 §2)

| Record | States | Scope |
|---|---|---|
| Repair | received → diagnosing → awaiting_consent → approved → repairing → testing → ready → collected. A failed test returns to repairing; a changed estimate returns to awaiting_consent | C |
| Quote | draft → approved → issued → accepted / declined / expired / superseded | C |
| Knowledge | draft → review → approved → indexed → withdrawn | C |
| AI run | planning → retrieving → drafting → auditing → released. Any stage → fallback / failed / awaiting_human | C |
| Order | pending_payment → confirmed → allocated → dispatched → completed | **Cond** |

Every transition records:
- the actor,
- the previous and next state,
- the policy version,
- the reason,
- the correlation ID.

## 9. Scope classes (S2 §2, S3 §4)

**Committed release baseline (S3 MOD01–MOD11):**
- secure foundation (identity, roles, organization/branch/company scope, audit, outbox);
- catalog for four categories plus stores and offers;
- comparison, commercial facts and staff-assisted/store reservation (no payment gateway assumed);
- full repair journey;
- corporate RFQ and versioned quotation;
- governed Agentic RAG assistant;
- prompt/specialist/shortcut registry;
- administrative workspaces;
- analytics;
- CSV/Word exports;
- production continuity.

**Conditional, enabled only by a sized change request and management decision:**
- full online checkout and payment reconciliation;
- returns/refunds as transactional flows;
- advanced corporate ordering, credit and accounting;
- live financing, trade-in and carrier integrations;
- autonomous privileged infrastructure actions.

**Deferred:**
- native mobile apps;
- voice/social-channel agents;
- advanced personalization;
- external manufacturer compatibility lookup.

**Excluded authority (S2 §2).** GEN-I does not automatically replace an ERP, accounting, payment, carrier or financing system. The AI does not:
- conclusively diagnose hardware,
- approve credit,
- set tax,
- certify installation safety,
- alter payment records.

## 10. Operational data GEN-I must supply (S1 §6, S2 §15)

- Repair-type catalogue by device/model, durations, parts/tools, parts availability and substitution rules.
- Diagnosis fees, final-price approval, warranty terms, consent wording.
- Booking, cancellation, rescheduling, no-show and escalation policies.
- SMS provider, sender and templates, plus delivery-failure handling.
- Source of truth for catalog, specifications, prices, promotions and inventory.
- Store list, hours and directions.
- Approved commercial terms: trade-in, installments, delivery, returns, warranty, pickup.

## 11. Known content issues to correct (S5)

- Product recommendations conflict across screens.
- The repair page contains copied comparison content.
- The corporate page promises capabilities beyond the documented quote-request journey.

These affect the public frontend and approved content, not this backend's structure.
