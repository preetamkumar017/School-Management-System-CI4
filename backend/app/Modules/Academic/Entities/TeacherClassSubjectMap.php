<?php

declare(strict_types=1);

namespace App\Modules\Academic\Entities;

use App\Core\BaseEntity;

/**
 * @property int|null $teacher_class_subject_map_id
 * @property int      $academic_session_id
 * @property int      $class_id
 * @property int      $section_id
 * @property int      $subject_id
 * @property int      $employee_id
 */
class TeacherClassSubjectMap extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'teacher_class_subject_map_id' => 'integer',
            'academic_session_id'          => 'integer',
            'class_id'                     => 'integer',
            'section_id'                   => 'integer',
            'subject_id'                   => 'integer',
            'employee_id'                  => 'integer',
        ]);

        parent::__construct($data);
    }
}
