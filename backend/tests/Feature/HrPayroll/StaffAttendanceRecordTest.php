<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Attendance\Models\StaffAttendanceRecordModel;
use App\Modules\HrPayroll\Models\AttendanceClosureModel;
use Tests\Support\HrPayroll\HrPayrollTestCase;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md (ADR-008 §3, §4)
 *
 * @internal
 */
final class StaffAttendanceRecordTest extends HrPayrollTestCase
{
    public function testRecordAttendanceValidatesEmployeeExists(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/attendance/staff-attendance', [
                'employee_id'     => 999999,
                'attendance_date' => '2026-07-01',
                'state'           => 'Present',
            ]),
            BusinessRuleException::class,
            'EMPLOYEE_NOT_FOUND',
            422,
        );
    }

    /**
     * BR-HR-001 end-to-end: reconciling every record in the period, then
     * closing it, pushes a closure record HR & Payroll's PayrollRun can
     * read (ADR-008 §4).
     */
    public function testReconcileThenClosePeriodPushesAttendanceClosure(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeFixture();
        $model      = new StaffAttendanceRecordModel();

        $model->insert(['employee_id' => $employeeId, 'attendance_date' => '2026-07-01', 'state' => 'Present', 'is_reconciled' => false]);
        $model->insert(['employee_id' => $employeeId, 'attendance_date' => '2026-07-02', 'state' => 'Present', 'is_reconciled' => false]);

        $reconcile = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/attendance/staff-attendance/reconcile', [
            'employee_id' => $employeeId,
            'from_date'   => '2026-07-01',
            'to_date'     => '2026-07-31',
        ]);
        $reconcile->assertStatus(200);

        $close = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/attendance/staff-attendance/close-period', [
            'employee_id' => $employeeId,
            'pay_period'  => '2026-07',
        ]);
        $close->assertStatus(200);

        $this->assertTrue((new AttendanceClosureModel())->existsByEmployeePeriod($employeeId, '2026-07'));
    }

    public function testClosePeriodBlockedWhenRecordsAreUnreconciled(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeFixture();

        (new StaffAttendanceRecordModel())->insert([
            'employee_id'     => $employeeId,
            'attendance_date' => '2026-07-01',
            'state'           => 'Present',
            'is_reconciled'   => false,
        ]);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/attendance/staff-attendance/close-period', [
                'employee_id' => $employeeId,
                'pay_period'  => '2026-07',
            ]),
            BusinessRuleException::class,
            'STAFF_ATTENDANCE_NOT_RECONCILED',
            422,
        );
    }
}
