<?php

declare(strict_types=1);

namespace App\Modules\Fees\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Fees\DTOs\CreateScholarshipWaiverRequest;
use App\Modules\Fees\Entities\ScholarshipWaiver;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/fees/scholarship-waivers
 */
#[OA\Tag(name: 'Scholarship Waivers')]
class ScholarshipWaiverController extends BaseController
{
    private const VALID_TYPES = [
        ScholarshipWaiver::TYPE_RTE,
        ScholarshipWaiver::TYPE_MERIT,
        ScholarshipWaiver::TYPE_SIBLING,
        ScholarshipWaiver::TYPE_STAFF_WARD,
    ];

    #[OA\Post(
        path: '/fees/scholarship-waivers',
        tags: ['Scholarship Waivers'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ScholarshipWaiverCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created — applied automatically at every subsequent invoice generation for this student (ADR-007 §5).', content: new OA\JsonContent(ref: '#/components/schemas/ScholarshipWaiverResponse')),
            new OA\Response(response: 422, description: 'FEE_HEAD_NOT_FOUND.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body         = $this->request->getJSON(true) ?? [];
        $studentId    = (int) ($body['student_id'] ?? 0);
        $feeHeadId    = (int) ($body['fee_head_id'] ?? 0);
        $waiverType   = (string) ($body['waiver_type'] ?? '');
        $waiverAmount = $body['waiver_amount'] ?? null;

        $fields = [];

        if ($studentId <= 0) {
            $fields['student_id'] = 'student_id is required.';
        }

        if ($feeHeadId <= 0) {
            $fields['fee_head_id'] = 'fee_head_id is required.';
        }

        if (! in_array($waiverType, self::VALID_TYPES, true)) {
            $fields['waiver_type'] = 'waiver_type must be one of RTE, MERIT, SIBLING, STAFF_WARD.';
        }

        if (! is_numeric($waiverAmount) || (float) $waiverAmount <= 0) {
            $fields['waiver_amount'] = 'waiver_amount is required and must be a positive number.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::scholarshipWaiverService()->createWaiver(
            new CreateScholarshipWaiverRequest($studentId, $feeHeadId, $waiverType, (float) $waiverAmount),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Get(
        path: '/fees/scholarship-waivers/{id}',
        tags: ['Scholarship Waivers'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/ScholarshipWaiverResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::scholarshipWaiverService()->getWaiver($id)->toArray());
    }

    #[OA\Get(
        path: '/fees/scholarship-waivers',
        tags: ['Scholarship Waivers'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'student_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ScholarshipWaiverResponse')),
            ),
        ],
    )]
    public function index()
    {
        $studentId = (int) ($this->request->getGet('student_id') ?? 0);

        if ($studentId <= 0) {
            throw new ValidationException(['student_id' => 'student_id query parameter is required.']);
        }

        $responses = Services::scholarshipWaiverService()->listByStudent($studentId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
