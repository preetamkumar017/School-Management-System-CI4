<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Models\UserModel;
use App\Modules\HrPayroll\Services\LeaveRequestService;
use Tests\Support\HrPayroll\HrPayrollTestCase;

/**
 * @internal
 */
final class LeaveRequestTest extends HrPayrollTestCase
{
    /**
     * @return array{0: int, 1: string} [employeeId, username] — a
     * restricted, generic-permission-only caller whose linked User is
     * the owner of the given employee, for Tier-2 self-service tests.
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

    public function testCreateAndApproveWithinBalance(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeFixture();

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/hr-payroll/leave-requests', [
            'employee_id' => $employeeId,
            'leave_type'  => 'CL',
            'start_date'  => '2026-08-10',
            'end_date'    => '2026-08-12',
        ]);
        $create->assertStatus(201);
        $leaveRequestId = $this->decode($create)['data']['leave_request_id'];

        $decide = $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/hr-payroll/leave-requests/{$leaveRequestId}/decide", [
            'decision' => 'Approved',
        ]);
        $decide->assertStatus(200);
        $this->assertSame('Approved', $this->decode($decide)['data']['status']);
    }

    /**
     * BR-HR-004 (ADR-008 §7): CL's decided annual allocation is 12 days.
     * A 13-day request exceeds it and is blocked without an override.
     */
    public function testApprovalBlockedWhenBalanceWouldGoNegative(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeFixture();

        $leaveRequestId = $this->createLeaveRequestFixture($employeeId, 'CL', '2026-01-01', '2026-01-13');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/hr-payroll/leave-requests/{$leaveRequestId}/decide", [
                'decision' => 'Approved',
            ]),
            BusinessRuleException::class,
            'INSUFFICIENT_LEAVE_BALANCE',
            422,
        );
    }

    /**
     * BR-HR-004 override authority (ADR-008 §7, enforcement wired ADR-015):
     * only a caller whose role carries the override permission may
     * approve past a negative projected balance.
     */
    public function testApprovalSucceedsOverBalanceWithOverrideReasonAndPermission(): void
    {
        // decide() is hr_payroll.manage-only (ADR-024 §3) on top of the
        // separate PERMISSION_OVERRIDE check (ADR-015) this test exists
        // to cover — both are required here.
        $roleId     = $this->createRole([LeaveRequestService::PERMISSION_OVERRIDE, LeaveRequestService::PERMISSION_MANAGE]);
        $user       = $this->createUser($roleId);
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeFixture();

        $leaveRequestId = $this->createLeaveRequestFixture($employeeId, 'CL', '2026-01-01', '2026-01-13');

        $decide = $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/hr-payroll/leave-requests/{$leaveRequestId}/decide", [
            'decision'         => 'Approved',
            'override_reason'  => 'Approved by HR head as a documented policy exception.',
        ]);

        $decide->assertStatus(200);
        $this->assertSame('Approved', $this->decode($decide)['data']['status']);
    }

    /**
     * BR-HR-004: a caller without the override permission cannot approve
     * past a negative projected balance even with an override_reason.
     */
    public function testApprovalRejectedOverBalanceWithoutOverridePermission(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeFixture();

        $leaveRequestId = $this->createLeaveRequestFixture($employeeId, 'CL', '2026-01-01', '2026-01-13');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/hr-payroll/leave-requests/{$leaveRequestId}/decide", [
                'decision'        => 'Approved',
                'override_reason' => 'Approved by HR head as a documented policy exception.',
            ]),
            AuthorizationException::class,
            'OVERRIDE_NOT_PERMITTED',
            403,
        );
    }

    /**
     * BR-HR-004 balance visibility: a real approved CL request reduces
     * the remaining balance by its exact day count, and other leave
     * types stay untouched.
     */
    public function testBalanceReflectsApprovedConsumption(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeFixture();

        $leaveRequestId = $this->createLeaveRequestFixture($employeeId, 'CL', '2026-03-01', '2026-03-03');
        $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/hr-payroll/leave-requests/{$leaveRequestId}/decide", [
            'decision' => 'Approved',
        ])->assertStatus(200);

        $balance = $this->withHeaders($headers)->get("api/v1/hr-payroll/leave-requests/balance?employee_id={$employeeId}&year=2026");
        $balance->assertStatus(200);
        $data = $this->decode($balance)['data'];

        $this->assertSame(12, $data['balances']['CL']['allocation']);
        $this->assertSame(3, $data['balances']['CL']['consumed']);
        $this->assertSame(9, $data['balances']['CL']['remaining']);
        $this->assertSame(10, $data['balances']['SL']['remaining']);
        $this->assertSame(15, $data['balances']['EL']['remaining']);
    }

    /**
     * ADR-024 §3: createLeaveRequest() Tier 2 — the caller IS the
     * employee_id in the request may apply for their own leave, closing
     * the "applied leave on someone else's behalf" exploit specifically
     * (the caller has no hr_payroll.manage at all here).
     */
    public function testCreateLeaveRequestSucceedsForSelf(): void
    {
        [$employeeId, $username] = $this->createSelfServiceEmployeeCaller();
        $tokens  = $this->loginAs($username);
        $headers = $this->authHeaders($tokens['access_token']);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/hr-payroll/leave-requests', [
            'employee_id' => $employeeId,
            'leave_type'  => 'CL',
            'start_date'  => '2026-08-10',
            'end_date'    => '2026-08-11',
        ]);

        $response->assertStatus(201);
    }

    /**
     * Tier 1 — a caller with hr_payroll.manage may create a leave request
     * on behalf of any employee.
     */
    public function testCreateLeaveRequestSucceedsForManageCallerOnBehalfOfAnotherEmployee(): void
    {
        $employeeId = $this->createEmployeeFixture();
        $user       = $this->createUser($this->createRole([LeaveRequestService::PERMISSION_MANAGE]));
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/hr-payroll/leave-requests', [
            'employee_id' => $employeeId,
            'leave_type'  => 'CL',
            'start_date'  => '2026-08-10',
            'end_date'    => '2026-08-11',
        ]);

        $response->assertStatus(201);
    }

    /**
     * The exact exploit demonstrated 2026-08-08: a caller with neither
     * hr_payroll.manage nor ownership of the target employee cannot
     * create a leave request on that employee's behalf.
     */
    public function testCreateLeaveRequestRejectedForNeitherManageNorSelf(): void
    {
        $targetEmployeeId       = $this->createEmployeeFixture();
        [, $callerUsername]     = $this->createSelfServiceEmployeeCaller();
        $tokens                 = $this->loginAs($callerUsername);
        $headers                = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/hr-payroll/leave-requests', [
                'employee_id' => $targetEmployeeId,
                'leave_type'  => 'CL',
                'start_date'  => '2026-08-10',
                'end_date'    => '2026-08-11',
            ]),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    /**
     * listByEmployee()/getBalances() Tier 2: self-service leave
     * history/balance viewing (what the "My HR" frontend page needs).
     */
    public function testListByEmployeeAndBalancesSucceedForSelf(): void
    {
        [$employeeId, $username] = $this->createSelfServiceEmployeeCaller();
        $tokens  = $this->loginAs($username);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->get("api/v1/hr-payroll/leave-requests?employee_id={$employeeId}")->assertStatus(200);
        $this->withHeaders($headers)->get("api/v1/hr-payroll/leave-requests/balance?employee_id={$employeeId}")->assertStatus(200);
    }

    /**
     * Tier 1: hr_payroll.manage can view any employee's leave
     * history/balance.
     */
    public function testListByEmployeeAndBalancesSucceedForManageCaller(): void
    {
        $employeeId = $this->createEmployeeFixture();
        $user       = $this->createUser($this->createRole([LeaveRequestService::PERMISSION_MANAGE]));
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->get("api/v1/hr-payroll/leave-requests?employee_id={$employeeId}")->assertStatus(200);
        $this->withHeaders($headers)->get("api/v1/hr-payroll/leave-requests/balance?employee_id={$employeeId}")->assertStatus(200);
    }

    /**
     * A caller with neither hr_payroll.manage nor ownership of the
     * target employee cannot view that employee's leave history/balance.
     */
    public function testListByEmployeeAndBalancesRejectedForNeitherManageNorSelf(): void
    {
        $targetEmployeeId   = $this->createEmployeeFixture();
        [, $callerUsername] = $this->createSelfServiceEmployeeCaller();
        $tokens              = $this->loginAs($callerUsername);
        $headers              = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->get("api/v1/hr-payroll/leave-requests?employee_id={$targetEmployeeId}"),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->get("api/v1/hr-payroll/leave-requests/balance?employee_id={$targetEmployeeId}"),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
