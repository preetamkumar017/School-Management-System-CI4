<?php

declare(strict_types=1);

namespace App\Modules\Library\DTOs;

final class IssueBookRequest
{
    public function __construct(
        public readonly int $bookId,
        public readonly string $borrowerType,
        public readonly int $borrowerRefId,
        public readonly string $dueDate,
    ) {
    }
}
