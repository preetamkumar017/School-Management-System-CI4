<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\HrPayroll\DTOs\CreatePayrollRunRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/hr-payroll/payroll-runs
 */
#[OA\Tag(name: 'Payroll Runs')]
class PayrollRunController extends BaseController
{
    #[OA\Post(
        path: '/hr-payroll/payroll-runs',
        tags: ['Payroll Runs'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PayrollRunCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/PayrollRunResponse')),
            new OA\Response(response: 422, description: 'ATTENDANCE_NOT_CLOSED / DUPLICATE_PAYROLL_RUN.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $employeeId     = (int) ($body['employee_id'] ?? 0);
        $payPeriod      = (string) ($body['pay_period'] ?? '');
        $grossPay       = $body['gross_pay'] ?? null;
        $deductionsJson = is_array($body['deductions_json'] ?? null) ? $body['deductions_json'] : [];

        $fields = [];

        if ($employeeId <= 0) {
            $fields['employee_id'] = 'employee_id is required.';
        }

        if ($payPeriod === '' || preg_match('/^\d{4}-\d{2}$/', $payPeriod) !== 1) {
            $fields['pay_period'] = 'pay_period is required and must be in YYYY-MM format.';
        }

        if (! is_numeric($grossPay) || (float) $grossPay <= 0) {
            $fields['gross_pay'] = 'gross_pay is required and must be a positive number.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::payrollRunService()->createPayrollRun(
            new CreatePayrollRunRequest($employeeId, $payPeriod, (float) $grossPay, $deductionsJson),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/hr-payroll/payroll-runs/{id}/approve',
        tags: ['Payroll Runs'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Approved.', content: new OA\JsonContent(ref: '#/components/schemas/PayrollRunResponse'))],
    )]
    public function approve(int $id)
    {
        return $this->respondSuccess(Services::payrollRunService()->approve($id)->toArray());
    }

    #[OA\Post(
        path: '/hr-payroll/payroll-runs/{id}/process',
        tags: ['Payroll Runs'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Processed (payslip issued, BR-HR-007).', content: new OA\JsonContent(ref: '#/components/schemas/PayrollRunResponse'))],
    )]
    public function process(int $id)
    {
        return $this->respondSuccess(Services::payrollRunService()->process($id)->toArray());
    }

    #[OA\Post(
        path: '/hr-payroll/payroll-runs/{id}/generate-payslip',
        tags: ['Payroll Runs'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Generated (ADR-012 §3).', content: new OA\JsonContent(ref: '#/components/schemas/DocumentResponse')),
            new OA\Response(response: 422, description: 'PAYROLL_RUN_NOT_PROCESSED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function generatePayslip(int $id)
    {
        return $this->respondCreated(Services::payrollRunService()->generatePayslipPdf($id)->toArray());
    }

    #[OA\Get(
        path: '/hr-payroll/payroll-runs/{id}',
        tags: ['Payroll Runs'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/PayrollRunResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::payrollRunService()->getPayrollRun($id)->toArray());
    }

    #[OA\Get(
        path: '/hr-payroll/payroll-runs',
        tags: ['Payroll Runs'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'employee_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/PayrollRunResponse')),
            ),
        ],
    )]
    public function index()
    {
        $employeeId = (int) ($this->request->getGet('employee_id') ?? 0);

        if ($employeeId <= 0) {
            throw new ValidationException(['employee_id' => 'employee_id query parameter is required.']);
        }

        $responses = Services::payrollRunService()->listByEmployee($employeeId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
