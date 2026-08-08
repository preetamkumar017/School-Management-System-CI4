<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\DTOs\CreateSubjectRequest;
use App\Modules\Academic\DTOs\SubjectResponse;
use App\Modules\Academic\DTOs\UpdateSubjectRequest;
use App\Modules\Academic\Entities\Subject;
use App\Modules\Academic\Models\SubjectModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

/**
 * docs/design/academic/Phase-4-Service-Design.md
 * RBAC (ADR-024 §3, Phase 2): `academic.manage` (Tier 1 only) gates writes.
 */
class SubjectService
{
    public const PERMISSION_MANAGE = 'academic.manage';

    public function __construct(
        private readonly SubjectModel $subjectModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createSubject(CreateSubjectRequest $request): SubjectResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->subjectModel->existsBySubjectCode($request->subjectCode)) {
            throw new BusinessRuleException('SUBJECT_CODE_ALREADY_TAKEN', 'This subject code is already taken.');
        }

        $id = $this->subjectModel->insert([
            'subject_name' => $request->subjectName,
            'subject_code' => $request->subjectCode,
        ], true);

        $subject = $this->subjectModel->find($id);

        $this->auditService->record('Subject', $id, AuditLog::ACTION_CREATE, null, $subject->toRawArray());

        return new SubjectResponse($subject);
    }

    public function updateSubject(int $id, UpdateSubjectRequest $request): SubjectResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireSubject($id);

        if ($this->subjectModel->existsBySubjectCodeExceptId($request->subjectCode, $id)) {
            throw new BusinessRuleException('SUBJECT_CODE_ALREADY_TAKEN', 'This subject code is already taken.');
        }

        $this->subjectModel->update($id, [
            'subject_name' => $request->subjectName,
            'subject_code' => $request->subjectCode,
        ]);

        $after = $this->subjectModel->find($id);

        $this->auditService->record(
            'Subject',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new SubjectResponse($after);
    }

    public function getSubject(int $id): SubjectResponse
    {
        return new SubjectResponse($this->requireSubject($id));
    }

    /**
     * @return list<SubjectResponse>
     */
    public function listSubjects(): array
    {
        return array_map(
            static fn (Subject $subject): SubjectResponse => new SubjectResponse($subject),
            $this->subjectModel->findAll(),
        );
    }

    private function requireSubject(int $id): Subject
    {
        $subject = $this->subjectModel->find($id);

        if ($subject === null) {
            throw new BusinessRuleException('SUBJECT_NOT_FOUND', 'Subject not found.');
        }

        return $subject;
    }
}
