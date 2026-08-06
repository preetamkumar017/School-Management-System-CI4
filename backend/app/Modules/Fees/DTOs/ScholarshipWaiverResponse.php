<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

use App\Modules\Fees\Entities\ScholarshipWaiver;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
final class ScholarshipWaiverResponse
{
    public readonly int $scholarshipWaiverId;
    public readonly int $studentId;
    public readonly int $feeHeadId;
    public readonly string $waiverType;
    public readonly float $waiverAmount;

    public function __construct(ScholarshipWaiver $waiver)
    {
        $this->scholarshipWaiverId = $waiver->scholarship_waiver_id;
        $this->studentId           = $waiver->student_id;
        $this->feeHeadId           = $waiver->fee_head_id;
        $this->waiverType          = $waiver->waiver_type;
        $this->waiverAmount        = $waiver->waiver_amount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scholarship_waiver_id' => $this->scholarshipWaiverId,
            'student_id'            => $this->studentId,
            'fee_head_id'           => $this->feeHeadId,
            'waiver_type'           => $this->waiverType,
            'waiver_amount'         => $this->waiverAmount,
        ];
    }
}
