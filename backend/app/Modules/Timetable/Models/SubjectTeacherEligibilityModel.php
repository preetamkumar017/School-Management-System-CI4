<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Models;

use App\Core\BaseModel;
use App\Modules\Timetable\Entities\SubjectTeacherEligibility;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md
 */
class SubjectTeacherEligibilityModel extends BaseModel
{
    protected $table          = 'subject_teacher_eligibilities';
    protected $primaryKey     = 'subject_teacher_eligibility_id';
    protected $returnType     = SubjectTeacherEligibility::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'employee_id',
        'subject_id',
        'created_by',
        'updated_by',
    ];

    public function existsByEmployeeSubject(int $employeeId, int $subjectId): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('subject_id', $subjectId)
            ->countAllResults() > 0;
    }

    /**
     * @return list<int>
     */
    public function findEmployeeIdsBySubject(int $subjectId): array
    {
        $rows = $this->select('employee_id')->where('subject_id', $subjectId)->findAll();

        return array_map(static fn (SubjectTeacherEligibility $row): int => $row->employee_id, $rows);
    }
}
