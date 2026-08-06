<?php

declare(strict_types=1);

namespace App\Modules\Fees\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Fees\DTOs\CreateFeeHeadRequest;
use App\Modules\Fees\DTOs\UpdateFeeHeadRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/fees/fee-heads
 */
#[OA\Tag(name: 'Fee Heads')]
class FeeHeadController extends BaseController
{
    #[OA\Post(
        path: '/fees/fee-heads',
        tags: ['Fee Heads'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/FeeHeadRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/FeeHeadResponse')),
            new OA\Response(response: 422, description: 'FEE_HEAD_NAME_ALREADY_TAKEN.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        [$name, $isTaxable, $gstRate] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::feeHeadService()->createFeeHead(new CreateFeeHeadRequest($name, $isTaxable, $gstRate));

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/fees/fee-heads/{id}',
        tags: ['Fee Heads'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/FeeHeadRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/FeeHeadResponse'))],
    )]
    public function update(int $id)
    {
        [$name, $isTaxable, $gstRate] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::feeHeadService()->updateFeeHead($id, new UpdateFeeHeadRequest($name, $isTaxable, $gstRate));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/fees/fee-heads/{id}',
        tags: ['Fee Heads'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/FeeHeadResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::feeHeadService()->getFeeHead($id)->toArray());
    }

    #[OA\Get(
        path: '/fees/fee-heads',
        tags: ['Fee Heads'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/FeeHeadResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::feeHeadService()->listFeeHeads();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: bool, 2: ?float}
     */
    private function validateFields(array $body): array
    {
        $name      = (string) ($body['fee_head_name'] ?? '');
        $isTaxable = (bool) ($body['is_taxable'] ?? false);
        $gstRate   = isset($body['gst_rate']) ? (float) $body['gst_rate'] : null;

        $fields = [];

        if ($name === '' || strlen($name) > 50) {
            $fields['fee_head_name'] = 'fee_head_name is required and must be at most 50 characters.';
        }

        if ($isTaxable && ($gstRate === null || $gstRate < 0 || $gstRate > 28)) {
            $fields['gst_rate'] = 'gst_rate is required (0-28) when is_taxable is true.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$name, $isTaxable, $gstRate];
    }
}
