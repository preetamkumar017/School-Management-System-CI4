<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

use App\Modules\Academic\Entities\AcademicClass;

/**
 * docs/design/academic/Phase-3-DTO-Design.md
 */
final class ClassResponse
{
    public readonly int $classId;
    public readonly string $className;
    public readonly int $sequenceOrder;

    public function __construct(AcademicClass $class)
    {
        $this->classId       = $class->class_id;
        $this->className     = $class->class_name;
        $this->sequenceOrder = $class->sequence_order;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class_id'       => $this->classId,
            'class_name'     => $this->className,
            'sequence_order' => $this->sequenceOrder,
        ];
    }
}
