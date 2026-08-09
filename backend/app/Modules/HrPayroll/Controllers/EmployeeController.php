<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\HrPayroll\DTOs\CreateEmployeeRequest;
use App\Modules\HrPayroll\DTOs\UpdateEmployeeRequest;
use App\Modules\HrPayroll\Entities\Employee;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/hr-payroll/employees
 */
#[OA\Tag(name: 'Employees')]
class EmployeeController extends BaseController
{
    private const VALID_STAFF_TYPES = [
        Employee::STAFF_TYPE_TEACHING,
        Employee::STAFF_TYPE_NON_TEACHING,
        Employee::STAFF_TYPE_SUPPORT,
        Employee::STAFF_TYPE_ADMINISTRATIVE,
    ];

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

        $code               = (string) ($body['employee_code'] ?? '');
        $fullName           = (string) ($body['full_name'] ?? '');
        $departmentId       = (int) ($body['department_id'] ?? 0);
        $designationId      = (int) ($body['designation_id'] ?? 0);
        $joiningDate        = (string) ($body['joining_date'] ?? '');
        $salaryJson         = is_array($body['salary_structure_json'] ?? null) ? $body['salary_structure_json'] : [];
        $staffType          = (string) ($body['staff_type'] ?? Employee::STAFF_TYPE_TEACHING);
        $qualification      = isset($body['qualification']) && $body['qualification'] !== '' ? (string) $body['qualification'] : null;
        $aadhaarNumber      = isset($body['aadhaar_number']) && $body['aadhaar_number'] !== '' ? (string) $body['aadhaar_number'] : null;
        $panNumber          = isset($body['pan_number']) && $body['pan_number'] !== '' ? (string) $body['pan_number'] : null;
        $pfUan              = isset($body['pf_uan']) && $body['pf_uan'] !== '' ? (string) $body['pf_uan'] : null;
        $esiNumber          = isset($body['esi_number']) && $body['esi_number'] !== '' ? (string) $body['esi_number'] : null;
        $bankName           = isset($body['bank_name']) && $body['bank_name'] !== '' ? (string) $body['bank_name'] : null;
        $bankAccountNumber  = isset($body['bank_account_number']) && $body['bank_account_number'] !== '' ? (string) $body['bank_account_number'] : null;
        $bankIfscCode       = isset($body['bank_ifsc_code']) && $body['bank_ifsc_code'] !== '' ? (string) $body['bank_ifsc_code'] : null;
        $probationEndDate   = isset($body['probation_end_date']) && $body['probation_end_date'] !== '' ? (string) $body['probation_end_date'] : null;
        $confirmationDate   = isset($body['confirmation_date']) && $body['confirmation_date'] !== '' ? (string) $body['confirmation_date'] : null;
        $cbseClassification = isset($body['cbse_classification']) && $body['cbse_classification'] !== '' ? (string) $body['cbse_classification'] : 'None';
        $cbseTeacherCode    = isset($body['cbse_teacher_code']) && $body['cbse_teacher_code'] !== '' ? (string) $body['cbse_teacher_code'] : null;

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

