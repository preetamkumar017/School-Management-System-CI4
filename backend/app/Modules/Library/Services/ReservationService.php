<?php

declare(strict_types=1);

namespace App\Modules\Library\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\ConfigurationService;
use App\Modules\Communication\DTOs\CreateNotificationLogRequest;
use App\Modules\Communication\Entities\NotificationLog;
use App\Modules\Communication\Services\NotificationLogService;
use App\Modules\Library\DTOs\CreateReservationRequest;
use App\Modules\Library\DTOs\ProcessExpiredNotificationsResult;
use App\Modules\Library\DTOs\ReservationResponse;
use App\Modules\Library\Entities\Reservation;
use App\Modules\Library\Models\BookModel;
use App\Modules\Library\Models\ReservationModel;
use CodeIgniter\I18n\Time;
use Config\Database;
use Config\Services as AppServices;

/**
 * docs/ADR/ADR-017-library-reservation-queue.md — BR-LIB-006. Explicit
 * trigger only; no scheduler exists in this codebase.
 */
class ReservationService
{
    public function __construct(
        private readonly ReservationModel $reservationModel,
        private readonly BookModel $bookModel,
        private readonly AuditService $auditService,
        private readonly ConfigurationService $configurationService,
        private readonly NotificationLogService $notificationLogService,
    ) {
    }

    public function createReservation(CreateReservationRequest $request): ReservationResponse
    {
        $book = $this->bookModel->find($request->bookId);

        if ($book === null) {
            throw new BusinessRuleException('BOOK_NOT_FOUND', 'Book not found.');
        }

        if ($book->is_available) {
            throw new BusinessRuleException(
                'RESERVATION_NOT_NEEDED',
                'This book is currently available — issue it directly instead of reserving it.',
            );
        }

        $this->validateBorrower($request->borrowerType, $request->borrowerRefId);

        if ($this->reservationModel->findActiveForBorrower($request->bookId, $request->borrowerType, $request->borrowerRefId) !== null) {
            throw new BusinessRuleException(
                'RESERVATION_ALREADY_EXISTS',
                'This borrower already has an active reservation for this book.',
            );
        }

        $id = $this->reservationModel->insert([
            'book_id'         => $request->bookId,
            'borrower_type'   => $request->borrowerType,
            'borrower_ref_id' => $request->borrowerRefId,
            'requested_at'    => Time::now()->toDateTimeString(),
            'status'          => Reservation::STATUS_WAITING,
        ], true);

        $reservation = $this->reservationModel->find($id);

        $this->auditService->record('Reservation', $id, AuditLog::ACTION_CREATE, null, $reservation->toRawArray());

        return new ReservationResponse($reservation);
    }

