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

        if ($request->capacity <= 0) {
            throw new BusinessRuleException('INVALID_SECTION_CAPACITY', 'Section capacity must be greater than zero.');
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

        if ($request->capacity <= 0) {
            throw new BusinessRuleException('INVALID_SECTION_CAPACITY', 'Section capacity must be greater than zero.');
        }

        // Check if capacity is reduced below current enrolled count
        $db = \Config\Database::connect();
        if ($db->tableExists('student_enrollments')) {
            $enrolledCount = $db->table('student_enrollments')
                ->where('section_id', $id)
                ->where('status', 'ACTIVE')
                ->countAllResults();

            if ($request->capacity < $enrolledCount) {
                throw new BusinessRuleException(
                    'CAPACITY_REDUCTION_BLOCKED',
                    "Cannot reduce section capacity below current enrolled student count ({$enrolledCount})."
                );
            }
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

    public function deleteSection(int $id): void
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireSection($id);

        $this->assertNoOperationalReferences($id);

        $this->sectionModel->delete($id);

        $this->auditService->record('Section', $id, AuditLog::ACTION_DELETE, $before->toRawArray(), null);
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

    private function assertNoOperationalReferences(int $id): void
    {
        $db = \Config\Database::connect();

        // 1. Check student enrollments
        if ($db->tableExists('student_enrollments')) {
            $count = $db->table('student_enrollments')->where('section_id', $id)->where('status', 'ACTIVE')->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'SECTION_HAS_ACTIVE_REFERENCES',
                    'Cannot delete section as it has active student enrollments.'
                );
            }
        }

        // 2. Check timetable entries
        if ($db->tableExists('timetable_entries')) {
            $count = $db->table('timetable_entries')->where('section_id', $id)->where('is_deleted', 0)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'SECTION_HAS_ACTIVE_REFERENCES',
                    'Cannot delete section as it has active timetable entries.'
                );
            }
        }

        // 3. Check attendance records
        if ($db->tableExists('attendance_records')) {
            $count = $db->table('attendance_records')->where('section_id', $id)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'SECTION_HAS_ACTIVE_REFERENCES',
                    'Cannot delete section as it has associated attendance records.'
                );
            }
        }

        // 4. Check teacher assignments
        if ($db->tableExists('teacher_class_subject_map')) {
            $count = $db->table('teacher_class_subject_map')->where('section_id', $id)->where('is_deleted', 0)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'SECTION_HAS_ACTIVE_REFERENCES',
                    'Cannot delete section as it has teacher assignments.'
                );
            }
        }
    }
}
