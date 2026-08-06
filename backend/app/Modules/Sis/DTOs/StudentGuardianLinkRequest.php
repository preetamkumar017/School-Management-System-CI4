<?php

declare(strict_types=1);

namespace App\Modules\Sis\DTOs;

final class StudentGuardianLinkRequest
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $guardianId,
        public readonly bool $isPrimaryContact = false,
    ) {
    }
}
