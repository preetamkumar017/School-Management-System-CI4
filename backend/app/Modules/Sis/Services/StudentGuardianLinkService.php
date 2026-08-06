<?php

declare(strict_types=1);

namespace App\Modules\Sis\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Sis\DTOs\StudentGuardianLinkRequest;
use App\Modules\Sis\DTOs\StudentGuardianLinkResponse;
use App\Modules\Sis\Entities\StudentGuardianLink;
use App\Modules\Sis\Mappers\StudentGuardianLinkMapper;
use App\Modules\Sis\Models\GuardianModel;
use App\Modules\Sis\Models\StudentGuardianLinkModel;
use App\Modules\Sis\Models\StudentModel;

/**
 * docs/design/sis/Phase-4.6-Service-Design.md
 */
class StudentGuardianLinkService
{
    public function __construct(
        private readonly StudentGuardianLinkModel $studentGuardianLinkModel,
        private readonly StudentModel $studentModel,
        private readonly GuardianModel $guardianModel,
        private readonly StudentGuardianLinkMapper $mapper,
        private readonly AuditService $auditService,
    ) {
    }

    public function linkGuardian(StudentGuardianLinkRequest $request): StudentGuardianLinkResponse
    {
        if ($this->studentModel->find($request->studentId) === null) {
            throw new BusinessRuleException('STUDENT_NOT_FOUND', 'Student not found.');
        }

        if ($this->guardianModel->find($request->guardianId) === null) {
            throw new BusinessRuleException('GUARDIAN_NOT_FOUND', 'Guardian not found.');
        }

        if ($this->studentGuardianLinkModel->existsByStudentIdAndGuardianId($request->studentId, $request->guardianId)) {
            throw new BusinessRuleException(
                'STUDENT_GUARDIAN_LINK_ALREADY_EXISTS',
                'This guardian is already linked to this student.',
            );
        }

        $this->studentGuardianLinkModel->insertLink($request->studentId, $request->guardianId, $request->isPrimaryContact);

        $link = new StudentGuardianLink([
            'student_id'         => $request->studentId,
            'guardian_id'        => $request->guardianId,
            'is_primary_contact' => $request->isPrimaryContact,
        ]);

        $this->auditService->record(
            'StudentGuardianLink',
            $request->studentId,
            AuditLog::ACTION_CREATE,
            null,
            $link->toRawArray(),
        );

        return $this->mapper->toResponse($link);
    }

    public function unlinkGuardian(int $studentId, int $guardianId): void
    {
        if (! $this->studentGuardianLinkModel->existsByStudentIdAndGuardianId($studentId, $guardianId)) {
            throw new BusinessRuleException(
                'STUDENT_GUARDIAN_LINK_NOT_FOUND',
                'This guardian is not linked to this student.',
            );
        }

        $this->studentGuardianLinkModel->deleteLink($studentId, $guardianId);

        $link = new StudentGuardianLink(['student_id' => $studentId, 'guardian_id' => $guardianId]);

        $this->auditService->record(
            'StudentGuardianLink',
            $studentId,
            AuditLog::ACTION_DELETE,
            $link->toRawArray(),
            null,
        );
    }

    /**
     * @return list<StudentGuardianLinkResponse>
     */
    public function listGuardiansForStudent(int $studentId): array
    {
        return array_map(
            fn (StudentGuardianLink $link): StudentGuardianLinkResponse => $this->mapper->toResponse($link),
            $this->studentGuardianLinkModel->findByStudentId($studentId),
        );
    }
}
