<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\HrPayroll\DTOs\CreateDepartmentRequest;
use App\Modules\HrPayroll\DTOs\UpdateDepartmentRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/hr-payroll/departments
 */
#[OA\Tag(name: 'Departments')]
class DepartmentController extends BaseController
{
    #[OA\Post(
        path: '/hr-payroll/departments',
        tags: ['Departments'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DepartmentRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/DepartmentResponse')),
            new OA\Response(response: 422, description: 'DEPARTMENT_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $name = $this->validateName($this->request->getJSON(true) ?? []);

        $response = Services::departmentService()->createDepartment(new CreateDepartmentRequest($name));

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/hr-payroll/departments/{id}',
        tags: ['Departments'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DepartmentRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/DepartmentResponse'))],
    )]
    public function update(int $id)
    {
        $name = $this->validateName($this->request->getJSON(true) ?? []);

        $response = Services::departmentService()->updateDepartment($id, new UpdateDepartmentRequest($name));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/hr-payroll/departments/{id}',
        tags: ['Departments'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/DepartmentResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::departmentService()->getDepartment($id)->toArray());
    }

    #[OA\Get(
        path: '/hr-payroll/departments',
        tags: ['Departments'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/DepartmentResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::departmentService()->listDepartments();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function validateName(array $body): string
    {
        $name = (string) ($body['department_name'] ?? '');

        if ($name === '' || strlen($name) > 50) {
            throw new ValidationException(['department_name' => 'department_name is required and must be at most 50 characters.']);
        }

        return $name;
    }
}
