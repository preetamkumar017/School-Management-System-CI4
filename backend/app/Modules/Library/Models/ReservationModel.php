<?php

declare(strict_types=1);

namespace App\Modules\Library\Models;

use App\Core\BaseModel;
use App\Modules\Library\Entities\Reservation;

/**
 * docs/ADR/ADR-017-library-reservation-queue.md
 */
class ReservationModel extends BaseModel
{
    protected $table          = 'reservations';
    protected $primaryKey     = 'reservation_id';
    protected $returnType     = Reservation::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'book_id',
        'borrower_type',
        'borrower_ref_id',
        'requested_at',
        'status',
        'notified_at',
        'notification_expires_at',
        'created_by',
        'updated_by',
    ];

    public function findEarliestWaitingForBook(int $bookId): ?Reservation
    {
        return $this->where('book_id', $bookId)
            ->where('status', Reservation::STATUS_WAITING)
            ->orderBy('requested_at', 'ASC')
            ->first();
    }

    public function findActiveForBorrower(int $bookId, string $borrowerType, int $borrowerRefId): ?Reservation
    {
        return $this->where('book_id', $bookId)
            ->where('borrower_type', $borrowerType)
            ->where('borrower_ref_id', $borrowerRefId)
            ->whereIn('status', [Reservation::STATUS_WAITING, Reservation::STATUS_NOTIFIED])
            ->first();
    }

    /**
     * @return list<Reservation>
     */
    public function findByBook(int $bookId): array
    {
        return $this->where('book_id', $bookId)->orderBy('requested_at', 'ASC')->findAll();
    }

    /**
     * @return list<Reservation>
     */
    public function findByBorrower(string $borrowerType, int $borrowerRefId): array
    {
        return $this->where('borrower_type', $borrowerType)
            ->where('borrower_ref_id', $borrowerRefId)
            ->orderBy('requested_at', 'ASC')
            ->findAll();
    }

    /**
     * @return list<Reservation>
     */
    public function findExpiredNotifications(string $now): array
    {
        return $this->where('status', Reservation::STATUS_NOTIFIED)
            ->where('notification_expires_at <', $now)
            ->findAll();
    }

    /**
     * docs/ADR/ADR-017-library-reservation-queue.md §6 — the exact
     * predicate `BookIssueService::issueBook()` and
     * `ReservationService::processExpiredNotifications()` both lock
     * against, following `SeatAllocationModel::incrementSeatsFilled()`/
     * `ApplicationModel::lockForUpdate()`'s raw-SQL row-lock shape.
     * `status` is part of the WHERE clause itself, so a row already
     * transitioned out of `Notified` by a winning concurrent transaction
     * simply locks nothing here rather than needing a separate re-check.
     *
     * @return array<string, mixed>|null
     */
    public function lockNotifiedForBook(int $bookId): ?array
    {
        return $this->db->query(
            'SELECT reservation_id, book_id, borrower_type, borrower_ref_id, status, notification_expires_at '
                . 'FROM reservations WHERE book_id = ? AND status = ? FOR UPDATE',
            [$bookId, Reservation::STATUS_NOTIFIED],
        )->getRowArray();
    }
}
