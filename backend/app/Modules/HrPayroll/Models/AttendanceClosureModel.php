<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Modules\HrPayroll\Entities\AttendanceClosure;
use CodeIgniter\Model;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 * Extends CodeIgniter\Model directly, not App\Core\BaseModel — write-once,
 * no soft-delete, no updated_by stamping, same reasoning as AuditLogModel.
 * Written only by Attendance's StaffAttendanceService::closePeriod();
 * read only by PayrollRunService (ADR-008 §4).
 */
class AttendanceClosureModel extends Model
{
    protected $table         = 'attendance_closures';
    protected $primaryKey    = 'attendance_closure_id';
    protected $returnType    = AttendanceClosure::class;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'employee_id',
        'pay_period',
        'closed_at',
        'closed_by',
    ];

    public function existsByEmployeePeriod(int $employeeId, string $payPeriod): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('pay_period', $payPeriod)
            ->countAllResults() > 0;
    }
}
