<?php

declare(strict_types=1);

namespace App\Modules\Examination\DTOs;

use App\Modules\Examination\Entities\ReportCard;

/**
 * docs/design/examination/Phase-3-DTO-Design.md — no Create…Request; rows
 * are produced only by ExamService::lockExam.
 */
final class ReportCardResponse
{
    public readonly int $reportCardId;
    public readonly int $studentId;
    public readonly int $examId;

    /** @var array<string, string> */
    public readonly array $gradeSummary;
    public readonly float $gpa;
    public readonly ?int $classRank;
    public readonly bool $isPublished;
    public readonly ?string $publishedAt;

    public function __construct(ReportCard $reportCard)
    {
        $this->reportCardId = $reportCard->report_card_id;
        $this->studentId    = $reportCard->student_id;
        $this->examId       = $reportCard->exam_id;
        $this->gradeSummary = $reportCard->grade_summary;
        $this->gpa          = $reportCard->gpa;
        $this->classRank    = $reportCard->class_rank;
        $this->isPublished  = $reportCard->is_published;
        $this->publishedAt  = $reportCard->published_at?->toDateTimeString();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'report_card_id' => $this->reportCardId,
            'student_id'     => $this->studentId,
            'exam_id'        => $this->examId,
            'grade_summary'  => $this->gradeSummary,
            'gpa'            => $this->gpa,
            'class_rank'     => $this->classRank,
            'is_published'   => $this->isPublished,
            'published_at'   => $this->publishedAt,
        ];
    }
}
