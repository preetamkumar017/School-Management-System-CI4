<?php

declare(strict_types=1);

namespace App\Modules\Examination\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/examination/Phase-1-Domain-Model.md — ENT-EXM-001.
 *
 * @property int|null $exam_id
 * @property string   $exam_name
 * @property int      $class_id
 * @property int      $academic_session_id
 * @property int      $grading_scheme_id
 * @property string   $exam_date
 * @property string   $status
 */
class Exam extends BaseEntity
{
    public const STATUS_CONFIGURED = 'CONFIGURED';
    public const STATUS_ACTIVE     = 'ACTIVE';
    public const STATUS_LOCKED     = 'LOCKED';
    public const STATUS_CLOSED     = 'CLOSED';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'exam_id'             => 'integer',
            'class_id'            => 'integer',
            'academic_session_id' => 'integer',
            'grading_scheme_id'   => 'integer',
        ]);

        parent::__construct($data);
    }
}
