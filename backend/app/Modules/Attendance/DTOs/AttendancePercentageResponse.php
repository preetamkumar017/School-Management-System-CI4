<?php

declare(strict_types=1);

namespace App\Modules\Attendance\DTOs;

/**
 * docs/design/attendance/Phase-2-Model-DTO-Design.md — read-model, not a
 * persisted entity.
 */
final class AttendancePercentageResponse
{
    public function __construct(
        public readonly int $studentId,
        public readonly string $fromDate,
        public readonly string $toDate,
        public readonly float $percentage,
        public readonly bool $isExamEligibilityAtRisk,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'student_id'                   => $this->studentId,
            'from_date'                    => $this->fromDate,
            'to_date'                      => $this->toDate,
            'percentage'                  => $this->percentage,
            'is_exam_eligibility_at_risk' => $this->isExamEligibilityAtRisk,
        ];
    }
}
