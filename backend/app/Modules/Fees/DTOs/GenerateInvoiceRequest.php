<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

final class GenerateInvoiceRequest
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $academicSessionId,
        public readonly string $dueDate,
    ) {
    }
}
