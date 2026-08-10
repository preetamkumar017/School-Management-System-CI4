<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Core\BaseModel;
use App\Modules\Academic\Entities\Subject;

/**
 * docs/design/academic/Phase-2-Model-Design.md
 */
class SubjectModel extends BaseModel
{
    protected $table          = 'subjects';
    protected $primaryKey     = 'subject_id';
    protected $returnType     = Subject::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'subject_name',
        'subject_code',
        'subject_category_id',
        'is_language_subject',
        'stream_applicability',
        'created_by',
        'updated_by',
    ];

    public function findBySubjectCode(string $value): ?Subject
    {
        return $this->where('subject_code', $value)->first();
    }

    public function existsBySubjectCode(string $value): bool
    {
        return $this->where('subject_code', $value)->countAllResults() > 0;
    }

    public function existsBySubjectCodeExceptId(string $value, int $id): bool
    {
        return $this->where('subject_code', $value)->where('subject_id !=', $id)->countAllResults() > 0;
    }

    /**
     * Subjects mapped to a class, via the class_subject_map junction — a
     * join, not a direct FK (Subject's Many-to-Many relationship, Phase 1).
     *
     * @return list<Subject>
     */
    public function findByClassId(int $classId): array
    {
        return $this->select('subjects.*')
            ->join('class_subject_map', 'class_subject_map.subject_id = subjects.subject_id')
            ->where('class_subject_map.class_id', $classId)
            ->findAll();
    }
}
