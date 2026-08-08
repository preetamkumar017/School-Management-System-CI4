<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\DTOs\ClassSubjectMapRequest;
use App\Modules\Academic\DTOs\ClassSubjectMapResponse;
use App\Modules\Academic\DTOs\SubjectResponse;
use App\Modules\Academic\Entities\ClassSubjectMap;
use App\Modules\Academic\Entities\Subject;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\ClassSubjectMapModel;
use App\Modules\Academic\Models\SubjectModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

/**
 * docs/design/academic/Phase-4-Service-Design.md
 * RBAC (ADR-024 §3, Phase 2): `academic.manage` (Tier 1 only) gates writes.
 */
class ClassSubjectMapService
{
    public const PERMISSION_MANAGE = 'academic.manage';

    public function __construct(
        private readonly ClassSubjectMapModel $classSubjectMapModel,
        private readonly ClassModel $classModel,
        private readonly SubjectModel $subjectModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function mapSubjectToClass(ClassSubjectMapRequest $request): ClassSubjectMapResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->classModel->find($request->classId) === null) {
            throw new BusinessRuleException('CLASS_NOT_FOUND', 'Class not found.');
        }

        if ($this->subjectModel->find($request->subjectId) === null) {
            throw new BusinessRuleException('SUBJECT_NOT_FOUND', 'Subject not found.');
        }

        if ($this->classSubjectMapModel->existsByClassIdAndSubjectId($request->classId, $request->subjectId)) {
            throw new BusinessRuleException(
                'CLASS_SUBJECT_MAPPING_ALREADY_EXISTS',
                'This subject is already mapped to this class.',
            );
        }

        $this->classSubjectMapModel->insertMapping($request->classId, $request->subjectId);

        $map = new ClassSubjectMap(['class_id' => $request->classId, 'subject_id' => $request->subjectId]);

        $this->auditService->record(
            'ClassSubjectMap',
            $request->classId,
            AuditLog::ACTION_CREATE,
            null,
            $map->toRawArray(),
        );

        return new ClassSubjectMapResponse($map);
    }

    public function unmapSubjectFromClass(int $classId, int $subjectId): void
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if (! $this->classSubjectMapModel->existsByClassIdAndSubjectId($classId, $subjectId)) {
            throw new BusinessRuleException(
                'CLASS_SUBJECT_MAPPING_NOT_FOUND',
                'This subject is not mapped to this class.',
            );
        }

        $this->classSubjectMapModel->deleteMapping($classId, $subjectId);

        $map = new ClassSubjectMap(['class_id' => $classId, 'subject_id' => $subjectId]);

        $this->auditService->record('ClassSubjectMap', $classId, AuditLog::ACTION_DELETE, $map->toRawArray(), null);
    }

    /**
     * @return list<SubjectResponse>
     */
    public function listSubjectsForClass(int $classId): array
    {
        return array_map(
            static fn (Subject $subject): SubjectResponse => new SubjectResponse($subject),
            $this->subjectModel->findByClassId($classId),
        );
    }
}
