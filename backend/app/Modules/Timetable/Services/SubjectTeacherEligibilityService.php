<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Timetable\DTOs\CreateSubjectTeacherEligibilityRequest;
use App\Modules\Timetable\DTOs\SubjectTeacherEligibilityResponse;
use App\Modules\Timetable\Models\SubjectTeacherEligibilityModel;
use Config\Services as AppServices;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md (ADR-013 §2).
 * Minimal admin-managed reference data — create + read only, no
 * update/delete endpoint, matching ADR-009 §13's precedent against
 * speculative CRUD beyond what a feature actually needs.
 */
class SubjectTeacherEligibilityService
{
    public function __construct(
        private readonly SubjectTeacherEligibilityModel $subjectTeacherEligibilityModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createEligibility(CreateSubjectTeacherEligibilityRequest $request): SubjectTeacherEligibilityResponse
    {
        AppServices::employeeService()->getEmployee($request->employeeId);
        AppServices::subjectService()->getSubject($request->subjectId);

        if ($this->subjectTeacherEligibilityModel->existsByEmployeeSubject($request->employeeId, $request->subjectId)) {
            throw new BusinessRuleException(
                'SUBJECT_TEACHER_ELIGIBILITY_ALREADY_EXISTS',
                'This employee is already recorded as eligible for this subject.',
            );
        }

        $id = $this->subjectTeacherEligibilityModel->insert([
            'employee_id' => $request->employeeId,
            'subject_id'  => $request->subjectId,
        ], true);

        $eligibility = $this->subjectTeacherEligibilityModel->find($id);

        $this->auditService->record('SubjectTeacherEligibility', $id, AuditLog::ACTION_CREATE, null, $eligibility->toRawArray());

        return new SubjectTeacherEligibilityResponse($eligibility);
    }

    /**
     * @return list<SubjectTeacherEligibilityResponse>
     */
    public function listBySubject(int $subjectId): array
    {
        return array_map(
            static fn ($row) => new SubjectTeacherEligibilityResponse($row),
            $this->subjectTeacherEligibilityModel->where('subject_id', $subjectId)->findAll(),
        );
    }
}
