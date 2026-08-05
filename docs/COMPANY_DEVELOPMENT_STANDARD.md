---
title: Company Development Standard
version: 1.0
status: Draft — pending review
applies_to: All company products (School ERP, College ERP, HRMS, CRM, Inventory, Hospital ERP, and any future product)
owner: Preetam Sinha
date: 2026-08-05
---

# Company Development Standard

## 0. Purpose & Scope

This document defines the **company-wide** engineering baseline that every product
is built on. It is technology-independent in intent but concrete in application: the
company's current technology baseline is PHP 8.3+ / CodeIgniter 4 / MySQL 8 on the
backend, Bootstrap 5 / jQuery / DataTables / SweetAlert2 / Select2 / Chart.js on the
web frontend, and Flutter / GetX / Dio on mobile, deployed to Hostinger shared
hosting, secured with JWT, and built REST-API-first.

**School ERP is the first product built under this standard, not the reason for it.**
Nothing in this document may reference School ERP business concepts (Admission,
Student, Fee, etc.). Product-specific documents (e.g. School ERP's MDB, BMDD, DDD,
ASD) sit *underneath* this document — they add product detail, they do not
contradict it. See [§15 Precedence & Governance](#15-precedence--governance).

This document does not repeat business requirements, ERD, or UI/UX decisions —
those live in each product's own documentation set.

---

## 1. Repository & Folder Structure

Each product is one repository with this top-level shape:

```
backend/     CodeIgniter 4 application (REST API)
database/    Schema reference, seed/reference data, DB operational scripts
mobile/      Flutter application
docs/        Product documentation (RGD, ADR, MDB, BMDD, DDD, ASD, Appendices, UIUX)
```

### 1.1 `backend/` — Core vs. Product modules

> **Decision.** Business logic is split into two namespaces: `App\Core\*` (shared,
> product-agnostic) and `App\Modules\*` (product-specific business modules). Both
> live inside the same repository for now — there is no separate shared package yet.
> **Why:** the company currently has one product; a versioned shared Composer
> package is real infrastructure (a registry, a release process, a consumer to keep
> in sync) that has no second consumer to justify it yet. Building it now would be
> speculative. **Revisit when:** the second product starts. At that point, extract
> `App\Core\*` into a private Composer package (path or VCS repository is enough on
> Hostinger — no need for a package registry). Because the boundary is already
> clean (Core never depends on a Modules namespace), that extraction is mechanical,
> not a redesign. Record the extraction itself as an ADR when it happens.

```
backend/
  app/
    Core/                     # shared, product-agnostic — every future product reuses this as-is
      Auth/                   # JWT issuance/validation, refresh-token store
      RBAC/                   # role/authority checks, usable from Filters and Services
      Audit/                  # AuditLog writer, common audit columns trait
      Response/               # standard envelope builder (see §7)
      Exceptions/             # the fixed exception categories + handlers (see §12)
      Logging/                # structured logger, request-id middleware
      Notification/           # email/SMS/push dispatch abstraction
      Document/               # file upload/storage/retrieval abstraction
      Config/                 # runtime configuration/feature-flag service
      BaseModel.php            # audit columns + soft-delete global scope
      BaseEntity.php
      BaseController.php
    Modules/
      <ModuleName>/            # one per business domain, e.g. a future product's modules
        Controllers/
        Services/
        Models/                # Model = repository by default (see §3.3)
        Entities/
        Filters/               # module-scoped route filters, if any
        Config/                # module-scoped routes/config
        Language/
    Database/
      Migrations/              # authoritative, executable schema history (CI4-native location)
      Seeds/
  public/
  writable/
  tests/
```

Never let a Module reach into `App\Core` internals beyond its published classes, and
never let one Module reach into another Module's `Models`/`Entities` — see the
cross-module rule in §3.4.

### 1.2 `database/`

This folder is **not** a duplicate of `backend/app/Database/Migrations` — migrations
stay CI4-native so the framework's migration runner works unmodified. `database/`
holds things a migration shouldn't:

- ERD export / schema reference document (kept in sync with, not instead of, the
  product's own ERD documentation)
- Reference/lookup seed data as flat files (CSV/SQL) that Seeders load
- Backup/restore and other DB operational scripts for the hosting environment

### 1.3 `mobile/`

Flutter app root, organized by GetX convention, one folder per feature:

```
mobile/lib/
  app/
    modules/<feature>/
      bindings/
      controllers/
      views/
    core/                      # shared: Dio client, interceptors, base controller, theming
    data/                      # models, repositories (Dio-backed)
    routes/
```

---

## 2. Naming Conventions

| Element | Convention | Example |
|---|---|---|
| PHP class | PascalCase, one class per file, filename = class name | `FeeStructureService.php` |
| PHP method / variable | camelCase | `calculateLateFee()` |
| PHP constant | UPPER_SNAKE_CASE | `MAX_LOGIN_ATTEMPTS` |
| Namespace | `App\Core\*` or `App\Modules\<Name>\*` | `App\Modules\Billing\Services` |
| Route path | kebab-case, plural, resource-oriented, versioned | `/api/v1/fee-structures` |
| DB table | snake_case, plural, matches entity | `fee_structures` |
| DB column | snake_case, descriptive | `admission_number` |
| PK column | `<table_singular>_id` | `fee_structure_id` |
| FK column | `<referenced_table_singular>_id`; `_ref_id` if polymorphic | `student_id`, `owner_ref_id` |
| PK constraint | `pk_<table>` | `pk_fee_structures` |
| FK constraint | `fk_<table>_<referenced_table>` | `fk_invoices_fee_structures` |
| Unique constraint | `uq_<table>_<columns>` | `uq_students_admission_number` |
| Check constraint | `ck_<table>_<rule>` | `ck_invoices_amount_positive` |
| Index | `idx_<table>_<columns>` | `idx_invoices_student_id` |
| Git branch | `<type>/<ticket-id>-<short-desc>` | `feature/FR-12-fee-structure-crud` |
| Dart file | snake_case | `fee_structure_controller.dart` |
| Dart class | PascalCase, role suffix | `FeeStructureController`, `FeeStructureBinding` |

---

## 3. Coding Standards

1. **PSR-12** code style; `declare(strict_types=1)` in every PHP file; type-hint all
   parameters and return types.
2. **Clean MVC, strictly layered:**
   - **Controller** — request parsing, calling one Service method, building the
     response envelope. No business logic, no direct Model/DB access.
   - **Service** — all business logic and orchestration. Owns the transaction
     boundary (never the Controller, never the Model). One business rule is
     enforced in exactly one method — do not duplicate a rule's logic across
     Services.
   - **Model / Entity** — data access and basic invariants only (CI4 Model IS the
     repository, see §3.3).
3. **Repository Pattern — used where it earns its keep, not everywhere.** A CI4
   Model is the repository for straightforward CRUD. Add a dedicated Repository
   class only when data-access logic genuinely diverges from CRUD (complex
   reporting queries, multi-table read models) — not as a default extra layer for
   every entity.
4. No dead code, no commented-out blocks. Public Service/Repository methods carry a
   one-line doc comment naming the requirement/rule they implement, using the
   product's own requirement-ID scheme (e.g. `FR-12`, `BR-SIS-004`).
5. Static analysis (PHPStan or Psalm) and PHP_CodeSniffer (PSR-12 ruleset) run as a
   hard CI gate — no merge without both passing, alongside the automated test suite.
6. Dependencies managed via Composer, versions pinned, upgrades reviewed
   deliberately (not auto-updated in CI).
7. All configuration flows through CI4's `.env` + `Config` classes. Nothing
   environment-specific is hardcoded in application code.

---

## 4. Database Standards

1. **One schema per environment**, module-grouped tables — no schema-per-module.
   MySQL 8, primary + read-replica topology once read load justifies it.
2. **3NF by default.** JSON columns only for genuinely variable-structure data, and
   only when the alternative (a full junction/attribute table) is documented as
   worse for the specific case.
3. **Every table** gets a single-column surrogate primary key: `BIGINT UNSIGNED`,
   named `<table_singular>_id`. Natural/business keys are `UNIQUE` constraints,
   never the primary key.
4. **Common columns — every table, no exceptions:**
   `created_by`, `created_at`, `updated_by`, `updated_at`, `is_deleted`,
   `deleted_by`, `deleted_at`. These are populated exclusively by
   `App\Core\BaseModel` / `BaseEntity` — never set manually by calling code. Add a
   `version` column (optimistic locking) on tables with real write contention.
5. **No hard deletes.** Soft-delete is universal, enforced by a shared global scope
   in `BaseModel` (default query excludes `is_deleted = 1`), not by per-query
   discipline. Soft-deleted rows remain subject to FK constraints — deleting a row
   never relaxes referential integrity. Restoring a soft-deleted row is its own
   audited action type, not a generic update.
6. **Foreign keys are `RESTRICT`-only** on update and delete — never `CASCADE
   DELETE`. Polymorphic associations (`owner_type` / `owner_ref_id`) have no
   DB-level FK; integrity is checked in the application layer, and this is a
   documented exception to the FK rule, not a precedent.
7. **Indexing:** every FK column is explicitly indexed (MySQL does not auto-index
   FKs); composite/search indexes only where a real, cited query pattern justifies
   them; indexes are created in the migration that creates the table, not added
   later as an afterthought.
8. **Constraint vs. business rule (enforcement-layer rule):**

   | Rule type | Enforced at |
   |---|---|
   | Primary key / Foreign key / Unique | Database |
   | Single-row check (range, format, simple cross-field) | Database `CHECK` constraint |
   | Cross-table or aggregate rule | Application Service layer only — never a DB trigger |

   A rule that cannot be cleanly placed in one of these rows is **incompletely
   specified** and must be clarified before it is implemented anywhere.
9. **Migrations are the only way schema changes happen.** Every change is a
   forward-only, reviewed CI4 migration with a working `down()`; no ad hoc schema
   edits against a running database, in any environment.
10. **Audit log** is one centralized, write-once table for the whole schema (no
    UPDATE/DELETE code path against it, enforced at the DB-privilege level where
    possible), partitioned by time once volume warrants it. This is distinct from
    the per-table `created_by`/`updated_by` columns — the audit log answers "who
    changed what and when," the columns answer "who currently owns this row."
11. **Pagination is enforced at the Model layer** for any list query — never left to
    controller-level discipline.

---

## 5. API Standards

1. **REST, resource-oriented.** Plural nouns, no verbs in the path. Versioned via a
   leading path segment: `/api/v1/...`. Additive changes do not bump the version;
   breaking changes do, and old + new versions run concurrently during a
   deprecation window (never a zero-length one).
2. **JSON only**, except `multipart/form-data` for file upload. One response shape
   per endpoint — no content negotiation.
3. **Standard response envelope**, identical shape on every endpoint:

   ```json
   {
     "success": true,
     "data": { },
     "error": null,
     "meta": {
       "request_id": "uuid",
       "pagination": { "page": 1, "per_page": 20, "total": 134 }
     }
   }
   ```

   On failure, `data` is `null` and `error` is populated:

   ```json
   {
     "success": false,
     "data": null,
     "error": { "category": "validation", "code": "FEE_STRUCTURE_INVALID_AMOUNT", "message": "...", "fields": { "amount": "must be positive" } },
     "meta": { "request_id": "uuid" }
   }
   ```

   Responses are **DTOs only** — never a raw Model/Entity. Provide role-scoped DTO
   variants where the same resource carries different PII visibility by caller
   role.
4. **HTTP method mapping:**

   | Method | Semantics |
   |---|---|
   | GET | Safe, idempotent read |
   | POST | Create / non-idempotent action (use an `Idempotency-Key` header for payment-like or confirm-style operations) |
   | PUT | Full replace, idempotent |
   | PATCH | Partial update |
   | DELETE | Soft-delete, idempotent |

5. **Pagination, filtering, sorting:** consistent `page` / `per_page` params on
   every list endpoint, with a default and a hard max page size (cap excess, don't
   reject it); response always carries page/total/page-size in `meta.pagination`.
   Filters match by exact field name against a documented attribute catalogue
   (indexed fields only), AND semantics by default. Every list endpoint defines a
   default sort; only indexed columns are sortable. Free-text search is distinct
   from filtering and still authorization-checks each result individually.
6. Every request carries a `request_id` (client-supplied or server-generated),
   echoed in `meta.request_id` and propagated into every log line for correlation.
7. API documentation (OpenAPI) is **generated from code** as a build artifact, never
   hand-maintained separately — it cannot drift if it's regenerated every build.

---

## 6. Git Workflow

1. `main` is always deployable. No direct commits to `main`.
2. Short-lived branches: `feature/<ticket-id>-<short-desc>`, `fix/<ticket-id>-<short-desc>`,
   `hotfix/<ticket-id>-<short-desc>`.
3. All changes land via pull request; **at least one independent review required**
   before merge.
4. Merge is blocked unless CI is green: lint + static analysis + automated tests.
5. Prefer squash-merge for feature branches to keep `main` history linear and
   readable.

---

## 7. Commit Convention

Conventional Commits: `<type>(<scope>): <short summary>`

- **Types:** `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf`, `security`
- **Scope:** the module name, e.g. `feat(billing): add fee structure endpoint`
- Reference the requirement/ticket ID in the body or a `Refs:` footer line, not just
  the subject.
- Never commit secrets or credentials; `.env` and credential files are always
  gitignored.

---

## 8. Logging Standards

1. Structured (JSON) logs at every layer. Every log line carries `request_id`,
   actor/user id, module, and outcome.
2. **Fixed log-level scheme:**

   | Level | Meaning |
   |---|---|
   | ERROR | Unhandled / system exception |
   | WARN | Business-rule violation or authorization failure |
   | INFO | Successful state-changing operation |
   | DEBUG | Diagnostic detail — dev/staging only, never production default |

3. **PII is never logged**, at any level, with no per-module exception.
4. Logs are centrally aggregated even on shared hosting (shipped to an external log
   service or exported on a schedule) — a local-only log file is never the sole
   copy in production.
5. The audit log (§4.10) is a separate concern from operational logging: audit rows
   are written transactionally with the operation they describe (a failed audit
   write fails the operation); logs are diagnostic and best-effort.

---

## 9. Security Standards

1. **Passwords:** bcrypt or Argon2 hashing only, never reversible encryption.
   Account lockout after 5 consecutive failed attempts.
2. **JWT:** short-lived access token (default 15 min) + longer-lived refresh token
   (default 7 days), refresh state tracked server-side so it can be explicitly
   revoked. Revocation triggers: logout, password change, access revocation by an
   admin. Password change revokes **all** active sessions for that account. JWT
   payload carries identity + role/authority claims only — never PII.
3. **RBAC enforced at the Service layer**, not only at the route/filter layer.
   Record-level scoping (e.g., a user seeing only records they own) is enforced in
   query logic, not bolted on after the fact.
4. **Transport:** TLS everywhere, in every environment including staging — no
   plaintext HTTP.
5. **Input handling:** parameterized queries only via CI4's Query Builder — never
   raw string concatenation into SQL. Output-encode everything rendered back to a
   browser context. CSRF tokens on any cookie-authenticated state-changing request.
6. **File uploads:** type/size allow-list, malware-scanned before acceptance, stored
   outside the public web-root, retrieved only through an authorized service call —
   never a directly guessable public path.
7. **Rate limiting**, tracked separately for interactive (per-identity) and
   machine/integration (per-source) traffic.
8. **CORS** restricted to an explicit known-origin allow-list — never a wildcard,
   in any environment.
9. **Secrets** are never committed to git or logs; injected via environment config
   at runtime; rotated on a schedule.
10. Dependency versions pinned; vulnerability scanning (`composer audit` or
    equivalent) run regularly, not just at initial setup.

---

## 10. Error Handling Standards

1. **Fixed exception categories**, each with exactly one centralized handler and one
   caller-visible contract: **Validation**, **Business Rule**, **Authorization**,
   **Concurrency**, **Rate Limit**, **System/Unhandled**.
2. Validate all fields before persisting anything; return **all** failing fields
   together — never fail-fast on the first one found.
3. Field/format validation always runs before business-rule validation. Business
   rules are never evaluated against structurally invalid input.
4. Business-rule violations return a stable rule code/reference in the error
   envelope — never internal detail (no stack traces, SQL text, internal IDs),
   regardless of the caller's role.
5. System/unhandled exceptions are logged and reported to monitoring; the caller
   receives a generic system-error response, nothing more specific.
6. No ad hoc `try/catch`-and-echo scattered through controllers — every category
   routes through its one centralized handler (CI4 `Exceptions` config +
   `App\Core\Exceptions` handler classes).

---

## 11. Code Review Checklist

- [ ] Layer boundaries respected — Controller thin, Service holds logic, Model has
      no business logic
- [ ] Cross-module calls go through the other module's Service only, never its
      Model/Entity
- [ ] Audit columns populated and audit-log write present for state-changing
      operations
- [ ] Validation order correct: field/format before business-rule
- [ ] Transaction boundary is the Service method, not the Controller or Model
- [ ] New list endpoints paginate; new indexes are justified by an actual query
      pattern
- [ ] No raw SQL string concatenation — parameterized queries only
- [ ] No PII in logs; correct log level used
- [ ] Security checks present: auth, RBAC, record-level scoping/ownership
- [ ] Naming conventions followed (files, classes, routes, DB objects — §2)
- [ ] No dead code or commented-out blocks
- [ ] Automated tests cover the new business rule and its edge cases; lint +
      static analysis + tests all green

---

## 12. Release Checklist

- [ ] All target commits merged to `main` via reviewed PRs
- [ ] Migrations reviewed, `down()` implemented, tested in staging against
      production-representative data volume
- [ ] Configuration fully externalized — no environment-specific values in code
- [ ] Secrets verified/rotated as needed; none committed
- [ ] Automated test suite + static analysis green
- [ ] Rollback plan documented and validated before promoting
- [ ] Health-check endpoint verified against all live dependencies
- [ ] Release tagged with a semantic version; changelog updated
- [ ] Post-release monitoring window defined (error rate / latency watch)
- [ ] Database backup verified immediately before a schema-changing release

---

## 13. Precedence & Governance

1. This document is the company-wide baseline for every product. A product's own
   documents (MDB, BMDD, DDD, ASD, etc.) may add product-specific detail but may
   not contradict it.
2. If a product document and this document conflict, **this document wins** unless
   an ADR in that product's `docs/ADR/` explicitly and narrowly supersedes a named
   section here — the ADR must say which section and why.
3. Changes to this document require an ADR-style record (what changed, why) because
   it affects every current and future product, not just the one being worked on
   at the time.
4. This is a versioned document. Any normative change bumps the version in the
   front matter.

---

## 14. Open Decisions Deferred, Not Forgotten

Recorded here so they aren't silently re-litigated per-product later:

- **Shared Composer package extraction** — deferred until product #2 exists (see
  §1.1). Revisit as an ADR at that point, not before.
- **Git branching for parallel release lines** (if a product ever needs to support
  two live versions at once) — not addressed here; the current model assumes
  `main` is always the one deployable line.
- **Central log aggregation vendor/mechanism on Hostinger shared hosting** — the
  *requirement* (§8.4) is fixed; the specific mechanism is a per-product
  infrastructure decision, to be recorded as an ADR when first implemented.
