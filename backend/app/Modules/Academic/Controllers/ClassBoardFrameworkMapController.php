<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateClassBoardFrameworkMapRequest;
use App\Modules\Academic\DTOs\UpdateClassBoardFrameworkMapRequest;
use Config\Services;

class ClassBoardFrameworkMapController extends BaseController
{
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];
        [$sessionId, $classId, $frameworkId] = $this->validateFields($body);

        $response = Services::classBoardFrameworkMapService()->mapClassToBoard(
            new CreateClassBoardFrameworkMapRequest($sessionId, $classId, $frameworkId)
        );

        return $this->respondCreated($response->toArray());
    }

    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        [$sessionId, $classId, $frameworkId] = $this->validateFields($body);

        $response = Services::classBoardFrameworkMapService()->updateMapping(
            $id,
            new UpdateClassBoardFrameworkMapRequest($sessionId, $classId, $frameworkId)
        );

        return $this->respondSuccess($response->toArray());
    }

    public function delete(int $id)
    {
        Services::classBoardFrameworkMapService()->deleteMapping($id);
        return $this->respondSuccess(null, [], 204);
    }

    public function show(int $id)
    {
        return $this->respondSuccess(Services::classBoardFrameworkMapService()->getMapping($id)->toArray());
    }

    public function index()
    {
        $sessionId = $this->request->getGet('academic_session_id');
        if ($sessionId !== null) {
            $responses = Services::classBoardFrameworkMapService()->listMappingsForSession((int) $sessionId);
        } else {
            $responses = Services::classBoardFrameworkMapService()->listMappings();
        }
        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function validateFields(array $body): array
    {
        $sessionId   = isset($body['academic_session_id']) ? (int) $body['academic_session_id'] : 0;
        $classId     = isset($body['class_id']) ? (int) $body['class_id'] : 0;
        $frameworkId = isset($body['framework_id']) ? (int) $body['framework_id'] : 0;
        $fields      = [];

        if ($sessionId <= 0) {
            $fields['academic_session_id'] = 'academic_session_id is required.';
        }

        if ($classId <= 0) {
            $fields['class_id'] = 'class_id is required.';
        }

        if ($frameworkId <= 0) {
            $fields['framework_id'] = 'framework_id is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$sessionId, $classId, $frameworkId];
    }
}
