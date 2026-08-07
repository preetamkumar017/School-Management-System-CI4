<?php

declare(strict_types=1);

namespace App\Modules\Library\DTOs;

use App\Modules\Library\Entities\Reservation;

final class ReservationResponse
{
    public readonly int $reservationId;
    public readonly int $bookId;
    public readonly string $borrowerType;
    public readonly int $borrowerRefId;
    public readonly string $requestedAt;
    public readonly string $status;
    public readonly ?string $notifiedAt;
    public readonly ?string $notificationExpiresAt;

    public function __construct(Reservation $reservation)
    {
        $this->reservationId         = $reservation->reservation_id;
        $this->bookId                = $reservation->book_id;
        $this->borrowerType          = $reservation->borrower_type;
        $this->borrowerRefId         = $reservation->borrower_ref_id;
        $this->requestedAt           = (string) $reservation->requested_at;
        $this->status                = $reservation->status;
        $this->notifiedAt            = $reservation->notified_at === null ? null : (string) $reservation->notified_at;
        $this->notificationExpiresAt = $reservation->notification_expires_at === null ? null : (string) $reservation->notification_expires_at;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reservation_id'           => $this->reservationId,
            'book_id'                  => $this->bookId,
            'borrower_type'            => $this->borrowerType,
            'borrower_ref_id'          => $this->borrowerRefId,
            'requested_at'             => $this->requestedAt,
            'status'                   => $this->status,
            'notified_at'              => $this->notifiedAt,
            'notification_expires_at'  => $this->notificationExpiresAt,
        ];
    }
}
