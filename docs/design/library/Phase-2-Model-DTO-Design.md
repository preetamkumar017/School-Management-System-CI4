---
status: Approved (Original)
last-updated: 2026-08-07
references: Phase 1, ADR-009
---

# Phase 2 — Library Model and DTO Design

Convention: `Book` is Master data (no pagination); `BookIssue` is
Transaction data, exposed via scoped listings (by borrower), matching
Fees'/HR & Payroll's precedent.

## `BookModel`

| Method | Purpose |
|---|---|
| `findByBarcode(string): ?Book` / `existsByBarcode(string): bool` / `...ExceptId(...)` | Business-key uniqueness |

## `BookIssueModel`

| Method | Purpose |
|---|---|
| `countIssuedByBorrower(string $borrowerType, int $borrowerRefId): int` | BR-LIB-001 |
| `sumUnsettledFinesByBorrower(string $borrowerType, int $borrowerRefId): float` | BR-LIB-005 — sum of `fine_amount` + `replacement_charge_amount` across rows where `fine_settled = false` |
| `findByBorrower(string $borrowerType, int $borrowerRefId): array` | Borrower's issue history |
| `findActiveByBookId(int $bookId): ?BookIssue` | `Issued` row for a book, if any — drives `Book.is_available` |

## DTOs

`CreateBookRequest`/`UpdateBookRequest`: `barcode` (create-only, immutable
on update), `title`, `author`, `classification`. `BookResponse`:
`book_id`, `barcode`, `title`, `author`, `classification`,
`is_available`.

`IssueBookRequest`: `book_id`, `borrower_type`, `borrower_ref_id`,
`due_date`. `ReturnBookRequest`: none beyond the path `id` — return date
is server-set (`Time::now()`). `ReportLostRequest`: none beyond the path
`id`. `SettleFineRequest`: none beyond the path `id`.
`BookIssueResponse`: `book_issue_id`, `book_id`, `borrower_type`,
`borrower_ref_id`, `issue_date`, `due_date`, `return_date`,
`fine_amount`, `status`, `replacement_charge_amount`, `fine_settled`.
