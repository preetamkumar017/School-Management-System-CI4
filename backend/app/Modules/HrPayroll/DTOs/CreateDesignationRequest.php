<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

final class CreateDesignationRequest
{
    public function __construct(
        public readonly string $designationName,
    ) {
    }
}
