<?php

declare(strict_types=1);

namespace App\Modules\Examination\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/examination/Phase-5-Controller-Design.md
 * Base path /api/v1/examination/report-cards. No POST / create route —
 * rows are produced only by ExamService::lockExam.
 */
#[OA\Tag(name: 'Report Cards')]
class ReportCardController extends BaseController
{
    #[OA\Post(
        path: '/examination/report-cards/publish',
        tags: ['Report Cards'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'exam_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'BR-EXM-001: publishes every report card for the exam and transitions Exam.status LOCKED -> CLOSED.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ReportCardResponse')),
            ),
            new OA\Response(response: 422, description: 'EXAM_NOT_LOCKED or NO_REPORT_CARDS_TO_PUBLISH.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function publish()
    {
        $examId = (int) ($this->request->getGet('exam_id') ?? 0);

        if ($examId <= 0) {
            throw new ValidationException(['exam_id' => 'exam_id query parameter is required.']);
        }

        $body           = $this->request->getJSON(true) ?? [];
        $overrideReason = isset($body['override_reason']) && $body['override_reason'] !== ''
            ? (string) $body['override_reason']
            : null;

        $responses = Services::reportCardService()->publishReportCards($examId, $overrideReason);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    #[OA\Get(
        path: '/examination/report-cards/{id}',
        tags: ['Report Cards'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/ReportCardResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::reportCardService()->getReportCard($id)->toArray());
    }

    #[OA\Get(
        path: '/examination/report-cards',
        tags: ['Report Cards'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'exam_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ReportCardResponse')),
            ),
        ],
    )]
    public function index()
    {
        $examId = (int) ($this->request->getGet('exam_id') ?? 0);

        if ($examId <= 0) {
            throw new ValidationException(['exam_id' => 'exam_id query parameter is required.']);
        }

        $responses = Services::reportCardService()->listReportCardsByExam($examId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
