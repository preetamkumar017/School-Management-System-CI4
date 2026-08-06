<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

/**
 * docs/design/academic/Phase-3-DTO-Design.md — class_id is deliberately
 * absent: immutable after creation, no documented flow moves a Section to
 * a different Class.
 */
final class UpdateSectionRequest
{
    public function __construct(
        public readonly string $sectionName,
        public readonly int $capacity,
    ) {
    }
}
