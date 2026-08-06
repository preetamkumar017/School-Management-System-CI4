<?php

declare(strict_types=1);

namespace App\Modules\Examination\DTOs;

use App\Modules\Examination\Entities\PromotionRecord;

/**
 * docs/design/examination/Phase-3-DTO-Design.md
 */
final class PromotionRecordResponse
{
    public readonly int $promotionRecordId;
    public readonly int $studentId;
    public readonly int $fromSessionId;
    public readonly int $toSessionId;
    public readonly int $fromClassId;
    public readonly int $toClassId;
    public readonly bool $academicClosureConfirmed;
    public readonly bool $feeClosureConfirmed;

    public function __construct(PromotionRecord $promotionRecord)
    {
        $this->promotionRecordId       = $promotionRecord->promotion_record_id;
        $this->studentId               = $promotionRecord->student_id;
        $this->fromSessionId           = $promotionRecord->from_session_id;
        $this->toSessionId             = $promotionRecord->to_session_id;
        $this->fromClassId             = $promotionRecord->from_class_id;
        $this->toClassId               = $promotionRecord->to_class_id;
        $this->academicClosureConfirmed = $promotionRecord->academic_closure_confirmed;
        $this->feeClosureConfirmed     = $promotionRecord->fee_closure_confirmed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'promotion_record_id'        => $this->promotionRecordId,
            'student_id'                 => $this->studentId,
            'from_session_id'            => $this->fromSessionId,
            'to_session_id'              => $this->toSessionId,
            'from_class_id'              => $this->fromClassId,
            'to_class_id'                => $this->toClassId,
            'academic_closure_confirmed' => $this->academicClosureConfirmed,
            'fee_closure_confirmed'      => $this->feeClosureConfirmed,
        ];
    }
}
