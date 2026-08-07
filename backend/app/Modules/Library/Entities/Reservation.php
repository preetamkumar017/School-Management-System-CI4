<?php

declare(strict_types=1);

namespace App\Modules\Library\Entities;

use App\Core\BaseEntity;

/**
 * docs/ADR/ADR-017-library-reservation-queue.md §1/§2 — BR-LIB-006. A
 * net-new entity; no Appendix-G card exists for it (ADR-009 §7). Reuses
 * BookIssue's polymorphic borrower_type/borrower_ref_id shape exactly.
 *
 * @property int|null $reservation_id
 * @property int      $book_id
 * @property string   $borrower_type
 * @property int      $borrower_ref_id
 * @property \CodeIgniter\I18n\Time $requested_at
 * @property string   $status
 * @property \CodeIgniter\I18n\Time|null $notified_at
 * @property \CodeIgniter\I18n\Time|null $notification_expires_at
 */
class Reservation extends BaseEntity
{
    public const BORROWER_STUDENT  = 'Student';
    public const BORROWER_EMPLOYEE = 'Employee';

    public const STATUS_WAITING   = 'Waiting';
    public const STATUS_NOTIFIED  = 'Notified';
    public const STATUS_FULFILLED = 'Fulfilled';
    public const STATUS_EXPIRED   = 'Expired';
    public const STATUS_CANCELLED = 'Cancelled';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'reservation_id'  => 'integer',
            'book_id'         => 'integer',
            'borrower_ref_id' => 'integer',
        ]);

        $this->dates = array_merge($this->dates, ['requested_at', 'notified_at', 'notification_expires_at']);

        parent::__construct($data);
    }
}
