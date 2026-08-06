<?php

declare(strict_types=1);

namespace App\Modules\Admission\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Admission\DTOs\CreateSeatAllocationRequest;
use App\Modules\Admission\DTOs\UpdateSeatAllocationCapacityRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/admission/Phase-5-Controller-Design.md
 * Base path /api/v1/admission/seat-allocations
 */
#[OA\Tag(name: 'Seat Allocations')]
class SeatAllocationController extends BaseController
{
    #[OA\Post(
        path: '/admission/seat-allocations',
        tags: ['Seat Allocations'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SeatAllocationCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/SeatAllocationResponse')),
            new OA\Response(response: 422, description: 'SEAT_ALLOCATION_ALREADY_EXISTS or SEAT_ALLOCATION_RTE_QUOTA_EXCEEDS_CEILING.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $classId           = (int) ($body['class_id'] ?? 0);
        $academicSessionId = (int) ($body['academic_session_id'] ?? 0);
        [$totalCapacity, $rteQuotaCapacity] = $this->validateCapacityFields($body);

        $fields = [];

        if ($classId <= 0) {
            $fields['class_id'] = 'class_id is required.';
        }

        if ($academicSessionId <= 0) {
            $fields['academic_session_id'] = 'academic_session_id is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::seatAllocationService()->createSeatAllocation(
            new CreateSeatAllocationRequest($classId, $academicSessionId, $totalCapacity, $rteQuotaCapacity),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/admission/seat-allocations/{id}',
        tags: ['Seat Allocations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SeatAllocationUpdateRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/SeatAllocationResponse')),
            new OA\Response(response: 422, description: 'SEAT_ALLOCATION_CAPACITY_BELOW_SEATS_FILLED or SEAT_ALLOCATION_RTE_QUOTA_EXCEEDS_CEILING.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function update(int $id)
    {
        [$totalCapacity, $rteQuotaCapacity] = $this->validateCapacityFields($this->request->getJSON(true) ?? []);

        $response = Services::seatAllocationService()->updateCapacity(
            $id,
            new UpdateSeatAllocationCapacityRequest($totalCapacity, $rteQuotaCapacity),
        );

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/admission/seat-allocations/{id}',
        tags: ['Seat Allocations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/SeatAllocationResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::seatAllocationService()->getSeatAllocation($id)->toArray());
    }

    #[OA\Get(
        path: '/admission/seat-allocations',
        tags: ['Seat Allocations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'class_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'academic_session_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK, or null if none exists for this class/session.', content: new OA\JsonContent(ref: '#/components/schemas/SeatAllocationResponse')),
            new OA\Response(response: 422, description: 'class_id and academic_session_id are required.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
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

        $response = Services::seatAllocationService()->findForClassAndSession($classId, $academicSessionId);

        return $this->respondSuccess($response?->toArray());
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: int, 1: int}
     */
    private function validateCapacityFields(array $body): array
    {
        $fields = [];

        if (! isset($body['total_capacity']) || ! is_numeric($body['total_capacity']) || (int) $body['total_capacity'] <= 0) {
            $fields['total_capacity'] = 'total_capacity is required and must be a positive integer.';
        }

        if (! isset($body['rte_quota_capacity']) || ! is_numeric($body['rte_quota_capacity']) || (int) $body['rte_quota_capacity'] < 0) {
            $fields['rte_quota_capacity'] = 'rte_quota_capacity is required and must be a non-negative integer.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [(int) $body['total_capacity'], (int) $body['rte_quota_capacity']];
    }
}
