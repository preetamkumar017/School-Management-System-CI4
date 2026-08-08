<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\HrPayroll\Services\LeaveRequestService;
use Tests\Support\HrPayroll\HrPayrollTestCase;

/**
 * @internal
 */
final class LeaveRequestTest extends HrPayrollTestCase
{
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
        $roleId     = $this->createRole([LeaveRequestService::PERMISSION_OVERRIDE]);
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
}
