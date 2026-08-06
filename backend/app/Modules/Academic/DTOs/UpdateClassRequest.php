<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class UpdateClassRequest
{
    public function __construct(
        public readonly string $className,
        public readonly int $sequenceOrder,
    ) {
    }
}
