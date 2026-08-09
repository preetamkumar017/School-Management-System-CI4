<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Modules\Administration\Services\ConfigurationService;
use App\Modules\Attendance\Entities\StaffAttendanceRecord;
use App\Modules\Attendance\Models\StaffAttendanceRecordModel;
use CodeIgniter\I18n\Time;

class AttendanceCalculationEngine
{
    public function __construct(
        private readonly StaffAttendanceRecordModel $attendanceRecordModel,
        private readonly ConfigurationService $configService
    ) {
    }

    /**
     * Recalculates the attendance record for a specific employee on a specific date based on raw punches.
     */
    public function recalculate(int $employeeId, string $date, array $punches): void
    {
        $record = $this->attendanceRecordModel->findByEmployeeDate($employeeId, $date);
        
        if ($record === null) {
            $record = new StaffAttendanceRecord([
                'employee_id'     => $employeeId,
                'attendance_date' => $date,
                'state'           => StaffAttendanceRecord::STATE_MISSING_PUNCH,
                'is_reconciled'   => false,
            ]);
            $recordId = $this->attendanceRecordModel->insert($record, true);
            $record = $this->attendanceRecordModel->find($recordId);
        }

        if (empty($punches)) {
            $this->attendanceRecordModel->update($record->staff_attendance_id, [
                'state'          => StaffAttendanceRecord::STATE_UNAUTHORIZED,
                'first_in_time'  => null,
                'last_out_time'  => null,
                'total_hours'    => 0,
                'late_minutes'   => 0,
                'early_minutes'  => 0,
                'overtime_hours' => 0,
                'is_half_day'    => false,
            ]);
            return;
        }

        // Sort punches by time
        usort($punches, fn($a, $b) => strcmp($a->punch_time, $b->punch_time));

        $firstIn = null;
        $lastOut = null;
        
        // Simple first-in last-out logic for a day
        foreach ($punches as $punch) {
            if ($punch->punch_type === 'In' && $firstIn === null) {
                $firstIn = $punch->punch_time;
            }
            if ($punch->punch_type === 'Out') {
                $lastOut = $punch->punch_time;
            }
        }

        if ($firstIn === null || $lastOut === null || count($punches) % 2 !== 0) {
            $this->attendanceRecordModel->update($record->staff_attendance_id, [
                'state'          => StaffAttendanceRecord::STATE_MISSING_PUNCH,
                'first_in_time'  => $firstIn,
                'last_out_time'  => $lastOut,
                'total_hours'    => 0,
                'late_minutes'   => 0,
                'early_minutes'  => 0,
                'overtime_hours' => 0,
                'is_half_day'    => false,
            ]);
            return;
        }

        $inTime = new Time($firstIn);
        $outTime = new Time($lastOut);
        
        // Total Hours
        $diffSeconds = $outTime->getTimestamp() - $inTime->getTimestamp();
        $totalHours = round($diffSeconds / 3600, 2);

        // Fetch Configs
        $standardStart = $this->configService->getString('attendance.standard_shift_start') ?: '08:00:00';
        $standardEnd = $this->configService->getString('attendance.standard_shift_end') ?: '16:00:00';
        $lateGrace = (int) ($this->configService->getNumber('attendance.late_coming_grace_minutes') ?: 15);
        $earlyGrace = (int) ($this->configService->getNumber('attendance.early_leaving_grace_minutes') ?: 15);
        
        $halfDayThreshold = (float) ($this->configService->getNumber('attendance.half_day_threshold_hours') ?: 4.5);
        $fullDayThreshold = (float) ($this->configService->getNumber('attendance.full_day_threshold_hours') ?: 8.0);
        $overtimeEnabled = $this->configService->getBoolean('attendance.overtime_enabled');

        $shiftStart = Time::parse("$date $standardStart");
        $shiftEnd = Time::parse("$date $standardEnd");

        // Late Coming
        $lateMinutes = 0;
        if ($inTime->isAfter($shiftStart)) {
            $diffMins = (int) round(($inTime->getTimestamp() - $shiftStart->getTimestamp()) / 60);
            if ($diffMins > $lateGrace) {
                $lateMinutes = $diffMins;
            }
        }

        // Early Leaving
        $earlyMinutes = 0;
        if ($outTime->isBefore($shiftEnd)) {
            $diffMins = (int) round(($shiftEnd->getTimestamp() - $outTime->getTimestamp()) / 60);
            if ($diffMins > $earlyGrace) {
                $earlyMinutes = $diffMins;
            }
        }

        // State Evaluation
        $isHalfDay = false;
        $state = StaffAttendanceRecord::STATE_PRESENT;
        
        if ($totalHours < $halfDayThreshold) {
            $state = StaffAttendanceRecord::STATE_UNAUTHORIZED;
        } elseif ($totalHours < $fullDayThreshold) {
            $isHalfDay = true;
            $state = StaffAttendanceRecord::STATE_HALF_DAY;
        }

        // Overtime Calculation
        $overtimeHours = 0;
        if ($overtimeEnabled && $totalHours > $fullDayThreshold) {
            $overtimeHours = $totalHours - $fullDayThreshold;
        }

        $this->attendanceRecordModel->update($record->staff_attendance_id, [
            'state'          => $state,
            'first_in_time'  => $firstIn,
            'last_out_time'  => $lastOut,
            'total_hours'    => $totalHours,
            'late_minutes'   => $lateMinutes,
            'early_minutes'  => $earlyMinutes,
            'overtime_hours' => $overtimeHours,
            'is_half_day'    => $isHalfDay,
        ]);
    }
}
