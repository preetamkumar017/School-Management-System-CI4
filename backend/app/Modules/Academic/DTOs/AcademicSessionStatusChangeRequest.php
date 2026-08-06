<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class AcademicSessionStatusChangeRequest
{
    public function __construct(public readonly string $status)
    {
    }
}
