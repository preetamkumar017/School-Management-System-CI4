<?php

declare(strict_types=1);

namespace App\Modules\Sis\Models;

use App\Modules\Sis\Entities\StudentGuardianLink;
use CodeIgniter\Model;

/**
 * docs/design/sis/Phase-4.3-Repository-Design.md
 * Composite key (student_id, guardian_id) — same reasoning as Academic's
 * ClassSubjectMapModel: no surrogate PK, so this never calls
 * find($id)/delete($id); every read/write goes through the explicit
 * composite-key methods below.
 */
class StudentGuardianLinkModel extends Model
{
    protected $table         = 'student_guardian_link';
    protected $primaryKey    = 'student_id';
    protected $returnType    = StudentGuardianLink::class;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'student_id',
        'guardian_id',
        'is_primary_contact',
    ];

    public function existsByStudentIdAndGuardianId(int $studentId, int $guardianId): bool
    {
        return $this->where('student_id', $studentId)->where('guardian_id', $guardianId)->countAllResults() > 0;
    }

    /**
     * Input to the Service layer's BR-SIS-006 ACTIVE-transition gate — a
     * student must have at least one linked guardian.
     */
    public function existsByStudentId(int $studentId): bool
    {
        return $this->where('student_id', $studentId)->countAllResults() > 0;
    }

    public function insertLink(int $studentId, int $guardianId, bool $isPrimaryContact): void
    {
        $this->insert([
            'student_id'         => $studentId,
            'guardian_id'        => $guardianId,
            'is_primary_contact' => $isPrimaryContact,
        ]);
    }

    public function deleteLink(int $studentId, int $guardianId): void
    {
        $this->where('student_id', $studentId)->where('guardian_id', $guardianId)->delete();
    }

    /**
     * @return list<StudentGuardianLink>
     */
    public function findByStudentId(int $studentId): array
    {
        return $this->where('student_id', $studentId)->findAll();
    }

    /**
     * @return list<StudentGuardianLink>
     */
    public function findByGuardianId(int $guardianId): array
    {
        return $this->where('guardian_id', $guardianId)->findAll();
    }
}
