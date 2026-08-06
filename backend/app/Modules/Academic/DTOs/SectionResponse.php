<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

use App\Modules\Academic\Entities\Section;

/**
 * docs/design/academic/Phase-3-DTO-Design.md
 */
final class SectionResponse
{
    public readonly int $sectionId;
    public readonly int $classId;
    public readonly string $sectionName;
    public readonly int $capacity;

    public function __construct(Section $section)
    {
        $this->sectionId   = $section->section_id;
        $this->classId     = $section->class_id;
        $this->sectionName = $section->section_name;
        $this->capacity    = $section->capacity;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'section_id'   => $this->sectionId,
            'class_id'     => $this->classId,
            'section_name' => $this->sectionName,
            'capacity'     => $this->capacity,
        ];
    }
}
