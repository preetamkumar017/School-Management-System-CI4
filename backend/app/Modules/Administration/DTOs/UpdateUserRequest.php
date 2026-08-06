<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

final class UpdateUserRequest
{
    public function __construct(
        public readonly string $username,
        public readonly int $roleId,
    ) {
    }
}
