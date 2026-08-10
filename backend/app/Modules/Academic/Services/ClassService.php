<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\DTOs\ClassResponse;
use App\Modules\Academic\DTOs\CreateClassRequest;
use App\Modules\Academic\DTOs\UpdateClassRequest;
use App\Modules\Academic\Entities\AcademicClass;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

class ClassService
{
    public const PERMISSION_MANAGE = 'academic.manage';

    public function __construct(
        private readonly ClassModel $classModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createClass(CreateClassRequest $request): ClassResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->classModel->existsByClassName($request->className)) {
            throw new BusinessRuleException('CLASS_NAME_ALREADY_TAKEN', 'This class name is already taken.');
        }

        if ($this->classModel->existsBySequenceOrder($request->sequenceOrder)) {
            throw new BusinessRuleException(
                'CLASS_SEQUENCE_ORDER_ALREADY_TAKEN',
                'This sequence order is already taken.',
            );
        }

        $id = $this->classModel->insert([
            'class_name'     => $request->className,
            'sequence_order' => $request->sequenceOrder,
        ], true);

        $class = $this->classModel->find($id);

        $this->auditService->record('Class', $id, AuditLog::ACTION_CREATE, null, $class->toRawArray());

        return new ClassResponse($class);
    }

    public function updateClass(int $id, UpdateClassRequest $request): ClassResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireClass($id);

        if ($this->classModel->existsByClassNameExceptId($request->className, $id)) {
            throw new BusinessRuleException('CLASS_NAME_ALREADY_TAKEN', 'This class name is already taken.');
        }

        if ($this->classModel->existsBySequenceOrderExceptId($request->sequenceOrder, $id)) {
            throw new BusinessRuleException(
                'CLASS_SEQUENCE_ORDER_ALREADY_TAKEN',
                'This sequence order is already taken.',
            );
        }

        $this->classModel->update($id, [
            'class_name'     => $request->className,
            'sequence_order' => $request->sequenceOrder,
        ]);

        $after = $this->classModel->find($id);

        $this->auditService->record('Class', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new ClassResponse($after);
    }

    public function deleteClass(int $id): void
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireClass($id);

        $this->assertNoOperationalReferences($id);

        $this->classModel->delete($id);

        $this->auditService->record('Class', $id, AuditLog::ACTION_DELETE, $before->toRawArray(), null);
    }

    public function getClass(int $id): ClassResponse
    {
        return new ClassResponse($this->requireClass($id));
    }

    /**
     * @return list<ClassResponse>
     */
    public function listClasses(): array
    {
        return array_map(
            static fn (AcademicClass $class): ClassResponse => new ClassResponse($class),
            $this->classModel->findAllOrderedBySequence(),
        );
    }

    private function requireClass(int $id): AcademicClass
    {
        $class = $this->classModel->find($id);

        if ($class === null) {
            throw new BusinessRuleException('CLASS_NOT_FOUND', 'Class not found.');
        }

        return $class;
    }

    private function assertNoOperationalReferences(int $id): void
    {
        $db = \Config\Database::connect();

        // 1. Check sections
        if ($db->tableExists('sections')) {
            $count = $db->table('sections')->where('class_id', $id)->where('is_deleted', 0)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'CLASS_HAS_ACTIVE_REFERENCES',
                    'Cannot delete class as it contains active sections.'
                );
            }
        }

        // 2. Check class_subject_map
        if ($db->tableExists('class_subject_map')) {
            $count = $db->table('class_subject_map')->where('class_id', $id)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'CLASS_HAS_ACTIVE_REFERENCES',
                    'Cannot delete class as it is mapped to subjects.'
                );
            }
        }

        // 3. Check student enrollments
        if ($db->tableExists('student_enrollments')) {
            $count = $db->table('student_enrollments')->where('class_id', $id)->where('is_deleted', 0)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'CLASS_HAS_ACTIVE_REFERENCES',
                    'Cannot delete class as it has active student enrollments.'
                );
            }
        }

        // 4. Check exams
        if ($db->tableExists('exams')) {
            $count = $db->table('exams')->where('class_id', $id)->where('is_deleted', 0)->countAllResults();
            if ($count > 0) {
                throw new BusinessRuleException(
                    'CLASS_HAS_ACTIVE_REFERENCES',
                    'Cannot delete class as it has associated exams.'
                );
            }
        }
    }
}
