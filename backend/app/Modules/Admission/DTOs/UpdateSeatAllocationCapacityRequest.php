<?php

declare(strict_types=1);

namespace App\Modules\Admission\DTOs;

/**
 * docs/design/admission/Phase-3-DTO-Design.md — class_id/academic_session_id
 * are create-only/immutable, same reasoning as Academic's Section.class_id.
 */
final class UpdateSeatAllocationCapacityRequest
{
    public function __construct(
        public readonly int $totalCapacity,
        public readonly int $rteQuotaCapacity,
    ) {
    }
}
