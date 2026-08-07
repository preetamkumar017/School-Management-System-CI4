---
status: Approved (Original)
last-updated: 2026-08-07
references: Phase 1, Phase 2, ADR-009
---

# Phase 3 — Library Service and Controller Design

## `BookService`

Plain CRUD (create/update/get/list), `barcode` uniqueness. No delete —
Master data, same reasoning as every prior Master entity.

## `BookIssueService`

| Operation | Reason |
|---|---|
| `issueBook(IssueBookRequest): BookIssueResponse` | Validates `book_id`; validates `borrower_ref_id` via SIS `StudentService`/HR & Payroll `EmployeeService` per `borrower_type`; rejects `BOOK_NOT_CIRCULATING` if `Book.classification = Reference` (BR-LIB-004); rejects `BOOK_NOT_AVAILABLE` if an `Issued` row already exists for the book; rejects `MAX_BOOKS_LIMIT_REACHED` if `countIssuedByBorrower() >= 3` (BR-LIB-001, ADR-009 §2); rejects `OUTSTANDING_FINE_BLOCKS_ISSUE` if `sumUnsettledFinesByBorrower() > 0` (BR-LIB-005, ADR-009 §6); `status = Issued`. |
| `returnBook(int $id): BookIssueResponse` | Rejects if `status != Issued`; sets `return_date = today`, `status = Returned`; computes `fine_amount = max(0, days_overdue) × 2.00` (BR-LIB-002, ADR-009 §3). |
| `reportLost(int $id): BookIssueResponse` | Rejects if `status != Issued`; sets `status = Lost`, `replacement_charge_amount = 500.00` (BR-LIB-003, ADR-009 §4). |
| `settleFine(int $id): BookIssueResponse` | Sets `fine_settled = true` — a Librarian action, no payment-gateway integration at this layer (ADR-009 §3/§4). |
| `getBookIssue(int $id): BookIssueResponse` | Plain read. |
| `listByBorrower(string $borrowerType, int $borrowerRefId): array` | Borrower's issue history. |

## Controllers — base path `/api/v1/library/...`

| Controller | Base path | Endpoints |
|---|---|---|
| `BookController` | `/books` | `POST /`, `PATCH /{id}`, `GET /{id}`, `GET /` |
| `BookIssueController` | `/book-issues` | `POST /` (issue), `POST /{id}/return`, `POST /{id}/report-lost`, `POST /{id}/settle-fine`, `GET /{id}`, `GET /?borrower_type&borrower_ref_id` |

## Conclusion

Every endpoint is ready for implementation on the basis of ADR-009's
resolutions. BR-LIB-006 (Reservation) and fee-ledger posting are
explicitly out of scope, not silently missing.