    public function cancelReservation(int $id): ReservationResponse
    {
        $before = $this->requireReservation($id);

        if (! in_array($before->status, [Reservation::STATUS_WAITING, Reservation::STATUS_NOTIFIED], true)) {
            throw new BusinessRuleException(
                'RESERVATION_INVALID_STATUS_TRANSITION',
                "Cannot cancel a reservation in status {$before->status}.",
            );
        }

        $wasNotified = $before->status === Reservation::STATUS_NOTIFIED;

        $this->reservationModel->update($id, ['status' => Reservation::STATUS_CANCELLED]);
        $after = $this->reservationModel->find($id);

        $this->auditService->record('Reservation', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        // The holder gave up their turn early — offer the book to the
        // next queued holder immediately rather than waiting out a
        // notification window nobody is going to respond to.
        if ($wasNotified) {
            $this->notifyNextInQueue($before->book_id);
        }

        return new ReservationResponse($after);
    }

    public function getReservation(int $id): ReservationResponse
    {
        return new ReservationResponse($this->requireReservation($id));
    }

    /**
     * @return list<ReservationResponse>
     */
    public function listByBook(int $bookId): array
    {
        return array_map(
            static fn (Reservation $reservation): ReservationResponse => new ReservationResponse($reservation),
            $this->reservationModel->findByBook($bookId),
        );
    }

    /**
     * @return list<ReservationResponse>
     */
    public function listByBorrower(string $borrowerType, int $borrowerRefId): array
    {
        return array_map(
            static fn (Reservation $reservation): ReservationResponse => new ReservationResponse($reservation),
            $this->reservationModel->findByBorrower($borrowerType, $borrowerRefId),
        );
    }

    /**
     * docs/ADR/ADR-017-library-reservation-queue.md §5 — the "book
     * returned" trigger, called from `BookIssueService::returnBook()`
     * immediately after the book is marked available. FIFO by
     * `requested_at`. Returns null when the queue for this book is
     * empty — general issue proceeds unblocked.
     */
    public function notifyNextInQueue(int $bookId): ?ReservationResponse
    {
        $candidate = $this->reservationModel->findEarliestWaitingForBook($bookId);

        if ($candidate === null) {
            return null;
        }

        $before = $candidate;
        $id     = (int) $candidate->reservation_id;
        $now    = Time::now();

        $this->reservationModel->update($id, [
            'status'                   => Reservation::STATUS_NOTIFIED,
            'notified_at'              => $now->toDateTimeString(),
            'notification_expires_at'  => $now->addHours((int) $this->configurationService->getNumber('library.reservation_response_window_hours'))->toDateTimeString(),
        ]);

        $after = $this->reservationModel->find($id);

        $this->auditService->record(
            'Reservation',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
            'BR-LIB-006: book available, longest-waiting reservation holder notified.',
        );

        $this->notificationLogService->create(new CreateNotificationLogRequest(
            $after->borrower_type === Reservation::BORROWER_STUDENT ? NotificationLog::RECIPIENT_STUDENT : NotificationLog::RECIPIENT_EMPLOYEE,
            $after->borrower_ref_id,
            NotificationLog::CHANNEL_SMS,
            'BR-LIB-006 book available for reservation',
        ));

        return new ReservationResponse($after);
    }

    /**
     * docs/ADR/ADR-017-library-reservation-queue.md §5/§6 — the
     * window-expiry trigger. Explicit-trigger only (no scheduler exists
     * in this codebase); callable on demand by a Librarian today.
     */
    public function processExpiredNotifications(): ProcessExpiredNotificationsResult
    {
        $now        = Time::now();
        $candidates = $this->reservationModel->findExpiredNotifications($now->toDateTimeString());

        $expirations = [];

        foreach ($candidates as $candidate) {
            $expiration = $this->expireOneNotification((int) $candidate->reservation_id, (int) $candidate->book_id, $now);

            if ($expiration !== null) {
                $expirations[] = $expiration;
            }
        }

        return new ProcessExpiredNotificationsResult($expirations);
    }

    /**
     * docs/ADR/ADR-017-library-reservation-queue.md §6 — the exact
     * `SELECT ... FOR UPDATE` predicate `BookIssueService::issueBook()`
     * locks against, exposed so both methods contend on the identical
     * row lock rather than two independently-shaped queries.
     *
     * @return array<string, mixed>|null
     */
    public function lockNotifiedReservationForBook(int $bookId): ?array
    {
        return $this->reservationModel->lockNotifiedForBook($bookId);
    }

    /**
     * Called by `BookIssueService::issueBook()`, inside the same
     * transaction that already holds `lockNotifiedReservationForBook()`'s
     * row lock, once the notified holder's own issue succeeds.
     */
    public function markFulfilled(int $id): void
    {
        $before = $this->requireReservation($id);

        $this->reservationModel->update($id, ['status' => Reservation::STATUS_FULFILLED]);
        $after = $this->reservationModel->find($id);

        $this->auditService->record('Reservation', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());
    }

    /**
     * @return array{expired_reservation_id: int, promoted_reservation_id: ?int}|null
     *               null when the candidate was no longer eligible by the
     *               time its row lock was acquired (already resolved by
     *               a concurrent `BookIssueService::issueBook()` fulfilling it).
     */
    private function expireOneNotification(int $reservationId, int $bookId, Time $now): ?array
    {
        $db = Database::connect();
        $db->transStart();

        $locked = $this->reservationModel->lockNotifiedForBook($bookId);

        if (
            $locked === null
            || (int) $locked['reservation_id'] !== $reservationId
            || $locked['notification_expires_at'] === null
            || $locked['notification_expires_at'] > $now->toDateTimeString()
        ) {
            $db->transComplete();

            return null;
        }

        $before = $this->reservationModel->find($reservationId);

        $this->reservationModel->update($reservationId, ['status' => Reservation::STATUS_EXPIRED]);
        $after = $this->reservationModel->find($reservationId);

        $this->auditService->record(
            'Reservation',
            $reservationId,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
            'BR-LIB-006: notification response window expired without collection.',
        );

        $promoted = $this->notifyNextInQueue($bookId);

        $db->transComplete();

        return [
            'expired_reservation_id'  => $reservationId,
            'promoted_reservation_id' => $promoted?->reservationId,
        ];
    }

    private function validateBorrower(string $borrowerType, int $borrowerRefId): void
    {
        if ($borrowerType === Reservation::BORROWER_STUDENT) {
            AppServices::studentService()->getStudent($borrowerRefId);

            return;
        }

        AppServices::employeeService()->getEmployee($borrowerRefId);
    }

    private function requireReservation(int $id): Reservation
    {
        $reservation = $this->reservationModel->find($id);

        if ($reservation === null) {
            throw new BusinessRuleException('RESERVATION_NOT_FOUND', 'Reservation not found.');
        }

        return $reservation;
    }
}
