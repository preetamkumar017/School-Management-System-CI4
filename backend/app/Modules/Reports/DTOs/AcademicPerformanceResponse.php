<?php

declare(strict_types=1);

namespace App\Modules\Reports\DTOs;

/**
 * docs/ADR/ADR-022-reports-dashboard.md — report area 4 (Academic
 * performance). Not backed by any entity — Reports has none (ADR-010 §7).
 * gpa/class_rank are read verbatim from ReportCard rows already computed
 * by ExamService::recalculateReportCards (Stage 6a's decided GPA formula
 * and standard-competition class-rank convention) — never recomputed here.
 */
final class AcademicPerformanceResponse
{
    /**
     * @param array<int, int> $rankDistribution class_rank => count of students holding that rank
     */
    public function __construct(
        public readonly int $examId,
        public readonly int $reportCardCount,
        public readonly float $averageGpa,
        public readonly int $passCount,
        public readonly int $failCount,
        public readonly float $passThresholdGpa,
        public readonly array $rankDistribution,
        public readonly string $generatedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'exam_id'             => $this->examId,
            'report_card_count'   => $this->reportCardCount,
            'average_gpa'         => $this->averageGpa,
            'pass_count'          => $this->passCount,
            'fail_count'          => $this->failCount,
            'pass_threshold_gpa'  => $this->passThresholdGpa,
            'rank_distribution'   => $this->rankDistribution,
            'generated_at'        => $this->generatedAt,
        ];
    }
}