        if (! in_array($staffType, self::VALID_STAFF_TYPES, true)) {
            $fields['staff_type'] = 'staff_type must be one of Teaching, NonTeaching, Support, Administrative.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::employeeService()->createEmployee(
            new CreateEmployeeRequest(
                $code,
                $fullName,
                $departmentId,
                $designationId,
                $joiningDate,
                $salaryJson,
                $staffType,
                $cbseClassification,
                $cbseTeacherCode,
                $qualification,
                $aadhaarNumber,
                $panNumber,
                $pfUan,
                $esiNumber,
                $bankName,
                $bankAccountNumber,
                $bankIfscCode,
                $probationEndDate,
                $confirmationDate,
            ),
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

        $fullName           = (string) ($body['full_name'] ?? '');
        $departmentId       = (int) ($body['department_id'] ?? 0);
        $designationId      = (int) ($body['designation_id'] ?? 0);
        $salaryJson         = is_array($body['salary_structure_json'] ?? null) ? $body['salary_structure_json'] : [];
        $exitDate           = isset($body['exit_date']) && $body['exit_date'] !== '' ? (string) $body['exit_date'] : null;
        $staffType          = isset($body['staff_type']) && $body['staff_type'] !== '' ? (string) $body['staff_type'] : null;
        $qualification      = isset($body['qualification']) && $body['qualification'] !== '' ? (string) $body['qualification'] : null;
        $experienceYears    = isset($body['experience_years']) && $body['experience_years'] !== '' ? (float) $body['experience_years'] : null;
        $emergencyContactName  = isset($body['emergency_contact_name']) && $body['emergency_contact_name'] !== '' ? (string) $body['emergency_contact_name'] : null;
        $emergencyContactPhone = isset($body['emergency_contact_phone']) && $body['emergency_contact_phone'] !== '' ? (string) $body['emergency_contact_phone'] : null;
        $documentsJson      = is_array($body['documents_json'] ?? null) ? $body['documents_json'] : null;
        $aadhaarNumber      = isset($body['aadhaar_number']) && $body['aadhaar_number'] !== '' ? (string) $body['aadhaar_number'] : null;
        $panNumber          = isset($body['pan_number']) && $body['pan_number'] !== '' ? (string) $body['pan_number'] : null;
        $pfUan              = isset($body['pf_uan']) && $body['pf_uan'] !== '' ? (string) $body['pf_uan'] : null;
        $esiNumber          = isset($body['esi_number']) && $body['esi_number'] !== '' ? (string) $body['esi_number'] : null;
        $bankName           = isset($body['bank_name']) && $body['bank_name'] !== '' ? (string) $body['bank_name'] : null;
        $bankAccountNumber  = isset($body['bank_account_number']) && $body['bank_account_number'] !== '' ? (string) $body['bank_account_number'] : null;
        $bankIfscCode       = isset($body['bank_ifsc_code']) && $body['bank_ifsc_code'] !== '' ? (string) $body['bank_ifsc_code'] : null;
        $probationEndDate   = isset($body['probation_end_date']) && $body['probation_end_date'] !== '' ? (string) $body['probation_end_date'] : null;
        $confirmationDate   = isset($body['confirmation_date']) && $body['confirmation_date'] !== '' ? (string) $body['confirmation_date'] : null;
        $cbseClassification = isset($body['cbse_classification']) && $body['cbse_classification'] !== '' ? (string) $body['cbse_classification'] : null;
        $cbseTeacherCode    = isset($body['cbse_teacher_code']) && $body['cbse_teacher_code'] !== '' ? (string) $body['cbse_teacher_code'] : null;

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

        if ($staffType !== null && ! in_array($staffType, self::VALID_STAFF_TYPES, true)) {
            $fields['staff_type'] = 'staff_type must be one of Teaching, NonTeaching, Support, Administrative.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::employeeService()->updateEmployee(
            $id,
            new UpdateEmployeeRequest(
                fullName: $fullName,
                departmentId: $departmentId,
                designationId: $designationId,
                salaryStructureJson: $salaryJson,
                exitDate: $exitDate,
                staffType: $staffType,
                cbseClassification: $cbseClassification,
                cbseTeacherCode: $cbseTeacherCode,
                qualification: $qualification,
                experienceYears: $experienceYears,
                emergencyContactName: $emergencyContactName,
                emergencyContactPhone: $emergencyContactPhone,
                documentsJson: $documentsJson,
                aadhaarNumber: $aadhaarNumber,
                panNumber: $panNumber,
                pfUan: $pfUan,
                esiNumber: $esiNumber,
                bankName: $bankName,
                bankAccountNumber: $bankAccountNumber,
                bankIfscCode: $bankIfscCode,
                probationEndDate: $probationEndDate,
                confirmationDate: $confirmationDate,
            ),
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
