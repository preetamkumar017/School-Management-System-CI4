<?php

declare(strict_types=1);

namespace App\Modules\Transport\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Transport\DTOs\CreateDriverRequest;
use App\Modules\Transport\DTOs\UpdateDriverRequest;
use App\Modules\Transport\Entities\Driver;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/transport/Phase-4-Driver-Trip-Design.md — ADR-019 §1.
 * Base path /api/v1/transport/drivers
 */
#[OA\Tag(name: 'Drivers')]
class DriverController extends BaseController
{
    #[OA\Post(
        path: '/transport/drivers',
        tags: ['Drivers'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DriverCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/DriverResponse')),
            new OA\Response(response: 422, description: 'DRIVER_LICENSE_NUMBER_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        [$fullName, $licenseNumber, $licenseValidUntil, $status] = $this->validateFields($body, true);

        $response = Services::driverService()->createDriver(
            new CreateDriverRequest($fullName, $licenseNumber, $licenseValidUntil, $status),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/transport/drivers/{id}',
        tags: ['Drivers'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DriverUpdateRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/DriverResponse'))],
    )]
    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        [$fullName, , $licenseValidUntil, $status] = $this->validateFields($body, false);

        $response = Services::driverService()->updateDriver($id, new UpdateDriverRequest($fullName, $licenseValidUntil, $status));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/transport/drivers/{id}',
        tags: ['Drivers'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/DriverResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::driverService()->getDriver($id)->toArray());
    }

    #[OA\Get(
        path: '/transport/drivers',
        tags: ['Drivers'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/DriverResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::driverService()->listDrivers();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: string, 2: ?string, 3: string}
     */
    private function validateFields(array $body, bool $requireLicenseNumber): array
    {
        $fullName          = (string) ($body['full_name'] ?? '');
        $licenseNumber     = (string) ($body['license_number'] ?? '');
        $licenseValidUntil = isset($body['license_valid_until']) && $body['license_valid_until'] !== '' ? (string) $body['license_valid_until'] : null;
        $status            = (string) ($body['status'] ?? Driver::STATUS_ACTIVE);

        $fields = [];

        if ($fullName === '' || strlen($fullName) > 100) {
            $fields['full_name'] = 'full_name is required and must be at most 100 characters.';
        }

        if ($requireLicenseNumber && ($licenseNumber === '' || strlen($licenseNumber) > 30)) {
            $fields['license_number'] = 'license_number is required and must be at most 30 characters.';
        }

        if (! in_array($status, [Driver::STATUS_ACTIVE, Driver::STATUS_INACTIVE], true)) {
            $fields['status'] = 'status must be one of Active, Inactive.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$fullName, $licenseNumber, $licenseValidUntil, $status];
    }
}
