<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

final class CreateDriverRequest
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $licenseNumber,
        public readonly ?string $licenseValidUntil,
        public readonly string $status,
    ) {
    }
}
