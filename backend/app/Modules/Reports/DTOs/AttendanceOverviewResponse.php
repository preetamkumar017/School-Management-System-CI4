<?php

declare(strict_types=1);

namespace App\Modules\Reports\DTOs;

/**
 * docs/ADR/ADR-022-reports-dashboard.md — report area 2 (Attendance
 * overview). Not backed by any entity — Reports has none (ADR-010 §7).
 */
final class AttendanceOverviewResponse
{
    /**
     * @param array<int, float>          $percentageByClass class_id => percentage
     * @param list<array{student_id: int, percentage: float}> $studentsBelowThreshold
     */
    public function __construct(
        public readonly string $fromDate,
        public readonly string $toDate,
        public readonly float $schoolWidePercentage,
        public readonly array $percentageByClass,
        public readonly array $studentsBelowThreshold,
        public readonly float $eligibilityThreshold,
        public readonly string $generatedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from_date'                => $this->fromDate,
            'to_date'                  => $this->toDate,
            'school_wide_percentage'   => $this->schoolWidePercentage,
            'percentage_by_class'      => $this->percentageByClass,
            'students_below_threshold' => $this->studentsBelowThreshold,
            'eligibility_threshold'    => $this->eligibilityThreshold,
            'generated_at'             => $this->generatedAt,
        ];
    }
}
