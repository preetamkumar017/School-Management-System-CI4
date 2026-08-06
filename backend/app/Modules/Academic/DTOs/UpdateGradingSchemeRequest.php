<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

/**
 * docs/design/academic/Phase-3-DTO-Design.md — Phase 4's decided shape:
 * scheme_name is deliberately absent. This mutates the existing row only
 * if unreferenced by a closed exam; otherwise the caller creates a new
 * GradingScheme (a new scheme_name) via CreateGradingSchemeRequest instead
 * — this DTO never produces a new row itself.
 */
final class UpdateGradingSchemeRequest
{
    /**
     * @param array<string, string> $gradeBandJson
     */
    public function __construct(
        public readonly string $boardType,
        public readonly array $gradeBandJson,
    ) {
    }
}
