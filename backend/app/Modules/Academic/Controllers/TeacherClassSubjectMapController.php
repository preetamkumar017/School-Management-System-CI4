<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateTeacherClassSubjectMapRequest;
use App\Modules\Academic\DTOs\UpdateTeacherClassSubjectMapRequest;
use Config\Services;

class TeacherClassSubjectMapController extends BaseController
{
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];
        [$sessionId, $classId, $sectionId, $subjectId, $employeeId] = $this->validateFields($body);

        $response = Services::teacherClassSubjectMapService()->assignTeacher(
            new CreateTeacherClassSubjectMapRequest($sessionId, $classId, $sectionId, $subjectId, $employeeId)
        );

        return $this->respondCreated($response->toArray());
    }

    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        [$sessionId, $classId, $sectionId, $subjectId, $employeeId] = $this->validateFields($body);

        $response = Services::teacherClassSubjectMapService()->updateAssignment(
            $id,
            new UpdateTeacherClassSubjectMapRequest($sessionId, $classId, $sectionId, $subjectId, $employeeId)
        );

        return $this->respondSuccess($response->toArray());
    }

    public function delete(int $id)
    {
        Services::teacherClassSubjectMapService()->deleteAssignment($id);
        return $this->respondSuccess(null, [], 204);
    }

    public function show(int $id)
    {
        return $this->respondSuccess(Services::teacherClassSubjectMapService()->getAssignment($id)->toArray());
    }

    public function index()
    {
        $sessionId = $this->request->getGet('academic_session_id');
        if ($sessionId !== null) {
            $responses = Services::teacherClassSubjectMapService()->listAssignmentsForSession((int) $sessionId);
        } else {
            $responses = Services::teacherClassSubjectMapService()->listAssignments();
        }
        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     */
    private function validateFields(array $body): array
    {
        $sessionId  = isset($body['academic_session_id']) ? (int) $body['academic_session_id'] : 0;
        $classId    = isset($body['class_id']) ? (int) $body['class_id'] : 0;
        $sectionId  = isset($body['section_id']) ? (int) $body['section_id'] : 0;
        $subjectId  = isset($body['subject_id']) ? (int) $body['subject_id'] : 0;
        $employeeId = isset($body['employee_id']) ? (int) $body['employee_id'] : 0;
        $fields     = [];

        if ($sessionId <= 0) {
            $fields['academic_session_id'] = 'academic_session_id is required.';
        }

        if ($classId <= 0) {
            $fields['class_id'] = 'class_id is required.';
        }

        if ($sectionId <= 0) {
            $fields['section_id'] = 'section_id is required.';
        }

        if ($subjectId <= 0) {
            $fields['subject_id'] = 'subject_id is required.';
        }

        if ($employeeId <= 0) {
            $fields['employee_id'] = 'employee_id is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$sessionId, $classId, $sectionId, $subjectId, $employeeId];
    }
}
