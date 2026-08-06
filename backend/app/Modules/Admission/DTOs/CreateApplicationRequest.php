<?php

declare(strict_types=1);

namespace App\Modules\Admission\DTOs;

final class CreateApplicationRequest
{
    public function __construct(
        public readonly string $applicantName,
        public readonly string $dob,
        public readonly int $classAppliedId,
        public readonly ?string $aadhaarNumber,
        public readonly string $category,
    ) {
    }
}
