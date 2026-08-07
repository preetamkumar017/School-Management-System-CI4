<?php

declare(strict_types=1);

namespace App\Modules\Library\DTOs;

/**
 * docs/ADR/ADR-017-library-reservation-queue.md §5 — the response shape
 * for the explicit-trigger
 * `POST /library/reservations/process-expired-notifications` action,
 * matching `ReleaseExpiredHoldsResult`'s shape exactly.
 */
final class ProcessExpiredNotificationsResult
{
    /**
     * @param list<array{expired_reservation_id: int, promoted_reservation_id: ?int}> $expirations
     */
    public function __construct(
        public readonly array $expirations,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'expired_count'  => count($this->expirations),
            'promoted_count' => count(array_filter($this->expirations, static fn (array $e): bool => $e['promoted_reservation_id'] !== null)),
            'expirations'    => $this->expirations,
        ];
    }
}
