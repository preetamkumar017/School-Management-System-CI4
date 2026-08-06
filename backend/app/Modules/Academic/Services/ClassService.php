<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\DTOs\ClassResponse;
use App\Modules\Academic\DTOs\CreateClassRequest;
use App\Modules\Academic\DTOs\UpdateClassRequest;
use App\Modules\Academic\Entities\AcademicClass;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

/**
 * docs/design/academic/Phase-4-Service-Design.md
 * No delete operation is exposed — a Class is "never hard-deleted... only
 * deactivated" (Phase 1); the only removal path is the standard
 * soft-delete, not modeled here since no approved document routes an API
 * call to it yet.
 */
class ClassService
{
    public function __construct(
        private readonly ClassModel $classModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createClass(CreateClassRequest $request): ClassResponse
    {
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
}
