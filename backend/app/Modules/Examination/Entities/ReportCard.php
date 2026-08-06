<?php

declare(strict_types=1);

namespace App\Modules\Examination\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/examination/Phase-1-Domain-Model.md — ENT-EXM-003.
 * No `Document` child entity — ADR-005 §9 (data record only, no PDF).
 *
 * @property int|null              $report_card_id
 * @property int                   $student_id
 * @property int                   $exam_id
 * @property array<string, string> $grade_summary
 * @property float                 $gpa
 * @property int|null              $class_rank
 * @property bool                  $is_published
 * @property \CodeIgniter\I18n\Time|null $published_at
 */
class ReportCard extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'report_card_id' => 'integer',
            'student_id'     => 'integer',
            'exam_id'        => 'integer',
            'grade_summary'  => 'json-array',
            'gpa'            => 'float',
            'class_rank'     => '?integer',
            'is_published'   => 'boolean',
        ]);

        $this->dates = array_merge($this->dates, ['published_at']);

        parent::__construct($data);
    }
}
