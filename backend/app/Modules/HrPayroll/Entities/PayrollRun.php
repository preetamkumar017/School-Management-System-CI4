<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-HR-004.
 *
 * @property int|null            $payroll_run_id
 * @property int                 $employee_id
 * @property string              $pay_period
 * @property float                $gross_pay
 * @property array<string,mixed> $deductions_json
 * @property float                $net_pay
 * @property string              $status
 */
class PayrollRun extends BaseEntity
{
    public const STATUS_DRAFT     = 'Draft';
    public const STATUS_APPROVED  = 'Approved';
    public const STATUS_PROCESSED = 'Processed';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'payroll_run_id'  => 'integer',
            'employee_id'     => 'integer',
            'gross_pay'       => 'float',
            'deductions_json' => 'json-array',
            'net_pay'         => 'float',
        ]);

        parent::__construct($data);
    }
}
