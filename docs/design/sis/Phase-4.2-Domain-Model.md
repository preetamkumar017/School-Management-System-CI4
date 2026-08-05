---
status: Approved (Revision 2)
last-updated: 2026-08-06
supersedes-in-spirit: Appendix-G Guardian entity card (Module: SYS placement), per ADR-002
references: ADR-002, ADR-003, ADR-004, DG-SIS-001 (Resolved)
---

# Phase 4.2 — SIS Domain Model (Revision 2)

## Scope

Per ADR-002: SIS owns `Student`, `Guardian`, and the `StudentGuardianLink` junction — all inside the `App\Modules\Sis` module, same internal layering as `Admission`/`Academic`. Four Business Rules enforced directly: BR-SIS-002, BR-SIS-003, BR-SIS-005, BR-SIS-006. BR-SIS-004 is explicitly out of scope (deferred to Examination, ADR-002).

**Revision 2 changes from Revision 1:** `section_id`'s creation-time status resolved (ADR-004, closing DG-SIS-001); `full_name`/`dob`'s source at stub-creation time stated (ADR-004 §2, closing the previously-unaddressed nullability tension).

---

## Entity: `Student` (ENT-SIS-001, table `students`)

Extends `App\Core\BaseEntity` (surrogate `student_id`, `version`, common audit columns — standard baseline, Company Development Standard §4.4).

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| admission_number | VARCHAR(20) | N | – | Unique (BR-SIS-002) |
| full_name | VARCHAR(100) | N | – | Non-empty |
| dob | DATE | N | – | Past date |
| aadhaar_number | VARCHAR(12) | Y | NULL | Unique where present (BR-SIS-002) |
| section_id | BIGINT UNSIGNED (plain FK) | Y | NULL | References Academic module's Section — cross-module, plain FK only (no DB-level FK to another module's table, per the cross-module rule). Nullable at creation; populated by Admin Staff during FR-06 profile completion; required (Service-layer check, not a DB constraint) before the `DRAFT → ACTIVE` transition (BR-SIS-003). Resolved by ADR-004 (DG-SIS-001). |
| application_id | BIGINT UNSIGNED (plain FK) | N | – | References `admission.applications`, One-to-One. Cross-module, plain FK, no DB-level FK across module boundaries. Per ADR-003, the validating call originates from **Admission's** Service layer, which passes `application_id` into a **SIS** Service method at stub-creation time — SIS does not call outward to Admission's `ApplicationService`. |
| category | enum (`GENERAL`, `RTE`) | N | GENERAL | SIS's own `StudentCategory` value set, not shared with Admission's `ApplicationCategory` |
| medical_info | TEXT | Y | NULL | Sensitive PII |
| status | enum (`DRAFT`, `ACTIVE`, `PROMOTED`, `EXITED`, `ARCHIVED`) | N | DRAFT | Forward-only (BR-SIS-003 gates DRAFT→ACTIVE; BR-SIS-006 also gates DRAFT→ACTIVE) |

`full_name` and `dob` retain their original definition (`NOT NULL`, no default) unchanged — and per ADR-004 §2, their source at stub-creation time is now stated: both are copied directly from the `Application` being confirmed (`Application.applicant_name` → `Student.full_name`, `Application.dob` → `Student.dob`), which already carries both as mandatory fields (Appendix-G). The nullability tension an earlier draft raised for these two fields is closed — it was never a schema gap, only a missing statement of where the values come from.

Relationships: plain-FK to Section and Application (cross-module, resolved via an explicit Service call, never a database-level foreign key across module boundaries); real many-to-many to `Guardian` via `StudentGuardianLink` (intra-module — both entities live in `Sis`, so this is not subject to the cross-module Service-only rule, and is joined explicitly in the Model layer — CI4 has no implicit ORM relationship graph to declare).

### `Student` Lifecycle

Created as a Student stub during Admission's Confirm Enrollment process (FR-02, ADR-003), with `full_name`/`dob`/`category`/`admission_number`/`application_id` populated at creation (from the confirmed `Application`, per ADR-004) and `section_id`/`medical_info` left `NULL` → completed by Admin Staff via SIS (FR-06: guardian linkage, `section_id` assignment) → Active (BR-SIS-003 mandatory-field check, now including `section_id`, + BR-SIS-006 guardian check) → Promoted (year-end) → Alumni/Exited → Archived.

---

## Entity: `Guardian` (ENT-SYS-003, table `guardians`) — unaffected by ADR-003/DG-SIS-001

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint |
|---|---|---|---|---|
| full_name | VARCHAR(100) | N | – | Non-empty |
| relationship | enum (`FATHER`, `MOTHER`, `GUARDIAN`, `OTHER`) | N | – | |
| mobile_number | VARCHAR(10) | N | – | 10-digit numeric |
| email | VARCHAR(100) | Y | NULL | Valid email format |

`is_primary_contact` is **not** a field here — see Design Note 1.

Relationships: many-to-many with `Student` via `StudentGuardianLink`.

## Entity: `StudentGuardianLink` (junction, table `student_guardian_link`) — unaffected by ADR-003/DG-SIS-001

Does **not** extend `BaseEntity`/audit-column baseline — the Company Development Standard's common-columns rule (§4.4) carves junction tables out of the surrogate-ID/audit-column baseline (formerly DDD Part-1 §9, now archived), using a composite key instead (Appendix-H §6).

| Field | Type | Null | Default | Constraint |
|---|---|---|---|---|
| student_id | BIGINT UNSIGNED | N | – | Composite PK part 1, FK → `students` |
| guardian_id | BIGINT UNSIGNED | N | – | Composite PK part 2, FK → `guardians` |
| is_primary_contact | BOOLEAN | N | FALSE | Stored, but its "exactly one true per student" invariant is **not enforced** this phase — see Design Note 4 |

Composite primary key `(student_id, guardian_id)`, declared directly in the migration and queried through explicit Model methods — there is no ORM object-relational cascade to replicate; both foreign keys are `RESTRICT`-only per the Company Development Standard.

---

## Design Notes (original, unaffected by ADR-003/DG-SIS-001)

1. **`is_primary_contact` placement**: Appendix-G's Guardian Attribute Catalogue lists it as a `guardians` column; treated as a documentation inconsistency and modeled on `StudentGuardianLink` instead (per-link semantics, matching Appendix-H's junction-table description).
2. **Scope of the primary-contact invariant**: Appendix-G's attribute-level note ("exactly one primary per student") is *not* elevated to enforced business logic. Only BR-SIS-006's literal text — "a student must have at least one linked guardian contact before becoming ACTIVE" — is in scope.
3. **`Student.application_id`**: present in Appendix-G's Foreign-Keys line and Relationships line, but missing as a row in Appendix-G's own Attribute Catalogue table for Student. Treated as a completeness gap in that one table, not a genuine conflict — field is included.
4. **BR-SIS-004** carried as explicitly out of scope, per ADR-002 — no placeholder field or enforcement added to `Student`.

## Out of scope

- Section/Class entity itself (Academic module, not yet built).
- Any change to Admission's `Application` entity.
- Communication/Portal consumption of Guardian data (future, via a direct call to `Sis`'s own Service class per ADR-002).
- Enforcing "exactly one primary contact per student" (see Note 2).
