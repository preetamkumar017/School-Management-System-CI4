<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;
use App\Modules\HrPayroll\Entities\PayrollRun;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 */
class PayrollRunModel extends BaseModel
{
    protected $table          = 'payroll_runs';
    protected $primaryKey     = 'payroll_run_id';
    protected $returnType     = PayrollRun::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'employee_id',
        'pay_period',
        'lwp_days',
        'gross_pay',
        'earnings_json',
        'deductions_json',
        'net_pay',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $beforeInsert = ['stampCreatedBy', 'encodeEarningsJson', 'encodeDeductionsJson'];
    protected $beforeUpdate = ['stampUpdatedBy', 'encodeEarningsJson', 'encodeDeductionsJson'];

    protected function encodeEarningsJson(array $eventData): array
    {
        if (isset($eventData['data']['earnings_json']) && is_array($eventData['data']['earnings_json'])) {
            $eventData['data']['earnings_json'] = json_encode($eventData['data']['earnings_json']);
        }

        return $eventData;
    }

    protected function encodeDeductionsJson(array $eventData): array
    {
        if (isset($eventData['data']['deductions_json']) && is_array($eventData['data']['deductions_json'])) {
            $eventData['data']['deductions_json'] = json_encode($eventData['data']['deductions_json']);
        }

        return $eventData;
    }

    public function existsByEmployeePeriod(int $employeeId, string $payPeriod): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('pay_period', $payPeriod)
            ->countAllResults() > 0;
    }

    /**
     * @return list<PayrollRun>
     */
    public function findByEmployeeId(int $employeeId): array
    {
        return $this->where('employee_id', $employeeId)->findAll();
    }

    /**
     * @return list<PayrollRun>
     */
    public function findByPeriod(string $payPeriod): array
    {
        return $this->where('pay_period', $payPeriod)->findAll();
    }
}
