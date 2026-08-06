<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

use App\Modules\Academic\Entities\ClassSubjectMap;

/**
 * docs/design/academic/Phase-3-DTO-Design.md
 */
final class ClassSubjectMapResponse
{
    public readonly int $classId;
    public readonly int $subjectId;

    public function __construct(ClassSubjectMap $map)
    {
        $this->classId   = $map->class_id;
        $this->subjectId = $map->subject_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class_id'   => $this->classId,
            'subject_id' => $this->subjectId,
        ];
    }
}
