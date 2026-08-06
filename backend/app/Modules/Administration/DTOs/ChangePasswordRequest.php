<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

final class ChangePasswordRequest
{
    public function __construct(
        public readonly string $currentPassword,
        public readonly string $newPassword,
    ) {
    }
}
