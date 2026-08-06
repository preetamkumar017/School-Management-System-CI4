<?php

declare(strict_types=1);

namespace App\Modules\Examination\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/examination/Phase-1-Domain-Model.md — ENT-EXM-002.
 *
 * @property int|null      $marks_record_id
 * @property int           $exam_id
 * @property int           $student_id
 * @property int           $subject_id
 * @property float|null    $marks_obtained
 * @property float         $max_marks
 * @property bool          $is_flagged
 * @property bool          $is_locked
 */
class MarksRecord extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'marks_record_id' => 'integer',
            'exam_id'         => 'integer',
            'student_id'      => 'integer',
            'subject_id'      => 'integer',
            'marks_obtained'  => '?float',
            'max_marks'       => 'float',
            'is_flagged'      => 'boolean',
            'is_locked'       => 'boolean',
        ]);

        parent::__construct($data);
    }
}
