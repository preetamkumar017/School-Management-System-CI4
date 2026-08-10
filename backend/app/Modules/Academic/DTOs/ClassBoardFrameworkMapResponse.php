<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

use App\Modules\Academic\Entities\ClassBoardFrameworkMap;

final class ClassBoardFrameworkMapResponse
{
    public readonly int $classBoardMapId;
    public readonly int $academicSessionId;
    public readonly int $classId;
    public readonly int $frameworkId;

    public function __construct(ClassBoardFrameworkMap $map)
    {
        $this->classBoardMapId   = $map->class_board_map_id;
        $this->academicSessionId = $map->academic_session_id;
        $this->classId           = $map->class_id;
        $this->frameworkId       = $map->framework_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class_board_map_id'  => $this->classBoardMapId,
            'academic_session_id' => $this->academicSessionId,
            'class_id'            => $this->classId,
            'framework_id'        => $this->frameworkId,
        ];
    }
}
