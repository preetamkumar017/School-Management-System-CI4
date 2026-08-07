<?php

declare(strict_types=1);

namespace App\Modules\Library\DTOs;

final class CreateReservationRequest
{
    public function __construct(
        public readonly int $bookId,
        public readonly string $borrowerType,
        public readonly int $borrowerRefId,
    ) {
    }
}
