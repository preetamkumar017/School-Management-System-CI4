<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Models\UserModel;
use Tests\Support\HrPayroll\HrPayrollTestCase;

/**
 * @internal
 */
final class PayrollRunTest extends HrPayrollTestCase
{
    /**
     * @return array{0: int, 1: string} [employeeId, username]
     */
    private function createSelfServiceEmployeeCaller(?int $employeeId = null): array
    {
        $employeeId = $employeeId ?? $this->createEmployeeFixture();
        $roleId     = $this->createRole(['read']);
        $userId     = (new UserModel())->insert([
            'username'      => 'self_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'EMPLOYEE',
            'owner_ref_id'  => $employeeId,
            'status'        => 'ACTIVE',
        ], true);

        return [$employeeId, (new UserModel())->find($userId)->username];
    }

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

    /**
     * ADR-024 §3: PayrollRunService's self-service reads
     * (listByEmployee()/getPayrollRun()) allow Tier 2 — an employee may
     * view their own payslip/payroll history.
     */
    public function testGetAndListPayrollRunSucceedForSelf(): void
    {
        [$employeeId, $username] = $this->createSelfServiceEmployeeCaller();
        $payrollRunId = $this->createPayrollRunFixture($employeeId, '2026-07');

        $tokens  = $this->loginAs($username);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->get("api/v1/hr-payroll/payroll-runs/{$payrollRunId}")->assertStatus(200);
        $this->withHeaders($headers)->get("api/v1/hr-payroll/payroll-runs?employee_id={$employeeId}")->assertStatus(200);
    }

    /**
     * A caller with neither hr_payroll.manage nor ownership of the
     * target employee cannot view that employee's payroll run(s).
     */
    public function testGetAndListPayrollRunRejectedForNeitherManageNorSelf(): void
    {
        $targetEmployeeId    = $this->createEmployeeFixture();
        $payrollRunId        = $this->createPayrollRunFixture($targetEmployeeId, '2026-07');
        [, $callerUsername]  = $this->createSelfServiceEmployeeCaller();

        $tokens  = $this->loginAs($callerUsername);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->get("api/v1/hr-payroll/payroll-runs/{$payrollRunId}"),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->get("api/v1/hr-payroll/payroll-runs?employee_id={$targetEmployeeId}"),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    /**
     * createPayrollRun() is hr_payroll.manage-only — never self-service,
     * even for the employee the run is for.
     */
    public function testCreatePayrollRunRejectedForCallerWithoutManagePermission(): void
    {
        [$employeeId, $username] = $this->createSelfServiceEmployeeCaller();
        $this->createAttendanceClosureFixture($employeeId, '2026-07');

        $tokens  = $this->loginAs($username);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/hr-payroll/payroll-runs', [
                'employee_id'     => $employeeId,
                'pay_period'      => '2026-07',
                'gross_pay'       => 50000,
                'deductions_json' => ['PF' => 1800],
            ]),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
