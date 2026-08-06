<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Core\BaseModel;
use App\Modules\Academic\Entities\Section;

/**
 * docs/design/academic/Phase-2-Model-Design.md
 *
 * Deliberately does NOT expose an occupancy-count method — occupancy of
 * active students in a section is SIS's own responsibility against its own
 * `students` table (Phase 2/4), not something Academic computes.
 */
class SectionModel extends BaseModel
{
    protected $table          = 'sections';
    protected $primaryKey     = 'section_id';
    protected $returnType     = Section::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'class_id',
        'section_name',
        'capacity',
        'created_by',
        'updated_by',
    ];

    /**
     * @return list<Section>
     */
    public function findByClassId(int $classId): array
    {
        return $this->where('class_id', $classId)->findAll();
    }

    public function existsByClassIdAndSectionName(int $classId, string $sectionName): bool
    {
        return $this->where('class_id', $classId)->where('section_name', $sectionName)->countAllResults() > 0;
    }

    public function existsByClassIdAndSectionNameExceptId(int $classId, string $sectionName, int $id): bool
    {
        return $this->where('class_id', $classId)
            ->where('section_name', $sectionName)
            ->where('section_id !=', $id)
            ->countAllResults() > 0;
    }
}
