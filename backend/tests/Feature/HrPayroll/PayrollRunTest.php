<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\HrPayroll\HrPayrollTestCase;

/**
 * @internal
 */
final class PayrollRunTest extends HrPayrollTestCase
{
    /**
     * BR-HR-001 (ADR-008 §4): a payroll run is blocked unless Attendance
     * has pushed a closure record for the employee/period.
     */
    public function testCreateIsBlockedWithoutAttendanceClosure(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeFixture();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/hr-payroll/payroll-runs', [
                'employee_id'     => $employeeId,
                'pay_period'      => '2026-07',
                'gross_pay'       => 50000,
                'deductions_json' => ['PF' => 1800],
            ]),
            BusinessRuleException::class,
            'ATTENDANCE_NOT_CLOSED',
            422,
        );
    }

    public function testCreateSucceedsOnceAttendanceIsClosedAndComputesNetPay(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeFixture();
        $this->createAttendanceClosureFixture($employeeId, '2026-07');

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/hr-payroll/payroll-runs', [
            'employee_id'     => $employeeId,
            'pay_period'      => '2026-07',
            'gross_pay'       => 50000,
            'deductions_json' => ['PF' => 1800],
        ]);

        $response->assertStatus(201);
        $body = $this->decode($response)['data'];
        $this->assertSame('Draft', $body['status']);
        $this->assertEquals(48200.0, $body['net_pay']);
    }

    /**
     * BR-HR-003: a second run for the same employee/period is blocked.
     */
    public function testDuplicatePayrollRunIsRejected(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeFixture();
        $this->createAttendanceClosureFixture($employeeId, '2026-07');
        $this->createPayrollRunFixture($employeeId, '2026-07');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/hr-payroll/payroll-runs', [
                'employee_id'     => $employeeId,
                'pay_period'      => '2026-07',
                'gross_pay'       => 50000,
                'deductions_json' => ['PF' => 1800],
            ]),
            BusinessRuleException::class,
            'DUPLICATE_PAYROLL_RUN',
            422,
        );
    }

    /**
     * BR-HR-007: once Processed, a payroll run is immutable — no update
     * endpoint accepts a status change beyond process() itself, and
     * process() cannot be called twice.
     */
    public function testProcessedRunCannotBeProcessedAgain(): void
    {
        $user         = $this->createUser();
        $tokens       = $this->loginAs($user['username']);
        $headers      = $this->authHeaders($tokens['access_token']);
        $employeeId   = $this->createEmployeeFixture();
        $payrollRunId = $this->createPayrollRunFixture($employeeId, '2026-07', 50000.0, 'Approved');

        $this->withHeaders($headers)->post("api/v1/hr-payroll/payroll-runs/{$payrollRunId}/process")->assertStatus(200);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/hr-payroll/payroll-runs/{$payrollRunId}/process"),
            BusinessRuleException::class,
            'PAYROLL_RUN_INVALID_STATUS_TRANSITION',
            422,
        );
    }
}
