<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

use App\Modules\HrPayroll\Entities\PayrollRun;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 */
final class PayrollRunResponse
{
    public readonly int $payrollRunId;
    public readonly int $employeeId;
    public readonly string $payPeriod;
    public readonly float $grossPay;

    /** @var array<string, mixed> */
    public readonly array $deductionsJson;
    public readonly float $netPay;
    public readonly string $status;

    public function __construct(PayrollRun $payrollRun)
    {
        $this->payrollRunId  = $payrollRun->payroll_run_id;
        $this->employeeId    = $payrollRun->employee_id;
        $this->payPeriod     = $payrollRun->pay_period;
        $this->grossPay      = $payrollRun->gross_pay;
        $this->deductionsJson = $payrollRun->deductions_json;
        $this->netPay        = $payrollRun->net_pay;
        $this->status        = $payrollRun->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'payroll_run_id'  => $this->payrollRunId,
            'employee_id'     => $this->employeeId,
            'pay_period'      => $this->payPeriod,
            'gross_pay'       => $this->grossPay,
            'deductions_json' => $this->deductionsJson,
            'net_pay'         => $this->netPay,
            'status'          => $this->status,
        ];
    }
}
