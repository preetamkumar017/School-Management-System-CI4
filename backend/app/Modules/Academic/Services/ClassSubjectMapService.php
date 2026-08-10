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
use App\Modules\Academic\Models\AcademicSessionModel;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\ClassSubjectMapModel;
use App\Modules\Academic\Models\SubjectModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

class ClassSubjectMapService
{
    public const PERMISSION_MANAGE = 'academic.manage';

    public function __construct(
        private readonly ClassSubjectMapModel $classSubjectMapModel,
        private readonly ClassModel $classModel,
        private readonly SubjectModel $subjectModel,
        private readonly AcademicSessionModel $academicSessionModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function mapSubjectToClass(ClassSubjectMapRequest $request): ClassSubjectMapResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->academicSessionModel->find($request->academicSessionId) === null) {
            throw new BusinessRuleException('ACADEMIC_SESSION_NOT_FOUND', 'Academic session not found.');
        }

        if ($this->classModel->find($request->classId) === null) {
            throw new BusinessRuleException('CLASS_NOT_FOUND', 'Class not found.');
        }

        if ($this->subjectModel->find($request->subjectId) === null) {
            throw new BusinessRuleException('SUBJECT_NOT_FOUND', 'Subject not found.');
        }

        if ($this->classSubjectMapModel->exists($request->academicSessionId, $request->classId, $request->subjectId)) {
            throw new BusinessRuleException(
                'CLASS_SUBJECT_MAPPING_ALREADY_EXISTS',
                'This subject is already mapped to this class for the selected session.',
            );
        }

        $this->classSubjectMapModel->insertMapping(
            $request->academicSessionId,
            $request->classId,
            $request->subjectId,
            $request->isMandatory
        );

        $map = new ClassSubjectMap([
            'academic_session_id' => $request->academicSessionId,
            'class_id'            => $request->classId,
            'subject_id'          => $request->subjectId,
            'is_mandatory'        => $request->isMandatory,
        ]);

        $this->auditService->record(
            'ClassSubjectMap',
            $request->classId,
            AuditLog::ACTION_CREATE,
            null,
            $map->toRawArray(),
        );

        return new ClassSubjectMapResponse($map);
    }

    public function unmapSubjectFromClass(int $sessionId, int $classId, int $subjectId): void
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if (! $this->classSubjectMapModel->exists($sessionId, $classId, $subjectId)) {
            throw new BusinessRuleException(
                'CLASS_SUBJECT_MAPPING_NOT_FOUND',
                'This subject is not mapped to this class for the selected session.',
            );
        }

        $this->assertNoOperationalReferences($sessionId, $classId, $subjectId);

        $this->classSubjectMapModel->deleteMapping($sessionId, $classId, $subjectId);

        $map = new ClassSubjectMap([
            'academic_session_id' => $sessionId,
            'class_id'            => $classId,
            'subject_id'          => $subjectId,
        ]);

        $this->auditService->record('ClassSubjectMap', $classId, AuditLog::ACTION_DELETE, $map->toRawArray(), null);
    }

    /**
     * @return list<SubjectResponse>
     */
    public function listSubjectsForClass(int $sessionId, int $classId): array
    {
        $mappings = $this->classSubjectMapModel->findBySessionAndClass($sessionId, $classId);
        $subjects = [];

        foreach ($mappings as $map) {
            $subject = $this->subjectModel->find($map->subject_id);
            if ($subject) {
                $subjects[] = new SubjectResponse($subject);
            }
        }

        return $subjects;
    }

    /**
     * @return list<ClassSubjectMapResponse>
     */
    public function listMappingsForSessionAndClass(int $sessionId, int $classId): array
    {
        return array_map(
            static fn (ClassSubjectMap $map): ClassSubjectMapResponse => new ClassSubjectMapResponse($map),
            $this->classSubjectMapModel->findBySessionAndClass($sessionId, $classId)
        );
    }

    private function assertNoOperationalReferences(int $sessionId, int $classId, int $subjectId): void
    {
        $db = \Config\Database::connect();

        // 1. Check timetable_entries
        if ($db->tableExists('timetable_entries') && $db->tableExists('sections')) {
            $count = $db->table('timetable_entries')
                ->join('sections', 'sections.section_id = timetable_entries.section_id')
                ->where('sections.class_id', $classId)
                ->where('timetable_entries.subject_id', $subjectId)
                ->where('timetable_entries.is_deleted', 0)
                ->countAllResults();

            if ($count > 0) {
                throw new BusinessRuleException(
                    'CLASS_SUBJECT_MAPPING_HAS_ACTIVE_REFERENCES',
                    'Cannot remove mapping as it is referenced in active timetable entries for this session.'
                );
            }
        }

        // 2. Check exam_subjects / marks_records
        if ($db->tableExists('marks_records')) {
            // Check if there are marks records for this class & subject in the session
            $count = $db->table('marks_records')
                ->join('exams', 'exams.exam_id = marks_records.exam_id')
                ->where('exams.academic_session_id', $sessionId)
                ->where('exams.class_id', $classId)
                ->where('marks_records.subject_id', $subjectId)
                ->countAllResults();

            if ($count > 0) {
                throw new BusinessRuleException(
                    'CLASS_SUBJECT_MAPPING_HAS_ACTIVE_REFERENCES',
                    'Cannot remove mapping as exam results exist for this subject in the session.'
                );
            }
        }
    }
}
