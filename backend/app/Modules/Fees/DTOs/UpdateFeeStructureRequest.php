<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md — class_id/fee_head_id/
 * academic_session_id/category are immutable post-creation; only amount
 * is updatable.
 */
final class UpdateFeeStructureRequest
{
    public function __construct(public readonly float $amount)
    {
    }
}
