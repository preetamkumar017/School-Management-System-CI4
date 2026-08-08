<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\DTOs\CreateSectionRequest;
use App\Modules\Academic\DTOs\SectionResponse;
use App\Modules\Academic\DTOs\UpdateSectionRequest;
use App\Modules\Academic\Entities\Section;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\SectionModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

/**
 * docs/design/academic/Phase-4-Service-Design.md
 * getSection is the method Admission/SIS call (via this Service, never
 * SectionModel directly) to validate a section_id and read its capacity
 * during their own orchestration (DG-SIS-001, resolved by ADR-004) — that
 * cross-module call is a foreign-key/capacity check, not a user-facing
 * Academic read, so it stays ungated (ADR-024 Phase 1 Addendum's
 * "existence/count helper" precedent).
 *
 * RBAC (ADR-024 §3, Phase 2): `academic.manage` (Tier 1 only) gates writes.
 */
class SectionService
{
    public const PERMISSION_MANAGE = 'academic.manage';

    public function __construct(
        private readonly SectionModel $sectionModel,
        private readonly ClassModel $classModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createSection(CreateSectionRequest $request): SectionResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->classModel->find($request->classId) === null) {
            throw new BusinessRuleException('CLASS_NOT_FOUND', 'Class not found.');
        }

        if ($this->sectionModel->existsByClassIdAndSectionName($request->classId, $request->sectionName)) {
            throw new BusinessRuleException(
                'SECTION_NAME_ALREADY_TAKEN_IN_CLASS',
                'This section name is already taken within this class.',
            );
        }

        $id = $this->sectionModel->insert([
            'class_id'     => $request->classId,
            'section_name' => $request->sectionName,
            'capacity'     => $request->capacity,
        ], true);

        $section = $this->sectionModel->find($id);

        $this->auditService->record('Section', $id, AuditLog::ACTION_CREATE, null, $section->toRawArray());

        return new SectionResponse($section);
    }

    public function updateSection(int $id, UpdateSectionRequest $request): SectionResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireSection($id);

        if ($this->sectionModel->existsByClassIdAndSectionNameExceptId($before->class_id, $request->sectionName, $id)) {
            throw new BusinessRuleException(
                'SECTION_NAME_ALREADY_TAKEN_IN_CLASS',
                'This section name is already taken within this class.',
            );
        }

        $this->sectionModel->update($id, [
            'section_name' => $request->sectionName,
            'capacity'     => $request->capacity,
        ]);

        $after = $this->sectionModel->find($id);

        $this->auditService->record(
            'Section',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new SectionResponse($after);
    }

    public function getSection(int $id): SectionResponse
    {
        return new SectionResponse($this->requireSection($id));
    }

    /**
     * @return list<SectionResponse>
     */
    public function listSectionsByClass(int $classId): array
    {
        return array_map(
            static fn (Section $section): SectionResponse => new SectionResponse($section),
            $this->sectionModel->findByClassId($classId),
        );
    }

    private function requireSection(int $id): Section
    {
        $section = $this->sectionModel->find($id);

        if ($section === null) {
            throw new BusinessRuleException('SECTION_NOT_FOUND', 'Section not found.');
        }

        return $section;
    }
}
