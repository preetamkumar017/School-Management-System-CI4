<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — additive table,
 * ADR-008 §4. Written only by Attendance's
 * StaffAttendanceService::closePeriod() (one-way push); read only by
 * PayrollRunService::createPayrollRun() (BR-HR-001).
 *
 * @property int|null $attendance_closure_id
 * @property int      $employee_id
 * @property string   $pay_period
 * @property string   $closed_at
 * @property int      $closed_by
 */
class AttendanceClosure extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'attendance_closure_id' => 'integer',
            'employee_id'           => 'integer',
            'closed_by'             => 'integer',
        ]);

        parent::__construct($data);
    }
}
