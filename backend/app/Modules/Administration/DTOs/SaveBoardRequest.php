<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

class SaveBoardRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $shortName,
        public readonly string $boardType,
        public readonly string $country = 'India',
        public readonly ?string $stateApplicability = null,
        public readonly string $status = 'ACTIVE',
        public readonly ?string $description = null
    ) {}
}
