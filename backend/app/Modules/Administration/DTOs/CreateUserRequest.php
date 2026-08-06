<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

final class CreateUserRequest
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly int $roleId,
        public readonly string $ownerType,
        public readonly int $ownerRefId,
    ) {
    }
}
