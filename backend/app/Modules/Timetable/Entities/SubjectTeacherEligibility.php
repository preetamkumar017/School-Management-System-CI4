<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md — ENT-TT-002
 * (BR-TT-004). Net-new per ADR-013 §2 — a minimal employee/subject
 * eligibility pair, admin-managed reference data.
 *
 * @property int|null $subject_teacher_eligibility_id
 * @property int      $employee_id
 * @property int      $subject_id
 */
class SubjectTeacherEligibility extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'subject_teacher_eligibility_id' => 'integer',
            'employee_id'                    => 'integer',
            'subject_id'                     => 'integer',
        ]);

        parent::__construct($data);
    }
}
