<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Entities\ClassSubjectMap;
use CodeIgniter\Model;

class ClassSubjectMapModel extends Model
{
    protected $table         = 'class_subject_map';
    protected $primaryKey    = 'class_id'; // Note: CI4 doesn't natively support composite primary keys.
    protected $returnType    = ClassSubjectMap::class;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'academic_session_id',
        'class_id',
        'subject_id',
        'is_mandatory',
    ];

    public function exists(int $sessionId, int $classId, int $subjectId): bool
    {
        return $this->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->countAllResults() > 0;
    }

    public function insertMapping(int $sessionId, int $classId, int $subjectId, int $isMandatory = 1): void
    {
        $this->insert([
            'academic_session_id' => $sessionId,
            'class_id'            => $classId,
            'subject_id'          => $subjectId,
            'is_mandatory'        => $isMandatory,
        ]);
    }

    public function deleteMapping(int $sessionId, int $classId, int $subjectId): void
    {
        $this->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->delete();
    }

    /**
     * @return list<ClassSubjectMap>
     */
    public function findBySessionAndClass(int $sessionId, int $classId): array
    {
        return $this->where('academic_session_id', $sessionId)
            ->where('class_id', $classId)
            ->findAll();
    }

    /**
     * Legacy helper mapping for compatibility (e.g. testing or old code without session context).
     * Fallbacks to current active session if not supplied.
     */
    public function existsByClassIdAndSubjectId(int $classId, int $subjectId): bool
    {
        // Try current active session fallback
        $db = \Config\Database::connect();
        $activeSession = $db->table('academic_sessions')->where('status', 'ACTIVE')->get()->getRow();
        if ($activeSession) {
            return $this->exists((int)$activeSession->academic_session_id, $classId, $subjectId);
        }
        return $this->where('class_id', $classId)->where('subject_id', $subjectId)->countAllResults() > 0;
    }
}
