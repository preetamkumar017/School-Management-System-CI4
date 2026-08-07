<?php

declare(strict_types=1);

namespace App\Modules\Library\DTOs;

final class CreateBookRequest
{
    public function __construct(
        public readonly string $barcode,
        public readonly string $title,
        public readonly ?string $author,
        public readonly string $classification,
    ) {
    }
}
