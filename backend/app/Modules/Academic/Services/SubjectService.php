<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\DTOs\CreateSubjectRequest;
use App\Modules\Academic\DTOs\SubjectResponse;
use App\Modules\Academic\DTOs\UpdateSubjectRequest;
use App\Modules\Academic\Entities\Subject;
use App\Modules\Academic\Models\SubjectCategoryModel;
use App\Modules\Academic\Models\SubjectModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

class SubjectService
{
    public const PERMISSION_MANAGE = 'academic.manage';

    public function __construct(
        private readonly SubjectModel $subjectModel,
        private readonly SubjectCategoryModel $subjectCategoryModel,
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

        if ($request->subjectCategoryId !== null) {
            $category = $this->subjectCategoryModel->find($request->subjectCategoryId);
            if ($category === null || (int)$category->is_active === 0) {
                throw new BusinessRuleException('INVALID_SUBJECT_CATEGORY', 'Subject category is invalid or inactive.');
            }
        }

        $id = $this->subjectModel->insert([
            'subject_name'         => $request->subjectName,
            'subject_code'         => $request->subjectCode,
            'subject_category_id'  => $request->subjectCategoryId,
            'is_language_subject'  => $request->isLanguageSubject,
            'stream_applicability' => $request->streamApplicability,
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

        if ($request->subjectCategoryId !== null) {
            $category = $this->subjectCategoryModel->find($request->subjectCategoryId);
            if ($category === null || (int)$category->is_active === 0) {
                throw new BusinessRuleException('INVALID_SUBJECT_CATEGORY', 'Subject category is invalid or inactive.');
            }
        }

        // Operational reference checks for critical modifications (like code changes)
        if ($before->subject_code !== $request->subjectCode) {
            $this->assertNoOperationalReferences($id, 'change the subject code of');
        }

        $this->subjectModel->update($id, [
            'subject_name'         => $request->subjectName,
            'subject_code'         => $request->subjectCode,
            'subject_category_id'  => $request->subjectCategoryId,
            'is_language_subject'  => $request->isLanguageSubject,
            'stream_applicability' => $request->streamApplicability,
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

    public function deleteSubject(int $id): void
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireSubject($id);

        $this->assertNoOperationalReferences($id, 'delete');

        $this->subjectModel->delete($id);

        $this->auditService->record('Subject', $id, AuditLog::ACTION_DELETE, $before->toRawArray(), null);
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

    private function assertNoOperationalReferences(int $id, string $action): void
    {
        $db = \Config\Database::connect();

        // 1. Check class_subject_map references
        if ($db->tableExists('class_subject_map')) {
            $count = $db->table('class_subject_map')->where('subject_id', $id)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'SUBJECT_HAS_ACTIVE_REFERENCES',
                    "Cannot {$action} this subject as it is referenced in Class-Subject mappings."
                );
            }
        }

        // 2. Check marks_records references
        if ($db->tableExists('marks_records')) {
            $count = $db->table('marks_records')->where('subject_id', $id)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'SUBJECT_HAS_ACTIVE_REFERENCES',
                    "Cannot {$action} this subject as it has associated marks records."
                );
            }
        }

        // 3. Check teacher_class_subject_map references
        if ($db->tableExists('teacher_class_subject_map')) {
            $count = $db->table('teacher_class_subject_map')->where('subject_id', $id)->where('is_deleted', 0)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'SUBJECT_HAS_ACTIVE_REFERENCES',
                    "Cannot {$action} this subject as it is assigned to a teacher."
                );
            }
        }

        // 4. Check timetable_entries references
        if ($db->tableExists('timetable_entries')) {
            $count = $db->table('timetable_entries')->where('subject_id', $id)->where('is_deleted', 0)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'SUBJECT_HAS_ACTIVE_REFERENCES',
                    "Cannot {$action} this subject as it is referenced in timetable entries."
                );
            }
        }
    }
}
