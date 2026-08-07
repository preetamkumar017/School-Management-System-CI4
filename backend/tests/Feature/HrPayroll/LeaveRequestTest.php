<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Core\Exceptions\BusinessRuleException;
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

    public function testApprovalSucceedsOverBalanceWithOverrideReason(): void
    {
        $user       = $this->createUser();
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
}
