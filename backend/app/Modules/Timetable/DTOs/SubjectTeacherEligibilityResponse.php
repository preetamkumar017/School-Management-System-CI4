<?php

declare(strict_types=1);

namespace App\Modules\Timetable\DTOs;

use App\Modules\Timetable\Entities\SubjectTeacherEligibility;

final class SubjectTeacherEligibilityResponse
{
    public readonly int $subjectTeacherEligibilityId;
    public readonly int $employeeId;
    public readonly int $subjectId;

    public function __construct(SubjectTeacherEligibility $eligibility)
    {
        $this->subjectTeacherEligibilityId = $eligibility->subject_teacher_eligibility_id;
        $this->employeeId                  = $eligibility->employee_id;
        $this->subjectId                   = $eligibility->subject_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subject_teacher_eligibility_id' => $this->subjectTeacherEligibilityId,
            'employee_id'                    => $this->employeeId,
            'subject_id'                     => $this->subjectId,
        ];
    }
}
