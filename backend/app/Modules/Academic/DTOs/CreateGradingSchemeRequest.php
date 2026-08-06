<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

final class CreateGradingSchemeRequest
{
    /**
     * @param array<string, string> $gradeBandJson
     */
    public function __construct(
        public readonly string $schemeName,
        public readonly string $boardType,
        public readonly array $gradeBandJson,
    ) {
    }
}
