<?php

declare(strict_types=1);

namespace App\Modules\Sis\DTOs;

final class UpdateGuardianRequest
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $relationship,
        public readonly string $mobileNumber,
        public readonly ?string $email,
    ) {
    }
}
