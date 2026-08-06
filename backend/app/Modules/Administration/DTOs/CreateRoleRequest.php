<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

final class CreateRoleRequest
{
    /**
     * @param list<string> $permissionSet
     */
    public function __construct(
        public readonly string $roleName,
        public readonly ?string $description,
        public readonly array $permissionSet,
    ) {
    }
}
