<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Timetable\DTOs\CreateSubjectTeacherEligibilityRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md (ADR-013 §2)
 * Base path /api/v1/timetable/subject-teacher-eligibilities
 */
#[OA\Tag(name: 'Subject-Teacher Eligibility')]
class SubjectTeacherEligibilityController extends BaseController
{
    #[OA\Post(
        path: '/timetable/subject-teacher-eligibilities',
        tags: ['Subject-Teacher Eligibility'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SubjectTeacherEligibilityRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/SubjectTeacherEligibilityResponse')),
            new OA\Response(response: 422, description: 'SUBJECT_TEACHER_ELIGIBILITY_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body       = $this->request->getJSON(true) ?? [];
        $employeeId = (int) ($body['employee_id'] ?? 0);
        $subjectId  = (int) ($body['subject_id'] ?? 0);

        $fields = [];

        if ($employeeId <= 0) {
            $fields['employee_id'] = 'employee_id is required.';
        }

        if ($subjectId <= 0) {
            $fields['subject_id'] = 'subject_id is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::subjectTeacherEligibilityService()->createEligibility(
            new CreateSubjectTeacherEligibilityRequest($employeeId, $subjectId),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Get(
        path: '/timetable/subject-teacher-eligibilities',
        tags: ['Subject-Teacher Eligibility'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'subject_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK — employees eligible to teach this subject.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SubjectTeacherEligibilityResponse')),
            ),
        ],
    )]
    public function index()
    {
        $subjectId = (int) ($this->request->getGet('subject_id') ?? 0);

        if ($subjectId <= 0) {
            throw new ValidationException(['subject_id' => 'subject_id query parameter is required.']);
        }

        $responses = Services::subjectTeacherEligibilityService()->listBySubject($subjectId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
