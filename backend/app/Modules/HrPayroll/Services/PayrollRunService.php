<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\HrPayroll\DTOs\CreatePayrollRunRequest;
use App\Modules\HrPayroll\DTOs\PayrollRunResponse;
use App\Modules\HrPayroll\Entities\PayrollRun;
use App\Modules\HrPayroll\Models\AttendanceClosureModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Modules\HrPayroll\Models\PayrollRunModel;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 */
class PayrollRunService
{
    public function __construct(
        private readonly PayrollRunModel $payrollRunModel,
        private readonly EmployeeModel $employeeModel,
        private readonly AttendanceClosureModel $attendanceClosureModel,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * BR-HR-001: blocked unless Attendance has pushed a closure record
     * for this employee/period (ADR-008 §4). BR-HR-005: deductions_json
     * is caller-supplied, not auto-calculated (ADR-008 §8).
     */
    public function createPayrollRun(CreatePayrollRunRequest $request): PayrollRunResponse
    {
        if ($this->employeeModel->find($request->employeeId) === null) {
            throw new BusinessRuleException('EMPLOYEE_NOT_FOUND', 'Employee not found.');
        }

        if (! $this->attendanceClosureModel->existsByEmployeePeriod($request->employeeId, $request->payPeriod)) {
            throw new BusinessRuleException(
                'ATTENDANCE_NOT_CLOSED',
                'Staff attendance for this employee/period has not been closed yet (BR-HR-001).',
            );
        }

        if ($this->payrollRunModel->existsByEmployeePeriod($request->employeeId, $request->payPeriod)) {
            throw new BusinessRuleException(
                'DUPLICATE_PAYROLL_RUN',
                'A payroll run already exists for this employee and period (BR-HR-003).',
            );
        }

        foreach ($request->deductionsJson as $label => $amount) {
            if (! is_numeric($amount) || (float) $amount < 0) {
                throw new BusinessRuleException('INVALID_DEDUCTION_AMOUNT', "Deduction \"{$label}\" must be a non-negative number.");
            }
        }

        $netPay = $request->grossPay - array_sum(array_map('floatval', $request->deductionsJson));

        $id = $this->payrollRunModel->insert([
            'employee_id'     => $request->employeeId,
            'pay_period'      => $request->payPeriod,
            'gross_pay'       => $request->grossPay,
            'deductions_json' => $request->deductionsJson,
            'net_pay'         => $netPay,
            'status'          => PayrollRun::STATUS_DRAFT,
        ], true);

        $payrollRun = $this->payrollRunModel->find($id);

        $this->auditService->record('PayrollRun', $id, AuditLog::ACTION_CREATE, null, $payrollRun->toRawArray());

        return new PayrollRunResponse($payrollRun);
    }

    public function approve(int $id): PayrollRunResponse
    {
        $before = $this->requirePayrollRun($id);

        if ($before->status !== PayrollRun::STATUS_DRAFT) {
            throw new BusinessRuleException(
                'PAYROLL_RUN_INVALID_STATUS_TRANSITION',
                "Cannot approve a payroll run in status {$before->status}.",
            );
        }

        $this->payrollRunModel->update($id, ['status' => PayrollRun::STATUS_APPROVED]);
        $after = $this->payrollRunModel->find($id);

        $this->auditService->record('PayrollRun', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new PayrollRunResponse($after);
    }

    /**
     * BR-HR-007: Processed is the payslip-issued signal, immutable
     * thereafter (ADR-008 §6, §10).
     */
    public function process(int $id): PayrollRunResponse
    {
        $before = $this->requirePayrollRun($id);

        if ($before->status !== PayrollRun::STATUS_APPROVED) {
            throw new BusinessRuleException(
                'PAYROLL_RUN_INVALID_STATUS_TRANSITION',
                "Cannot process a payroll run in status {$before->status}.",
            );
        }

        $this->payrollRunModel->update($id, ['status' => PayrollRun::STATUS_PROCESSED]);
        $after = $this->payrollRunModel->find($id);

        $this->auditService->record('PayrollRun', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new PayrollRunResponse($after);
    }

    public function getPayrollRun(int $id): PayrollRunResponse
    {
        return new PayrollRunResponse($this->requirePayrollRun($id));
    }

    /**
     * @return list<PayrollRunResponse>
     */
    public function listByEmployee(int $employeeId): array
    {
        return array_map(
            static fn (PayrollRun $payrollRun): PayrollRunResponse => new PayrollRunResponse($payrollRun),
            $this->payrollRunModel->findByEmployeeId($employeeId),
        );
    }

    private function requirePayrollRun(int $id): PayrollRun
    {
        $payrollRun = $this->payrollRunModel->find($id);

        if ($payrollRun === null) {
            throw new BusinessRuleException('PAYROLL_RUN_NOT_FOUND', 'Payroll run not found.');
        }

        return $payrollRun;
    }
}
