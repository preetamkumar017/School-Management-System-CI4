<?php

declare(strict_types=1);

namespace App\Modules\Examination\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Examination\DTOs\CreatePromotionRecordRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/examination/Phase-5-Controller-Design.md
 * Base path /api/v1/examination/promotions
 */
#[OA\Tag(name: 'Promotions')]
class PromotionController extends BaseController
{
    #[OA\Post(
        path: '/examination/promotions',
        tags: ['Promotions'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PromotionRecordCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created — BR-SIS-001. fee_closure_confirmed is computed from Fees (ADR-014 §2).', content: new OA\JsonContent(ref: '#/components/schemas/PromotionRecordResponse')),
            new OA\Response(response: 422, description: 'PROMOTION_CLOSURE_PRECONDITION_NOT_MET, PROMOTION_INVALID_CLASS_SEQUENCE, or PROMOTION_RECORD_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];
        [$studentId, $fromSessionId, $toSessionId, $fromClassId, $toClassId] = $this->validateFields($body);

        $response = Services::promotionService()->promoteStudent(
            new CreatePromotionRecordRequest($studentId, $fromSessionId, $toSessionId, $fromClassId, $toClassId),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Get(
        path: '/examination/promotions/{id}',
        tags: ['Promotions'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/PromotionRecordResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::promotionService()->getPromotionRecord($id)->toArray());
    }

    #[OA\Get(
        path: '/examination/promotions',
        tags: ['Promotions'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'to_session_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/PromotionRecordResponse')),
            ),
        ],
    )]
    public function index()
    {
        $toSessionId = (int) ($this->request->getGet('to_session_id') ?? 0);

        if ($toSessionId <= 0) {
            throw new ValidationException(['to_session_id' => 'to_session_id query parameter is required.']);
        }

        $responses = Services::promotionService()->listPromotionsByToSession($toSessionId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     */
    private function validateFields(array $body): array
    {
        $studentId     = (int) ($body['student_id'] ?? 0);
        $fromSessionId = (int) ($body['from_session_id'] ?? 0);
        $toSessionId   = (int) ($body['to_session_id'] ?? 0);
        $fromClassId   = (int) ($body['from_class_id'] ?? 0);
        $toClassId     = (int) ($body['to_class_id'] ?? 0);

        $fields = [];

        if ($studentId <= 0) {
            $fields['student_id'] = 'student_id is required.';
        }

        if ($fromSessionId <= 0) {
            $fields['from_session_id'] = 'from_session_id is required.';
        }

        if ($toSessionId <= 0) {
            $fields['to_session_id'] = 'to_session_id is required.';
        }

        if ($fromClassId <= 0) {
            $fields['from_class_id'] = 'from_class_id is required.';
        }

        if ($toClassId <= 0) {
            $fields['to_class_id'] = 'to_class_id is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$studentId, $fromSessionId, $toSessionId, $fromClassId, $toClassId];
    }
}
