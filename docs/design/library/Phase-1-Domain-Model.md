---
status: Approved (Original)
last-updated: 2026-08-07
references: Appendix-G Data Dictionary v1.0 (LIB module entities), Appendix-C v1.1 (BR-LIB-001–006), ADR-009
---

# Phase 1 — Library Domain Model

## Scope

Per ADR-009: `Library` (`App\Modules\Library`) owns two entities: `Book`
(Master), `BookIssue` (Transaction). `BookIssue.borrower_ref_id` is
polymorphic against SIS's `Student` or HR & Payroll's `Employee`,
dispatched on `borrower_type`. BR-LIB-006 (Reservation) is out of scope
(ADR-009 §7); fine/replacement-charge posting to Fees is out of scope
(ADR-009 §3, §4).

## Entity: `Book` (ENT-LIB-001, table `books`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| barcode | VARCHAR(30) | N | – | Unique |
| title | VARCHAR(200) | N | – | Non-empty |
| author | VARCHAR(150) | Y | NULL | |
| classification | enum (`Circulating`, `Reference`) | N | Circulating | BR-LIB-004 |
| is_available | BOOLEAN | N | TRUE | Derived from absence of an `Issued` `BookIssue` row, not caller-set on issue/return (still a real column per Appendix-G, kept in sync by the Service) |

Unique constraint: `barcode`.

### Lifecycle

Created (cataloged) → Active → Lost/Damaged (via a `BookIssue.status =
Lost` row, BR-LIB-003 — `Book` itself has no separate status column,
ADR-009 §1) → Withdrawn/Archived.

---

## Entity: `BookIssue` (ENT-LIB-002, table `book_issues`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| book_id | BIGINT UNSIGNED | N | – | FK → `books` (intra-module, real FK) |
| borrower_type | enum (`Student`, `Employee`) | N | – | |
| borrower_ref_id | BIGINT UNSIGNED | N | – | Polymorphic; validated via SIS `StudentService` or HR & Payroll `EmployeeService` per `borrower_type` (cross-module, plain field, no DB FK) |
| issue_date | DATE | N | CURRENT_DATE | |
| due_date | DATE | N | – | > `issue_date` |
| return_date | DATE | Y | NULL | |
| fine_amount | DECIMAL(8,2) | N | 0.00 | BR-LIB-002, decided ₹2/day (ADR-009 §3) |
| status | enum (`Issued`, `Returned`, `Lost`) | N | Issued | Decided additive column (ADR-009 §1) |
| replacement_charge_amount | DECIMAL(8,2) | N | 0.00 | Decided additive column, BR-LIB-003, decided ₹500 flat (ADR-009 §4) |
| fine_settled | BOOLEAN | N | FALSE | Decided additive column, BR-LIB-005 (ADR-009 §6) |

No unique constraint (per Appendix-G — a borrower can have multiple
issue records over time, and even concurrently for different books).

### Lifecycle

Created (Issued, BR-LIB-001/004/005 gate) → Returned (fine computed,
BR-LIB-002) or Lost (replacement charge computed, BR-LIB-003) →
Archived.

## Out of scope

- BR-LIB-006 Reservation queue (ADR-009 §7 — no `Reservation` entity).
- Fine/replacement-charge posting to the Fees ledger (ADR-009 §3, §4 —
  Fees has no ad-hoc-charge capability).
