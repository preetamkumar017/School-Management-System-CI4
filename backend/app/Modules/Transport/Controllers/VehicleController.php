<?php

declare(strict_types=1);

namespace App\Modules\Transport\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Transport\DTOs\CreateVehicleRequest;
use App\Modules\Transport\DTOs\UpdateVehicleRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/transport/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/transport/vehicles
 */
#[OA\Tag(name: 'Vehicles')]
class VehicleController extends BaseController
{
    #[OA\Post(
        path: '/transport/vehicles',
        tags: ['Vehicles'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VehicleCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/VehicleResponse')),
            new OA\Response(response: 422, description: 'VEHICLE_REGISTRATION_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        [$registrationNo, $capacity, $gpsDeviceId, $licenseValidUntil] = $this->validateFields($body, true);

        $response = Services::vehicleService()->createVehicle(
            new CreateVehicleRequest($registrationNo, $capacity, $gpsDeviceId, $licenseValidUntil),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/transport/vehicles/{id}',
        tags: ['Vehicles'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VehicleUpdateRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/VehicleResponse'))],
    )]
    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        [, $capacity, $gpsDeviceId, $licenseValidUntil] = $this->validateFields($body, false);

        $response = Services::vehicleService()->updateVehicle($id, new UpdateVehicleRequest($capacity, $gpsDeviceId, $licenseValidUntil));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/transport/vehicles/{id}',
        tags: ['Vehicles'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/VehicleResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::vehicleService()->getVehicle($id)->toArray());
    }

    #[OA\Get(
        path: '/transport/vehicles',
        tags: ['Vehicles'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/VehicleResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::vehicleService()->listVehicles();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: int, 2: ?string, 3: ?string}
     */
    private function validateFields(array $body, bool $requireRegistrationNo): array
    {
        $registrationNo    = (string) ($body['registration_no'] ?? '');
        $capacity          = (int) ($body['capacity'] ?? 0);
        $gpsDeviceId       = isset($body['gps_device_id']) && $body['gps_device_id'] !== '' ? (string) $body['gps_device_id'] : null;
        $licenseValidUntil = isset($body['license_valid_until']) && $body['license_valid_until'] !== '' ? (string) $body['license_valid_until'] : null;

        $fields = [];

        if ($requireRegistrationNo && ($registrationNo === '' || strlen($registrationNo) > 20)) {
            $fields['registration_no'] = 'registration_no is required and must be at most 20 characters.';
        }

        if ($capacity <= 0) {
            $fields['capacity'] = 'capacity is required and must be a positive integer.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$registrationNo, $capacity, $gpsDeviceId, $licenseValidUntil];
    }
}
