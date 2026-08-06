<?php

declare(strict_types=1);

namespace App\Modules\Examination\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Examination\DTOs\CreateExamRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/examination/Phase-5-Controller-Design.md
 * Base path /api/v1/examination/exams
 */
#[OA\Tag(name: 'Exams')]
class ExamController extends BaseController
{
    #[OA\Post(
        path: '/examination/exams',
        tags: ['Exams'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ExamCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/ExamResponse')),
            new OA\Response(response: 422, description: 'Validation failure or business rule violation.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];
        [$examName, $classId, $academicSessionId, $gradingSchemeId, $examDate] = $this->validateFields($body);

        $response = Services::examService()->createExam(
            new CreateExamRequest($examName, $classId, $academicSessionId, $gradingSchemeId, $examDate),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/examination/exams/{id}/activate',
        tags: ['Exams'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'CONFIGURED -> ACTIVE.', content: new OA\JsonContent(ref: '#/components/schemas/ExamResponse'))],
    )]
    public function activate(int $id)
    {
        $response = Services::examService()->activateExam($id, $this->overrideReason());

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/examination/exams/{id}/lock',
        tags: ['Exams'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'ACTIVE -> LOCKED. Computes grade/GPA/rank and generates ReportCard rows (FR-19).', content: new OA\JsonContent(ref: '#/components/schemas/ExamResponse')),
            new OA\Response(response: 422, description: 'EXAM_MARKS_NOT_COMPLETE or EXAM_INVALID_STATUS_TRANSITION.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function lock(int $id)
    {
        $response = Services::examService()->lockExam($id, $this->overrideReason());

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/examination/exams/{id}',
        tags: ['Exams'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/ExamResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::examService()->getExam($id)->toArray());
    }

    #[OA\Get(
        path: '/examination/exams',
        tags: ['Exams'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'class_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'academic_session_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ExamResponse')),
            ),
        ],
    )]
    public function index()
    {
        $classId           = (int) ($this->request->getGet('class_id') ?? 0);
        $academicSessionId = (int) ($this->request->getGet('academic_session_id') ?? 0);

        $fields = [];

        if ($classId <= 0) {
            $fields['class_id'] = 'class_id query parameter is required.';
        }

        if ($academicSessionId <= 0) {
            $fields['academic_session_id'] = 'academic_session_id query parameter is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $responses = Services::examService()->listExamsByClassAndSession($classId, $academicSessionId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    private function overrideReason(): ?string
    {
        $body   = $this->request->getJSON(true) ?? [];
        $reason = $body['override_reason'] ?? null;

        return $reason !== null && $reason !== '' ? (string) $reason : null;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: int, 2: int, 3: int, 4: string}
     */
    private function validateFields(array $body): array
    {
        $examName          = (string) ($body['exam_name'] ?? '');
        $classId           = (int) ($body['class_id'] ?? 0);
        $academicSessionId = (int) ($body['academic_session_id'] ?? 0);
        $gradingSchemeId   = (int) ($body['grading_scheme_id'] ?? 0);
        $examDate          = (string) ($body['exam_date'] ?? '');

        $fields = [];

        if ($examName === '' || strlen($examName) > 50) {
            $fields['exam_name'] = 'exam_name is required and must be at most 50 characters.';
        }

        if ($classId <= 0) {
            $fields['class_id'] = 'class_id is required.';
        }

        if ($academicSessionId <= 0) {
            $fields['academic_session_id'] = 'academic_session_id is required.';
        }

        if ($gradingSchemeId <= 0) {
            $fields['grading_scheme_id'] = 'grading_scheme_id is required.';
        }

        if (strtotime($examDate) === false) {
            $fields['exam_date'] = 'exam_date is required and must be a valid date.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$examName, $classId, $academicSessionId, $gradingSchemeId, $examDate];
    }
}
