<?php

declare(strict_types=1);

namespace App\Modules\Transport\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Transport\DTOs\AllocateTransportRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/transport/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/transport/allocations
 */
#[OA\Tag(name: 'Transport Allocations')]
class TransportAllocationController extends BaseController
{
    #[OA\Post(
        path: '/transport/allocations',
        tags: ['Transport Allocations'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TransportAllocationCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/TransportAllocationResponse')),
            new OA\Response(response: 422, description: 'ROUTE_CAPACITY_EXCEEDED / STUDENT_ALREADY_ALLOCATED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $studentId        = (int) ($body['student_id'] ?? 0);
        $routeId          = (int) ($body['route_id'] ?? 0);
        $stopName         = (string) ($body['stop_name'] ?? '');
        $emergencyContact = (string) ($body['emergency_contact'] ?? '');

        $fields = [];

        if ($studentId <= 0) {
            $fields['student_id'] = 'student_id is required.';
        }

        if ($routeId <= 0) {
            $fields['route_id'] = 'route_id is required.';
        }

        if ($stopName === '') {
            $fields['stop_name'] = 'stop_name is required.';
        }

        if ($emergencyContact === '') {
            $fields['emergency_contact'] = 'emergency_contact is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::transportAllocationService()->allocate(
            new AllocateTransportRequest($studentId, $routeId, $stopName, $emergencyContact),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/transport/allocations/{id}/deallocate',
        tags: ['Transport Allocations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'De-allocated.', content: new OA\JsonContent(ref: '#/components/schemas/TransportAllocationResponse'))],
    )]
    public function deallocate(int $id)
    {
        return $this->respondSuccess(Services::transportAllocationService()->deallocate($id)->toArray());
    }

    #[OA\Get(
        path: '/transport/allocations/{id}',
        tags: ['Transport Allocations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/TransportAllocationResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::transportAllocationService()->getAllocation($id)->toArray());
    }

    #[OA\Get(
        path: '/transport/allocations',
        tags: ['Transport Allocations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'route_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/TransportAllocationResponse')),
            ),
        ],
    )]
    public function index()
    {
        $routeId = (int) ($this->request->getGet('route_id') ?? 0);

        if ($routeId <= 0) {
            throw new ValidationException(['route_id' => 'route_id query parameter is required.']);
        }

        $responses = Services::transportAllocationService()->listByRoute($routeId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
