<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

final class RefreshRequest
{
    public function __construct(public readonly string $refreshToken)
    {
    }
}
