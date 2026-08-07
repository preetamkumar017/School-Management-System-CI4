<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

final class CreateCircularRequest
{
    public function __construct(
        public readonly int $authorId,
        public readonly string $postType,
        public readonly string $title,
        public readonly string $body,
        public readonly string $targetAudience,
    ) {
    }
}
