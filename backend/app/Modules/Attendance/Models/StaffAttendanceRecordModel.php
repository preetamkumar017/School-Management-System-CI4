<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Core\BaseModel;
use App\Modules\Attendance\Entities\StaffAttendanceRecord;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md (ADR-008 §3)
 */
class StaffAttendanceRecordModel extends BaseModel
{
    protected $table          = 'staff_attendance_records';
    protected $primaryKey     = 'staff_attendance_id';
    protected $returnType     = StaffAttendanceRecord::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'employee_id',
        'attendance_date',
        'state',
        'is_reconciled',
        'created_by',
        'updated_by',
    ];

    public function existsByEmployeeDate(int $employeeId, string $attendanceDate): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('attendance_date', $attendanceDate)
            ->countAllResults() > 0;
    }

    public function findByEmployeeDate(int $employeeId, string $attendanceDate): ?StaffAttendanceRecord
    {
        return $this->where('employee_id', $employeeId)
            ->where('attendance_date', $attendanceDate)
            ->first();
    }

    /**
     * @return list<StaffAttendanceRecord>
     */
    public function findByEmployeeBetween(int $employeeId, string $fromDate, string $toDate): array
    {
        return $this->where('employee_id', $employeeId)
            ->where('attendance_date >=', $fromDate)
            ->where('attendance_date <=', $toDate)
            ->findAll();
    }

    public function countUnreconciledByEmployeeBetween(int $employeeId, string $fromDate, string $toDate): int
    {
        return $this->where('employee_id', $employeeId)
            ->where('attendance_date >=', $fromDate)
            ->where('attendance_date <=', $toDate)
            ->where('is_reconciled', false)
            ->countAllResults();
    }
}
