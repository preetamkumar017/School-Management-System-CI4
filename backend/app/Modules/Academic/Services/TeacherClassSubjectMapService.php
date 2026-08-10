<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\DTOs\CreateTeacherClassSubjectMapRequest;
use App\Modules\Academic\DTOs\TeacherClassSubjectMapResponse;
use App\Modules\Academic\DTOs\UpdateTeacherClassSubjectMapRequest;
use App\Modules\Academic\Entities\TeacherClassSubjectMap;
use App\Modules\Academic\Models\AcademicSessionModel;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\SectionModel;
use App\Modules\Academic\Models\SubjectModel;
use App\Modules\Academic\Models\TeacherClassSubjectMapModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

class TeacherClassSubjectMapService
{
    public const PERMISSION_MANAGE = 'academic.manage';

    public function __construct(
        private readonly TeacherClassSubjectMapModel $teacherClassSubjectMapModel,
        private readonly ClassModel $classModel,
        private readonly SectionModel $sectionModel,
        private readonly SubjectModel $subjectModel,
        private readonly AcademicSessionModel $academicSessionModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function assignTeacher(CreateTeacherClassSubjectMapRequest $request): TeacherClassSubjectMapResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $this->validateReferences(
            $request->academicSessionId,
            $request->classId,
            $request->sectionId,
            $request->subjectId,
            $request->employeeId
        );

        if ($this->teacherClassSubjectMapModel->exists($request->academicSessionId, $request->sectionId, $request->subjectId)) {
            throw new BusinessRuleException(
                'TEACHER_ASSIGNMENT_ALREADY_EXISTS',
                'A teacher is already assigned to this section and subject for this session.',
            );
        }

        $id = $this->teacherClassSubjectMapModel->insert([
            'academic_session_id' => $request->academicSessionId,
            'class_id'            => $request->classId,
            'section_id'          => $request->sectionId,
            'subject_id'          => $request->subjectId,
            'employee_id'         => $request->employeeId,
        ], true);

        $map = $this->teacherClassSubjectMapModel->find($id);

        $this->auditService->record('TeacherClassSubjectMap', $id, AuditLog::ACTION_CREATE, null, $map->toRawArray());

        return new TeacherClassSubjectMapResponse($map);
    }

    public function updateAssignment(int $id, UpdateTeacherClassSubjectMapRequest $request): TeacherClassSubjectMapResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireAssignment($id);

        $this->validateReferences(
            $request->academicSessionId,
            $request->classId,
            $request->sectionId,
            $request->subjectId,
            $request->employeeId
        );

        if ($this->teacherClassSubjectMapModel->existsExceptId($request->academicSessionId, $request->sectionId, $request->subjectId, $id)) {
            throw new BusinessRuleException(
                'TEACHER_ASSIGNMENT_ALREADY_EXISTS',
                'A teacher is already assigned to this section and subject for this session.',
            );
        }

        $this->assertNoOperationalReferences($before, 'update');

        $this->teacherClassSubjectMapModel->update($id, [
            'academic_session_id' => $request->academicSessionId,
            'class_id'            => $request->classId,
            'section_id'          => $request->sectionId,
            'subject_id'          => $request->subjectId,
            'employee_id'         => $request->employeeId,
        ]);

        $after = $this->teacherClassSubjectMapModel->find($id);

        $this->auditService->record(
            'TeacherClassSubjectMap',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new TeacherClassSubjectMapResponse($after);
    }

    public function deleteAssignment(int $id): void
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireAssignment($id);

        $this->assertNoOperationalReferences($before, 'delete');

        $this->teacherClassSubjectMapModel->delete($id);

