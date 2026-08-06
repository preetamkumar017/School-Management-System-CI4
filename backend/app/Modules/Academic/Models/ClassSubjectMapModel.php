<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Entities\ClassSubjectMap;
use CodeIgniter\Model;

/**
 * docs/design/academic/Phase-2-Model-Design.md
 * Composite key (class_id, subject_id) — no surrogate PK, so this never
 * calls find($id)/delete($id); every read/write goes through the explicit
 * composite-key methods below. Extends CodeIgniter\Model directly, not
 * App\Core\BaseModel, same reasoning as AuditLogModel/RefreshTokenModel.
 */
class ClassSubjectMapModel extends Model
{
    protected $table         = 'class_subject_map';
    protected $primaryKey    = 'class_id';
    protected $returnType    = ClassSubjectMap::class;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'class_id',
        'subject_id',
    ];

    public function existsByClassIdAndSubjectId(int $classId, int $subjectId): bool
    {
        return $this->where('class_id', $classId)->where('subject_id', $subjectId)->countAllResults() > 0;
    }

    public function insertMapping(int $classId, int $subjectId): void
    {
        $this->insert(['class_id' => $classId, 'subject_id' => $subjectId]);
    }

    public function deleteMapping(int $classId, int $subjectId): void
    {
        $this->where('class_id', $classId)->where('subject_id', $subjectId)->delete();
    }

    /**
     * @return list<ClassSubjectMap>
     */
    public function findByClassId(int $classId): array
    {
        return $this->where('class_id', $classId)->findAll();
    }

    /**
     * @return list<ClassSubjectMap>
     */
    public function findBySubjectId(int $subjectId): array
    {
        return $this->where('subject_id', $subjectId)->findAll();
    }
}
