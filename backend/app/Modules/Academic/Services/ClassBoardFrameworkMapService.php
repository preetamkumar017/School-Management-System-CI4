<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\DTOs\ClassBoardFrameworkMapResponse;
use App\Modules\Academic\DTOs\CreateClassBoardFrameworkMapRequest;
use App\Modules\Academic\DTOs\UpdateClassBoardFrameworkMapRequest;
use App\Modules\Academic\Entities\ClassBoardFrameworkMap;
use App\Modules\Academic\Models\AcademicSessionModel;
use App\Modules\Academic\Models\ClassBoardFrameworkMapModel;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

class ClassBoardFrameworkMapService
{
    public const PERMISSION_MANAGE = 'academic.manage';

    public function __construct(
        private readonly ClassBoardFrameworkMapModel $classBoardFrameworkMapModel,
        private readonly ClassModel $classModel,
        private readonly AcademicSessionModel $academicSessionModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function mapClassToBoard(CreateClassBoardFrameworkMapRequest $request): ClassBoardFrameworkMapResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->academicSessionModel->find($request->academicSessionId) === null) {
            throw new BusinessRuleException('ACADEMIC_SESSION_NOT_FOUND', 'Academic session not found.');
        }

        if ($this->classModel->find($request->classId) === null) {
            throw new BusinessRuleException('CLASS_NOT_FOUND', 'Class not found.');
        }

        $this->assertFrameworkExists($request->frameworkId);

        if ($this->classBoardFrameworkMapModel->exists($request->academicSessionId, $request->classId)) {
            throw new BusinessRuleException(
                'CLASS_BOARD_MAPPING_ALREADY_EXISTS',
                'This class is already mapped to a board framework for this session.',
            );
        }

        $id = $this->classBoardFrameworkMapModel->insert([
            'academic_session_id' => $request->academicSessionId,
            'class_id'            => $request->classId,
            'framework_id'        => $request->frameworkId,
        ], true);

        $map = $this->classBoardFrameworkMapModel->find($id);

        $this->auditService->record('ClassBoardFrameworkMap', $id, AuditLog::ACTION_CREATE, null, $map->toRawArray());

        return new ClassBoardFrameworkMapResponse($map);
    }

    public function updateMapping(int $id, UpdateClassBoardFrameworkMapRequest $request): ClassBoardFrameworkMapResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireMapping($id);

        if ($this->classBoardFrameworkMapModel->existsExceptId($request->academicSessionId, $request->classId, $id)) {
            throw new BusinessRuleException(
                'CLASS_BOARD_MAPPING_ALREADY_EXISTS',
                'This class is already mapped to a board framework for this session.',
            );
        }

        $this->assertFrameworkExists($request->frameworkId);
        $this->assertNoExamReferences($before->academic_session_id, $before->class_id, 'update');

        $this->classBoardFrameworkMapModel->update($id, [
            'academic_session_id' => $request->academicSessionId,
            'class_id'            => $request->classId,
            'framework_id'        => $request->frameworkId,
        ]);

        $after = $this->classBoardFrameworkMapModel->find($id);

        $this->auditService->record(
            'ClassBoardFrameworkMap',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new ClassBoardFrameworkMapResponse($after);
    }

    public function deleteMapping(int $id): void
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireMapping($id);

        $this->assertNoExamReferences($before->academic_session_id, $before->class_id, 'delete');

        $this->classBoardFrameworkMapModel->delete($id);

        $this->auditService->record('ClassBoardFrameworkMap', $id, AuditLog::ACTION_DELETE, $before->toRawArray(), null);
    }

    public function getMapping(int $id): ClassBoardFrameworkMapResponse
    {
        return new ClassBoardFrameworkMapResponse($this->requireMapping($id));
    }

    /**
     * @return list<ClassBoardFrameworkMapResponse>
     */
    public function listMappings(): array
    {
        return array_map(
            static fn (ClassBoardFrameworkMap $map): ClassBoardFrameworkMapResponse => new ClassBoardFrameworkMapResponse($map),
            $this->classBoardFrameworkMapModel->findAll(),
        );
    }

    /**
     * @return list<ClassBoardFrameworkMapResponse>
     */
    public function listMappingsForSession(int $sessionId): array
    {
        return array_map(
            static fn (ClassBoardFrameworkMap $map): ClassBoardFrameworkMapResponse => new ClassBoardFrameworkMapResponse($map),
            $this->classBoardFrameworkMapModel->where('academic_session_id', $sessionId)->findAll(),
        );
    }

    private function requireMapping(int $id): ClassBoardFrameworkMap
    {
        $map = $this->classBoardFrameworkMapModel->find($id);

        if ($map === null) {
            throw new BusinessRuleException('CLASS_BOARD_MAPPING_NOT_FOUND', 'Class-board framework mapping not found.');
        }

        return $map;
    }

    private function assertFrameworkExists(int $frameworkId): void
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('academic_frameworks')) {
            $count = $db->table('academic_frameworks')->where('framework_id', $frameworkId)->countAllResults();
            if ($count === 0) {
                throw new BusinessRuleException('FRAMEWORK_NOT_FOUND', 'Academic framework not found.');
            }
        }
    }

    private function assertNoExamReferences(int $sessionId, int $classId, string $action): void
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('exams')) {
            $count = $db->table('exams')
                ->where('academic_session_id', $sessionId)
                ->where('class_id', $classId)
                ->where('is_deleted', 0)
                ->countAllResults();

            if ($count > 0) {
                throw new BusinessRuleException(
                    'CLASS_BOARD_MAPPING_IMMUTABLE',
                    "Cannot {$action} class-board mapping as examinations exist for this class in this session."
                );
            }
        }
    }
}
