<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Attendance\DTOs\CreateStaffAttendanceRecordRequest;
use App\Modules\Attendance\DTOs\StaffAttendanceRecordResponse;
use App\Modules\Attendance\Entities\StaffAttendanceRecord;
use App\Modules\Attendance\Models\StaffAttendanceRecordModel;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md (ADR-008 §3, §4).
 * employee_id is validated against HR & Payroll's EmployeeService — the
 * only calls this Service makes into HR & Payroll (existence check here,
 * one-way closure push in closePeriod()); HR & Payroll never calls back
 * into Attendance.
 */
class StaffAttendanceService
{
    public function __construct(
        private readonly StaffAttendanceRecordModel $staffAttendanceRecordModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function recordAttendance(CreateStaffAttendanceRecordRequest $request): StaffAttendanceRecordResponse
    {
        AppServices::employeeService()->assertEmployeeExists($request->employeeId);

        if ($this->staffAttendanceRecordModel->existsByEmployeeDate($request->employeeId, $request->attendanceDate)) {
            throw new BusinessRuleException(
                'STAFF_ATTENDANCE_ALREADY_RECORDED',
                'Attendance for this employee/date already exists.',
            );
        }

        $id = $this->staffAttendanceRecordModel->insert([
            'employee_id'     => $request->employeeId,
            'attendance_date' => $request->attendanceDate,
            'state'           => $request->state,
            'is_reconciled'   => false,
        ], true);

        $record = $this->staffAttendanceRecordModel->find($id);

        $this->auditService->record('StaffAttendanceRecord', $id, AuditLog::ACTION_CREATE, null, $record->toRawArray());

        return new StaffAttendanceRecordResponse($record);
    }

    /**
     * BR-ATT-005: cross-checks against Leave — any date range covered by
     * an Approved LeaveRequest is marked On Leave; every other record in
     * range is simply marked reconciled.
     */
    public function reconcile(int $employeeId, string $fromDate, string $toDate): void
    {
        $records = $this->staffAttendanceRecordModel->findByEmployeeBetween($employeeId, $fromDate, $toDate);

        if ($records === []) {
            return;
        }

        $approvedLeaves = AppServices::leaveRequestService()->listApprovedOverlapping($employeeId, $fromDate, $toDate);

        foreach ($records as $record) {
            $onLeave = false;

            foreach ($approvedLeaves as $leave) {
                if ($record->attendance_date >= $leave->startDate && $record->attendance_date <= $leave->endDate) {
                    $onLeave = true;
                    break;
                }
            }

            $data = ['is_reconciled' => true];

            if ($onLeave) {
                $data['state'] = StaffAttendanceRecord::STATE_ON_LEAVE;
            }

            $this->staffAttendanceRecordModel->update($record->staff_attendance_id, $data);
        }
    }

    /**
     * BR-HR-001 (ADR-008 §4): rejects unless every record for the
     * employee/period is reconciled, then pushes a one-way closure
     * record into HR & Payroll's own storage.
     */
    public function closePeriod(int $employeeId, string $payPeriod): void
    {
        AppServices::employeeService()->assertEmployeeExists($employeeId);

        [$fromDate, $toDate] = $this->periodBounds($payPeriod);

        $records = $this->staffAttendanceRecordModel->findByEmployeeBetween($employeeId, $fromDate, $toDate);

        if ($records === [] || $this->staffAttendanceRecordModel->countUnreconciledByEmployeeBetween($employeeId, $fromDate, $toDate) > 0) {
            throw new BusinessRuleException(
                'STAFF_ATTENDANCE_NOT_RECONCILED',
                'Every staff attendance record for this employee/period must be reconciled before closure.',
            );
        }

        AppServices::employeeService()->recordAttendanceClosure($employeeId, $payPeriod, (int) RequestContext::userId());
    }

    /**
     * BR-TT-004/FR-16 (Timetable Substitution, ADR-013 §4): the absence
     * signal that gates whether a substitution may even be created for
     * a given employee/date — true only for a recorded Unauthorized or
     * On Leave StaffAttendanceRecord that date, false for Present or no
     * record at all.
     */
    public function wasAbsentOn(int $employeeId, string $date): bool
    {
        $record = $this->staffAttendanceRecordModel->findByEmployeeDate($employeeId, $date);

        if ($record === null) {
            return false;
        }

        return in_array($record->state, [StaffAttendanceRecord::STATE_UNAUTHORIZED, StaffAttendanceRecord::STATE_ON_LEAVE], true);
    }

    /**
     * @return list<StaffAttendanceRecordResponse>
     */
    public function listByEmployeeBetween(int $employeeId, string $fromDate, string $toDate): array
    {
        return array_map(
            static fn (StaffAttendanceRecord $record): StaffAttendanceRecordResponse => new StaffAttendanceRecordResponse($record),
            $this->staffAttendanceRecordModel->findByEmployeeBetween($employeeId, $fromDate, $toDate),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function periodBounds(string $payPeriod): array
    {
        $start = new \DateTimeImmutable($payPeriod . '-01');
        $end   = $start->modify('last day of this month');

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
}
