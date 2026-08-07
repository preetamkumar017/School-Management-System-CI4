<?php

declare(strict_types=1);

namespace App\Modules\Library\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Library\DTOs\BookIssueResponse;
use App\Modules\Library\DTOs\IssueBookRequest;
use App\Modules\Library\Entities\Book;
use App\Modules\Library\Entities\BookIssue;
use App\Modules\Library\Models\BookIssueModel;
use App\Modules\Library\Models\BookModel;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/library/Phase-3-Service-Controller-Design.md
 * BR-LIB-002/003 fines/replacement charges are computed and stored here
 * but not posted to the Fees ledger (ADR-009 §3, §4 — no ad-hoc-charge
 * capability exists in Fees' current design).
 */
class BookIssueService
{
    /** ADR-009 §2 — decided default, "Client/Product Decision Required" per Appendix-C. */
    private const MAX_BOOKS_PER_BORROWER = 3;

    /** ADR-009 §3 — decided default. */
    private const FINE_PER_DAY = 2.00;

    /** ADR-009 §4 — decided default; no `price` field exists on Book to compute a variable charge from. */
    private const REPLACEMENT_CHARGE = 500.00;

    /** ADR-009 §6 — decided default: any nonzero unpaid fine blocks a new issue. */
    private const OUTSTANDING_FINE_THRESHOLD = 0.0;

    public function __construct(
        private readonly BookIssueModel $bookIssueModel,
        private readonly BookModel $bookModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function issueBook(IssueBookRequest $request): BookIssueResponse
    {
        $book = $this->bookModel->find($request->bookId);

        if ($book === null) {
            throw new BusinessRuleException('BOOK_NOT_FOUND', 'Book not found.');
        }

        if ($book->classification === Book::CLASSIFICATION_REFERENCE) {
            throw new BusinessRuleException(
                'BOOK_NOT_CIRCULATING',
                'This title is classified Reference and is in-library-use only (BR-LIB-004).',
            );
        }

        if ($this->bookIssueModel->findActiveByBookId($request->bookId) !== null) {
            throw new BusinessRuleException('BOOK_NOT_AVAILABLE', 'This book is already issued to another borrower.');
        }

        $this->validateBorrower($request->borrowerType, $request->borrowerRefId);

        if ($this->bookIssueModel->countIssuedByBorrower($request->borrowerType, $request->borrowerRefId) >= self::MAX_BOOKS_PER_BORROWER) {
            throw new BusinessRuleException(
                'MAX_BOOKS_LIMIT_REACHED',
                'This borrower is already at the configured maximum-books limit (BR-LIB-001).',
            );
        }

        if ($this->bookIssueModel->sumUnsettledFinesByBorrower($request->borrowerType, $request->borrowerRefId) > self::OUTSTANDING_FINE_THRESHOLD) {
            throw new BusinessRuleException(
                'OUTSTANDING_FINE_BLOCKS_ISSUE',
                'This borrower has an outstanding library fine that must be settled first (BR-LIB-005).',
            );
        }

        $id = $this->bookIssueModel->insert([
            'book_id'         => $request->bookId,
            'borrower_type'   => $request->borrowerType,
            'borrower_ref_id' => $request->borrowerRefId,
            'issue_date'      => Time::now()->toDateString(),
            'due_date'        => $request->dueDate,
            'status'          => BookIssue::STATUS_ISSUED,
        ], true);

        $this->bookModel->update($request->bookId, ['is_available' => false]);

        $bookIssue = $this->bookIssueModel->find($id);

        $this->auditService->record('BookIssue', $id, AuditLog::ACTION_CREATE, null, $bookIssue->toRawArray());

        return new BookIssueResponse($bookIssue);
    }

    /**
     * BR-LIB-002: fine = max(0, days overdue) x decided per-day rate.
     */
    public function returnBook(int $id): BookIssueResponse
    {
        $before = $this->requireBookIssue($id);

        if ($before->status !== BookIssue::STATUS_ISSUED) {
            throw new BusinessRuleException(
                'BOOK_ISSUE_INVALID_STATUS_TRANSITION',
                "Cannot return a book issue in status {$before->status}.",
            );
        }

        $returnDate = Time::now();
        $dueDate    = new \DateTimeImmutable((string) $before->due_date);
        $today      = new \DateTimeImmutable($returnDate->toDateString());
        $daysOverdue = $today > $dueDate ? $today->diff($dueDate)->days : 0;
        $fineAmount  = $daysOverdue > 0 ? round($daysOverdue * self::FINE_PER_DAY, 2) : 0.0;

        $this->bookIssueModel->update($id, [
            'return_date' => $returnDate->toDateString(),
            'status'      => BookIssue::STATUS_RETURNED,
            'fine_amount' => $fineAmount,
        ]);

        $this->bookModel->update($before->book_id, ['is_available' => true]);

        $after = $this->bookIssueModel->find($id);

        $this->auditService->record('BookIssue', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new BookIssueResponse($after);
    }

    /**
     * BR-LIB-003: decided flat replacement charge (ADR-009 §4).
     */
    public function reportLost(int $id): BookIssueResponse
    {
        $before = $this->requireBookIssue($id);

        if ($before->status !== BookIssue::STATUS_ISSUED) {
            throw new BusinessRuleException(
                'BOOK_ISSUE_INVALID_STATUS_TRANSITION',
                "Cannot report lost a book issue in status {$before->status}.",
            );
        }

        $this->bookIssueModel->update($id, [
            'status'                    => BookIssue::STATUS_LOST,
            'replacement_charge_amount' => self::REPLACEMENT_CHARGE,
        ]);

        // The book stays unavailable — it's lost, not merely overdue.
        $after = $this->bookIssueModel->find($id);

        $this->auditService->record('BookIssue', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new BookIssueResponse($after);
    }

    public function settleFine(int $id): BookIssueResponse
    {
        $before = $this->requireBookIssue($id);

        $this->bookIssueModel->update($id, ['fine_settled' => true]);
        $after = $this->bookIssueModel->find($id);

        $this->auditService->record('BookIssue', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new BookIssueResponse($after);
    }

    public function getBookIssue(int $id): BookIssueResponse
    {
        return new BookIssueResponse($this->requireBookIssue($id));
    }

    /**
     * @return list<BookIssueResponse>
     */
    public function listByBorrower(string $borrowerType, int $borrowerRefId): array
    {
        return array_map(
            static fn (BookIssue $bookIssue): BookIssueResponse => new BookIssueResponse($bookIssue),
            $this->bookIssueModel->findByBorrower($borrowerType, $borrowerRefId),
        );
    }

    private function validateBorrower(string $borrowerType, int $borrowerRefId): void
    {
        if ($borrowerType === BookIssue::BORROWER_STUDENT) {
            AppServices::studentService()->getStudent($borrowerRefId);

            return;
        }

        AppServices::employeeService()->getEmployee($borrowerRefId);
    }

    private function requireBookIssue(int $id): BookIssue
    {
        $bookIssue = $this->bookIssueModel->find($id);

        if ($bookIssue === null) {
            throw new BusinessRuleException('BOOK_ISSUE_NOT_FOUND', 'Book issue not found.');
        }

        return $bookIssue;
    }
}
