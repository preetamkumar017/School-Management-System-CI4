<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

use App\Modules\Academic\Entities\TeacherClassSubjectMap;

final class TeacherClassSubjectMapResponse
{
    public readonly int $teacherClassSubjectMapId;
    public readonly int $academicSessionId;
    public readonly int $classId;
    public readonly int $sectionId;
    public readonly int $subjectId;
    public readonly int $employeeId;

    public function __construct(TeacherClassSubjectMap $map)
    {
        $this->teacherClassSubjectMapId = $map->teacher_class_subject_map_id;
        $this->academicSessionId        = $map->academic_session_id;
        $this->classId                  = $map->class_id;
        $this->sectionId                = $map->section_id;
        $this->subjectId                = $map->subject_id;
        $this->employeeId               = $map->employee_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'teacher_class_subject_map_id' => $this->teacherClassSubjectMapId,
            'academic_session_id'          => $this->academicSessionId,
            'class_id'                     => $this->classId,
            'section_id'                   => $this->sectionId,
            'subject_id'                   => $this->subjectId,
            'employee_id'                  => $this->employeeId,
        ];
    }
}
