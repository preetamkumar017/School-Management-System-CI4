<?php

declare(strict_types=1);

namespace App\Modules\Transport\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Transport\DTOs\CreateRouteRequest;
use App\Modules\Transport\DTOs\UpdateRouteRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/transport/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/transport/routes
 */
#[OA\Tag(name: 'Routes')]
class RouteController extends BaseController
{
    #[OA\Post(
        path: '/transport/routes',
        tags: ['Routes'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RouteCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/RouteResponse')),
            new OA\Response(response: 422, description: 'ROUTE_CAPACITY_EXCEEDS_VEHICLE.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $routeName = (string) ($body['route_name'] ?? '');
        [$stopsJson, $capacity, $vehicleId] = $this->validateFields($body);

        if ($routeName === '' || strlen($routeName) > 50) {
            throw new ValidationException(['route_name' => 'route_name is required and must be at most 50 characters.']);
        }

        $response = Services::routeService()->createRoute(new CreateRouteRequest($routeName, $stopsJson, $capacity, $vehicleId));

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/transport/routes/{id}',
        tags: ['Routes'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RouteUpdateRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/RouteResponse'))],
    )]
    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        [$stopsJson, $capacity, $vehicleId] = $this->validateFields($body);

        $response = Services::routeService()->updateRoute($id, new UpdateRouteRequest($stopsJson, $capacity, $vehicleId));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/transport/routes/{id}',
        tags: ['Routes'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/RouteResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::routeService()->getRoute($id)->toArray());
    }

    #[OA\Get(
        path: '/transport/routes',
        tags: ['Routes'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/RouteResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::routeService()->listRoutes();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: list<string>, 1: int, 2: ?int}
     */
    private function validateFields(array $body): array
    {
        $stopsJson = is_array($body['stops_json'] ?? null) ? array_values($body['stops_json']) : [];
        $capacity  = (int) ($body['capacity'] ?? 0);
        $vehicleId = isset($body['vehicle_id']) ? (int) $body['vehicle_id'] : null;

        $fields = [];

        if ($stopsJson === []) {
            $fields['stops_json'] = 'stops_json is required and must be a non-empty array.';
        }

        if ($capacity <= 0) {
            $fields['capacity'] = 'capacity is required and must be a positive integer.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$stopsJson, $capacity, $vehicleId];
    }
}
