<?php

declare(strict_types=1);

namespace App\Modules\Transport\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/transport/Phase-4-Driver-Trip-Design.md — ADR-019 §5.
 * BR-TRN-006. Base path /api/v1/transport/trips
 */
#[OA\Tag(name: 'Trips')]
class TripController extends BaseController
{
    #[OA\Post(
        path: '/transport/trips/start',
        tags: ['Trips'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TripStartRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Trip started.', content: new OA\JsonContent(ref: '#/components/schemas/TripResponse')),
            new OA\Response(
                response: 422,
                description: 'BR-TRN-006: DRIVER_NOT_ASSIGNED_TO_ROUTE, VEHICLE_NOT_ASSIGNED_TO_ROUTE, DRIVER_INACTIVE, DRIVER_LICENSE_MISSING, DRIVER_LICENSE_EXPIRED, VEHICLE_LICENSE_MISSING, or VEHICLE_LICENSE_EXPIRED.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function start()
    {
        $body = $this->request->getJSON(true) ?? [];

        $routeId = isset($body['route_id']) ? (int) $body['route_id'] : 0;

        if ($routeId <= 0) {
            throw new ValidationException(['route_id' => 'route_id is required and must be a positive integer.']);
        }

        $response = Services::tripService()->startTrip($routeId);

        return $this->respondCreated($response->toArray());
    }

    #[OA\Get(
        path: '/transport/trips/{id}',
        tags: ['Trips'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/TripResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::tripService()->getTrip($id)->toArray());
    }

    #[OA\Get(
        path: '/transport/trips',
        tags: ['Trips'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'route_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/TripResponse')),
            ),
        ],
    )]
    public function index()
    {
        $routeId = (int) ($this->request->getGet('route_id') ?? 0);

        if ($routeId <= 0) {
            throw new ValidationException(['route_id' => 'route_id query parameter is required.']);
        }

        $responses = Services::tripService()->listByRoute($routeId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
