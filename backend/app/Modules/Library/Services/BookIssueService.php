<?php

declare(strict_types=1);

namespace App\Modules\Library\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\ConfigurationService;
use App\Modules\Library\DTOs\BookIssueResponse;
use App\Modules\Library\DTOs\IssueBookRequest;
use App\Modules\Library\Entities\Book;
use App\Modules\Library\Entities\BookIssue;
use App\Modules\Library\Models\BookIssueModel;
use App\Modules\Library\Models\BookModel;
use CodeIgniter\I18n\Time;
use Config\Database;
use Config\Services as AppServices;

/**
 * docs/design/library/Phase-3-Service-Controller-Design.md
 * BR-LIB-002/003 fines/replacement charges are computed and stored here
 * but not posted to the Fees ledger (ADR-009 §3, §4 — no ad-hoc-charge
 * capability exists in Fees' current design). BR-LIB-006 reservation
 * priority (ADR-017) is enforced here too — issueBook() blocks a book
 * that's Notified to a different reservation holder.
 *
 * RBAC (ADR-024 §3, Phase 2): `library.manage` (Tier 1) gates every
 * write — a physical book issue/return/loss/fine-settlement is a
 * librarian-performed action at the circulation desk, not self-service,
 * unlike Reservation's create/cancel. `getBookIssue()`/`listByBorrower()`
 * allow Tier 2 — the borrower may read their own issue history, matching
 * `borrower_type`/`borrower_ref_id` directly on the entity (ADR-024 §1's
 * "simplest Tier 2 case" note).
 */
class BookIssueService
{
    public const PERMISSION_MANAGE = 'library.manage';

    public function __construct(
        private readonly BookIssueModel $bookIssueModel,
        private readonly BookModel $bookModel,
        private readonly AuditService $auditService,
        private readonly ConfigurationService $configurationService,
        private readonly ReservationService $reservationService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function issueBook(IssueBookRequest $request): BookIssueResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

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

        if ($this->bookIssueModel->countIssuedByBorrower($request->borrowerType, $request->borrowerRefId) >= $this->configurationService->getNumber('library.max_books_per_borrower')) {
            throw new BusinessRuleException(
                'MAX_BOOKS_LIMIT_REACHED',
                'This borrower is already at the configured maximum-books limit (BR-LIB-001).',
            );
        }

        if ($this->bookIssueModel->sumUnsettledFinesByBorrower($request->borrowerType, $request->borrowerRefId) > $this->configurationService->getNumber('library.outstanding_fine_threshold')) {
            throw new BusinessRuleException(
                'OUTSTANDING_FINE_BLOCKS_ISSUE',
                'This borrower has an outstanding library fine that must be settled first (BR-LIB-005).',
            );
        }

        // docs/ADR/ADR-017-library-reservation-queue.md §6 — a genuine
        // row lock, the same SELECT ... FOR UPDATE shape
        // SeatAllocationModel::incrementSeatsFilled/ApplicationModel::
        // lockForUpdate already established, guarding against this issue
        // and a concurrent ReservationService::processExpiredNotifications()
        // pass both acting on the same Notified reservation.
        $db = Database::connect();
        $db->transStart();

        $lockedReservation = $this->reservationService->lockNotifiedReservationForBook($request->bookId);

        if (
            $lockedReservation !== null
            && ($lockedReservation['borrower_type'] !== $request->borrowerType || (int) $lockedReservation['borrower_ref_id'] !== $request->borrowerRefId)
        ) {
            $db->transComplete();

            throw new BusinessRuleException(
                'BOOK_RESERVED_FOR_ANOTHER_BORROWER',
                'This book is reserved and notified to another borrower with priority to collect it (BR-LIB-006).',
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

        if ($lockedReservation !== null) {
            $this->reservationService->markFulfilled((int) $lockedReservation['reservation_id']);
        }

        $bookIssue = $this->bookIssueModel->find($id);

        $this->auditService->record('BookIssue', $id, AuditLog::ACTION_CREATE, null, $bookIssue->toRawArray());

        $db->transComplete();

        return new BookIssueResponse($bookIssue);
    }

    /**
     * BR-LIB-002: fine = max(0, days overdue) x decided per-day rate.
     */
    public function returnBook(int $id): BookIssueResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

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
        $fineAmount  = $daysOverdue > 0 ? round($daysOverdue * $this->configurationService->getNumber('library.fine_per_day_rate'), 2) : 0.0;

        $this->bookIssueModel->update($id, [
            'return_date' => $returnDate->toDateString(),
            'status'      => BookIssue::STATUS_RETURNED,
            'fine_amount' => $fineAmount,
        ]);

        $this->bookModel->update($before->book_id, ['is_available' => true]);

        $after = $this->bookIssueModel->find($id);

        $this->auditService->record('BookIssue', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        // docs/ADR/ADR-017-library-reservation-queue.md §5 — BR-LIB-006's
        // "returned" trigger: offer the book to the longest-waiting
        // reservation holder, if any, before it's generally available.
        $this->reservationService->notifyNextInQueue($before->book_id);

        return new BookIssueResponse($after);
    }

    /**
     * BR-LIB-003: decided flat replacement charge (ADR-009 §4).
     */
    public function reportLost(int $id): BookIssueResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireBookIssue($id);

        if ($before->status !== BookIssue::STATUS_ISSUED) {
            throw new BusinessRuleException(
                'BOOK_ISSUE_INVALID_STATUS_TRANSITION',
                "Cannot report lost a book issue in status {$before->status}.",
            );
        }

        $this->bookIssueModel->update($id, [
            'status'                    => BookIssue::STATUS_LOST,
            'replacement_charge_amount' => $this->configurationService->getNumber('library.replacement_charge'),
        ]);

        // The book stays unavailable — it's lost, not merely overdue.
        $after = $this->bookIssueModel->find($id);

        $this->auditService->record('BookIssue', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new BookIssueResponse($after);
    }

    public function settleFine(int $id): BookIssueResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireBookIssue($id);

        $this->bookIssueModel->update($id, ['fine_settled' => true]);
        $after = $this->bookIssueModel->find($id);

        $this->auditService->record('BookIssue', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new BookIssueResponse($after);
    }

    public function getBookIssue(int $id): BookIssueResponse
    {
        $bookIssue = $this->requireBookIssue($id);

        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, strtoupper($bookIssue->borrower_type), $bookIssue->borrower_ref_id);

        return new BookIssueResponse($bookIssue);
    }

    /**
     * @return list<BookIssueResponse>
     */
    public function listByBorrower(string $borrowerType, int $borrowerRefId): array
    {
        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, strtoupper($borrowerType), $borrowerRefId);

        return array_map(
            static fn (BookIssue $bookIssue): BookIssueResponse => new BookIssueResponse($bookIssue),
            $this->bookIssueModel->findByBorrower($borrowerType, $borrowerRefId),
        );
    }

    private function validateBorrower(string $borrowerType, int $borrowerRefId): void
    {
        if ($borrowerType === BookIssue::BORROWER_STUDENT) {
            AppServices::studentService()->assertStudentExists($borrowerRefId);

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
