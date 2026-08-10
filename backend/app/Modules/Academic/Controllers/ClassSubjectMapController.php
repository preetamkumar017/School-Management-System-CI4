<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\ClassSubjectMapRequest;
use Config\Services;

class ClassSubjectMapController extends BaseController
{
    public function create()
    {
        $body        = $this->request->getJSON(true) ?? [];
        $sessionId   = (int) ($body['academic_session_id'] ?? 0);
        $classId     = (int) ($body['class_id'] ?? 0);
        $subjectId   = (int) ($body['subject_id'] ?? 0);
        $isMandatory = isset($body['is_mandatory']) ? (int) $body['is_mandatory'] : 1;

        $fields = [];

        if ($sessionId <= 0) {
            $fields['academic_session_id'] = 'academic_session_id is required.';
        }

        if ($classId <= 0) {
            $fields['class_id'] = 'class_id is required.';
        }

        if ($subjectId <= 0) {
            $fields['subject_id'] = 'subject_id is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::classSubjectMapService()->mapSubjectToClass(
            new ClassSubjectMapRequest($sessionId, $classId, $subjectId, $isMandatory),
        );

        return $this->respondCreated($response->toArray());
    }

    public function delete(int $sessionId, int $classId, int $subjectId)
    {
        Services::classSubjectMapService()->unmapSubjectFromClass($sessionId, $classId, $subjectId);
        return $this->respondSuccess();
    }

    public function byClass(int $sessionId, int $classId)
    {
        $responses = Services::classSubjectMapService()->listSubjectsForClass($sessionId, $classId);
        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    public function listMappings(int $sessionId, int $classId)
    {
        $responses = Services::classSubjectMapService()->listMappingsForSessionAndClass($sessionId, $classId);
        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
