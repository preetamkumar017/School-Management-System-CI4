<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\HrPayroll\DTOs\CreateDesignationRequest;
use App\Modules\HrPayroll\DTOs\UpdateDesignationRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/hr-payroll/designations
 */
#[OA\Tag(name: 'Designations')]
class DesignationController extends BaseController
{
    #[OA\Post(
        path: '/hr-payroll/designations',
        tags: ['Designations'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DesignationRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/DesignationResponse')),
            new OA\Response(response: 422, description: 'DESIGNATION_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $name = $this->validateName($this->request->getJSON(true) ?? []);

        $response = Services::designationService()->createDesignation(new CreateDesignationRequest($name));

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/hr-payroll/designations/{id}',
        tags: ['Designations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DesignationRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/DesignationResponse'))],
    )]
    public function update(int $id)
    {
        $name = $this->validateName($this->request->getJSON(true) ?? []);

        $response = Services::designationService()->updateDesignation($id, new UpdateDesignationRequest($name));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/hr-payroll/designations/{id}',
        tags: ['Designations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/DesignationResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::designationService()->getDesignation($id)->toArray());
    }

    #[OA\Get(
        path: '/hr-payroll/designations',
        tags: ['Designations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/DesignationResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::designationService()->listDesignations();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function validateName(array $body): string
    {
        $name = (string) ($body['designation_name'] ?? '');

        if ($name === '' || strlen($name) > 50) {
            throw new ValidationException(['designation_name' => 'designation_name is required and must be at most 50 characters.']);
        }

        return $name;
    }
}
