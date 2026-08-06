<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

/**
 * docs/design/administration/Phase-3-DTO-Design.md
 */
final class LoginRequest
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
    ) {
    }
}