        $this->auditService->record('TeacherClassSubjectMap', $id, AuditLog::ACTION_DELETE, $before->toRawArray(), null);
    }

    public function getAssignment(int $id): TeacherClassSubjectMapResponse
    {
        return new TeacherClassSubjectMapResponse($this->requireAssignment($id));
    }

    /**
     * @return list<TeacherClassSubjectMapResponse>
     */
    public function listAssignments(): array
    {
        return array_map(
            static fn (TeacherClassSubjectMap $map): TeacherClassSubjectMapResponse => new TeacherClassSubjectMapResponse($map),
            $this->teacherClassSubjectMapModel->findAll(),
        );
    }

    /**
     * @return list<TeacherClassSubjectMapResponse>
     */
    public function listAssignmentsForSession(int $sessionId): array
    {
        return array_map(
            static fn (TeacherClassSubjectMap $map): TeacherClassSubjectMapResponse => new TeacherClassSubjectMapResponse($map),
            $this->teacherClassSubjectMapModel->where('academic_session_id', $sessionId)->findAll(),
        );
    }

    private function requireAssignment(int $id): TeacherClassSubjectMap
    {
        $map = $this->teacherClassSubjectMapModel->find($id);

        if ($map === null) {
            throw new BusinessRuleException('TEACHER_ASSIGNMENT_NOT_FOUND', 'Teacher class subject assignment not found.');
        }

        return $map;
    }

    private function validateReferences(int $sessionId, int $classId, int $sectionId, int $subjectId, int $employeeId): void
    {
        if ($this->academicSessionModel->find($sessionId) === null) {
            throw new BusinessRuleException('ACADEMIC_SESSION_NOT_FOUND', 'Academic session not found.');
        }

        if ($this->classModel->find($classId) === null) {
            throw new BusinessRuleException('CLASS_NOT_FOUND', 'Class not found.');
        }

        $section = $this->sectionModel->find($sectionId);
        if ($section === null || (int)$section->class_id !== $classId) {
            throw new BusinessRuleException('SECTION_NOT_FOUND', 'Section not found or does not belong to the selected class.');
        }

        if ($this->subjectModel->find($subjectId) === null) {
            throw new BusinessRuleException('SUBJECT_NOT_FOUND', 'Subject not found.');
        }

        // Validate teacher exists in employees table
        $db = \Config\Database::connect();
        if ($db->tableExists('employees')) {
            $teacher = $db->table('employees')
                ->where('employee_id', $employeeId)
                ->where('is_deleted', 0)
                ->get()
                ->getRow();

            if ($teacher === null) {
                throw new BusinessRuleException('TEACHER_NOT_FOUND', 'Eligible teacher not found.');
            }
        }
    }

    private function assertNoOperationalReferences(TeacherClassSubjectMap $map, string $action): void
    {
        $db = \Config\Database::connect();

        // 1. Check timetable_entries
        if ($db->tableExists('timetable_entries')) {
            $count = $db->table('timetable_entries')
                ->where('section_id', $map->section_id)
                ->where('subject_id', $map->subject_id)
                ->where('employee_id', $map->employee_id)
                ->where('is_deleted', 0)
                ->countAllResults();

            if ($count > 0) {
                throw new BusinessRuleException(
                    'TEACHER_ASSIGNMENT_HAS_ACTIVE_REFERENCES',
                    "Cannot {$action} assignment as it is referenced in active timetable schedules."
                );
            }
        }

        // 2. Check attendance_records (if the teacher marked attendance)
        if ($db->tableExists('attendance_records') && $db->tableExists('timetable_entries')) {
            $count = $db->table('attendance_records')
                ->join('timetable_entries', 'timetable_entries.timetable_entry_id = attendance_records.timetable_entry_id')
                ->where('timetable_entries.section_id', $map->section_id)
                ->where('timetable_entries.subject_id', $map->subject_id)
                ->where('timetable_entries.employee_id', $map->employee_id)
                ->countAllResults();

            if ($count > 0) {
                throw new BusinessRuleException(
                    'TEACHER_ASSIGNMENT_HAS_ACTIVE_REFERENCES',
                    "Cannot {$action} assignment as attendance has already been recorded in this section."
                );
            }
        }
    }
}
