<?php

declare(strict_types=1);

namespace App\Modules\Fees\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Fees\DTOs\CreateFeeStructureRequest;
use App\Modules\Fees\DTOs\UpdateFeeStructureRequest;
use App\Modules\Fees\Entities\FeeStructure;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/fees/fee-structures
 */
#[OA\Tag(name: 'Fee Structures')]
class FeeStructureController extends BaseController
{
    private const VALID_CATEGORIES = [FeeStructure::CATEGORY_GENERAL, FeeStructure::CATEGORY_RTE];

    #[OA\Post(
        path: '/fees/fee-structures',
        tags: ['Fee Structures'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/FeeStructureCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/FeeStructureResponse')),
            new OA\Response(response: 422, description: 'FEE_STRUCTURE_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $classId   = (int) ($body['class_id'] ?? 0);
        $feeHeadId = (int) ($body['fee_head_id'] ?? 0);
        $sessionId = (int) ($body['academic_session_id'] ?? 0);
        $category  = (string) ($body['category'] ?? FeeStructure::CATEGORY_GENERAL);
        $amount    = $this->validateAmount($body);

        $fields = [];

        if ($classId <= 0) {
            $fields['class_id'] = 'class_id is required.';
        }

        if ($feeHeadId <= 0) {
            $fields['fee_head_id'] = 'fee_head_id is required.';
        }

        if ($sessionId <= 0) {
            $fields['academic_session_id'] = 'academic_session_id is required.';
        }

        if (! in_array($category, self::VALID_CATEGORIES, true)) {
            $fields['category'] = 'category must be one of GENERAL, RTE.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::feeStructureService()->createFeeStructure(
            new CreateFeeStructureRequest($classId, $feeHeadId, $sessionId, $category, $amount),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/fees/fee-structures/{id}',
        tags: ['Fee Structures'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/FeeStructureUpdateRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/FeeStructureResponse'))],
    )]
    public function update(int $id)
    {
        $amount = $this->validateAmount($this->request->getJSON(true) ?? []);

        $response = Services::feeStructureService()->updateFeeStructure($id, new UpdateFeeStructureRequest($amount));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/fees/fee-structures/{id}',
        tags: ['Fee Structures'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/FeeStructureResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::feeStructureService()->getFeeStructure($id)->toArray());
    }

    #[OA\Get(
        path: '/fees/fee-structures',
        tags: ['Fee Structures'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'class_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'academic_session_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['GENERAL', 'RTE'], default: 'GENERAL')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/FeeStructureResponse')),
            ),
        ],
    )]
    public function index()
    {
        $classId   = (int) ($this->request->getGet('class_id') ?? 0);
        $sessionId = (int) ($this->request->getGet('academic_session_id') ?? 0);
        $category  = (string) ($this->request->getGet('category') ?? FeeStructure::CATEGORY_GENERAL);

        $fields = [];

        if ($classId <= 0) {
            $fields['class_id'] = 'class_id query parameter is required.';
        }

        if ($sessionId <= 0) {
            $fields['academic_session_id'] = 'academic_session_id query parameter is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $responses = Services::feeStructureService()->listByClassSessionCategory($classId, $sessionId, $category);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function validateAmount(array $body): float
    {
        if (! isset($body['amount']) || ! is_numeric($body['amount']) || (float) $body['amount'] <= 0) {
            throw new ValidationException(['amount' => 'amount is required and must be a positive number.']);
        }

        return (float) $body['amount'];
    }
}
