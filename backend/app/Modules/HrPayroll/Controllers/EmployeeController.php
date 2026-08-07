<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\HrPayroll\DTOs\CreateEmployeeRequest;
use App\Modules\HrPayroll\DTOs\UpdateEmployeeRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/hr-payroll/employees
 */
#[OA\Tag(name: 'Employees')]
class EmployeeController extends BaseController
{
    #[OA\Post(
        path: '/hr-payroll/employees',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EmployeeCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/EmployeeResponse')),
            new OA\Response(response: 422, description: 'EMPLOYEE_CODE_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $code          = (string) ($body['employee_code'] ?? '');
        $fullName      = (string) ($body['full_name'] ?? '');
        $departmentId  = (int) ($body['department_id'] ?? 0);
        $designationId = (int) ($body['designation_id'] ?? 0);
        $joiningDate   = (string) ($body['joining_date'] ?? '');
        $salaryJson    = is_array($body['salary_structure_json'] ?? null) ? $body['salary_structure_json'] : [];

        $fields = [];

        if ($code === '' || strlen($code) > 20) {
            $fields['employee_code'] = 'employee_code is required and must be at most 20 characters.';
        }

        if ($fullName === '') {
            $fields['full_name'] = 'full_name is required.';
        }

        if ($departmentId <= 0) {
            $fields['department_id'] = 'department_id is required.';
        }

        if ($designationId <= 0) {
            $fields['designation_id'] = 'designation_id is required.';
        }

        if ($joiningDate === '') {
            $fields['joining_date'] = 'joining_date is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::employeeService()->createEmployee(
            new CreateEmployeeRequest($code, $fullName, $departmentId, $designationId, $joiningDate, $salaryJson),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/hr-payroll/employees/{id}',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EmployeeUpdateRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/EmployeeResponse'))],
    )]
    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        $fullName      = (string) ($body['full_name'] ?? '');
        $departmentId  = (int) ($body['department_id'] ?? 0);
        $designationId = (int) ($body['designation_id'] ?? 0);
        $salaryJson    = is_array($body['salary_structure_json'] ?? null) ? $body['salary_structure_json'] : [];
        $exitDate      = isset($body['exit_date']) && $body['exit_date'] !== '' ? (string) $body['exit_date'] : null;

        $fields = [];

        if ($fullName === '') {
            $fields['full_name'] = 'full_name is required.';
        }

        if ($departmentId <= 0) {
            $fields['department_id'] = 'department_id is required.';
        }

        if ($designationId <= 0) {
            $fields['designation_id'] = 'designation_id is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::employeeService()->updateEmployee(
            $id,
            new UpdateEmployeeRequest($fullName, $departmentId, $designationId, $salaryJson, $exitDate),
        );

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/hr-payroll/employees/{id}',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/EmployeeResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::employeeService()->getEmployee($id)->toArray());
    }

    #[OA\Get(
        path: '/hr-payroll/employees',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/EmployeeResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::employeeService()->listEmployees();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
