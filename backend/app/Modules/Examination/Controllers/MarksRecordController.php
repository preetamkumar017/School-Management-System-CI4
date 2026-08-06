<?php

declare(strict_types=1);

namespace App\Modules\Examination\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Examination\DTOs\CreateMarksRecordRequest;
use App\Modules\Examination\DTOs\MarksRecordReevaluateRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/examination/Phase-5-Controller-Design.md
 * Base path /api/v1/examination/marks-records
 */
#[OA\Tag(name: 'Marks Records')]
class MarksRecordController extends BaseController
{
    #[OA\Post(
        path: '/examination/marks-records',
        tags: ['Marks Records'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/MarksRecordCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/MarksRecordResponse')),
            new OA\Response(response: 422, description: 'Validation failure or business rule violation.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];
        [$examId, $studentId, $subjectId, $marksObtained, $maxMarks] = $this->validateFields($body);

        $overrideReason = isset($body['override_reason']) && $body['override_reason'] !== ''
            ? (string) $body['override_reason']
            : null;

        $response = Services::marksRecordService()->createMarksRecord(
            new CreateMarksRecordRequest($examId, $studentId, $subjectId, $marksObtained, $maxMarks),
            $overrideReason,
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/examination/marks-records/{id}/lock',
        tags: ['Marks Records'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'BR-EXM-003 lock.', content: new OA\JsonContent(ref: '#/components/schemas/MarksRecordResponse')),
            new OA\Response(response: 422, description: 'MARKS_RECORD_ALREADY_LOCKED or MARKS_RECORD_FLAGGED_PENDING_REVIEW.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function lock(int $id)
    {
        $body           = $this->request->getJSON(true) ?? [];
        $overrideReason = isset($body['override_reason']) && $body['override_reason'] !== ''
            ? (string) $body['override_reason']
            : null;

        $response = Services::marksRecordService()->lockMarksRecord($id, $overrideReason);

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/examination/marks-records/{id}/reevaluate',
        tags: ['Marks Records'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['marks_obtained', 'reason'],
                properties: [
                    new OA\Property(property: 'marks_obtained', type: 'number', format: 'float'),
                    new OA\Property(property: 'reason', type: 'string'),
                ],
            ),
        ),
        responses: [new OA\Response(response: 200, description: 'BR-EXM-003 re-evaluation (ADR-005 §7).', content: new OA\JsonContent(ref: '#/components/schemas/MarksRecordResponse'))],
    )]
    public function reevaluate(int $id)
    {
        $body          = $this->request->getJSON(true) ?? [];
        $marksObtained = $body['marks_obtained'] ?? null;
        $reason        = (string) ($body['reason'] ?? '');

        $fields = [];

        if (! is_numeric($marksObtained)) {
            $fields['marks_obtained'] = 'marks_obtained is required and must be numeric.';
        }

        if (trim($reason) === '') {
            $fields['reason'] = 'reason is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::marksRecordService()->reevaluate(
            $id,
            new MarksRecordReevaluateRequest((float) $marksObtained, $reason),
        );

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/examination/marks-records/{id}',
        tags: ['Marks Records'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/MarksRecordResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::marksRecordService()->getMarksRecord($id)->toArray());
    }

    #[OA\Get(
        path: '/examination/marks-records',
        tags: ['Marks Records'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'exam_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/MarksRecordResponse')),
            ),
        ],
    )]
    public function index()
    {
        $examId = (int) ($this->request->getGet('exam_id') ?? 0);

        if ($examId <= 0) {
            throw new ValidationException(['exam_id' => 'exam_id query parameter is required.']);
        }

        $responses = Services::marksRecordService()->listMarksByExam($examId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: int, 1: int, 2: int, 3: ?float, 4: float}
     */
    private function validateFields(array $body): array
    {
        $examId        = (int) ($body['exam_id'] ?? 0);
        $studentId     = (int) ($body['student_id'] ?? 0);
        $subjectId     = (int) ($body['subject_id'] ?? 0);
        $marksObtained = $body['marks_obtained'] ?? null;
        $maxMarks = $body['max_marks'] ?? null;

        $fields = [];

        if ($examId <= 0) {
            $fields['exam_id'] = 'exam_id is required.';
        }

        if ($studentId <= 0) {
            $fields['student_id'] = 'student_id is required.';
        }

        if ($subjectId <= 0) {
            $fields['subject_id'] = 'subject_id is required.';
        }

        if ($marksObtained !== null && ! is_numeric($marksObtained)) {
            $fields['marks_obtained'] = 'marks_obtained must be numeric when present.';
        }

        if (! is_numeric($maxMarks) || (float) $maxMarks <= 0) {
            $fields['max_marks'] = 'max_marks is required and must be a positive number.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [
            $examId,
            $studentId,
            $subjectId,
            $marksObtained !== null ? (float) $marksObtained : null,
            (float) $maxMarks,
        ];
    }
}
