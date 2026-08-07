<?php

declare(strict_types=1);

namespace App\Modules\Attendance\DTOs;

use App\Modules\Attendance\Entities\StaffAttendanceRecord;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md (ADR-008 §3)
 */
final class StaffAttendanceRecordResponse
{
    public readonly int $staffAttendanceId;
    public readonly int $employeeId;
    public readonly string $attendanceDate;
    public readonly string $state;
    public readonly bool $isReconciled;

    public function __construct(StaffAttendanceRecord $record)
    {
        $this->staffAttendanceId = $record->staff_attendance_id;
        $this->employeeId        = $record->employee_id;
        $this->attendanceDate    = $record->attendance_date;
        $this->state             = $record->state;
        $this->isReconciled      = $record->is_reconciled;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'staff_attendance_id' => $this->staffAttendanceId,
            'employee_id'         => $this->employeeId,
            'attendance_date'     => $this->attendanceDate,
            'state'               => $this->state,
            'is_reconciled'       => $this->isReconciled,
        ];
    }
}
