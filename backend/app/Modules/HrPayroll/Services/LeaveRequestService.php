<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\ConfigurationService;
use App\Modules\HrPayroll\DTOs\CreateLeaveRequestRequest;
use App\Modules\HrPayroll\DTOs\DecideLeaveRequestRequest;
use App\Modules\HrPayroll\DTOs\LeaveRequestResponse;
use App\Modules\HrPayroll\Entities\LeaveRequest;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Modules\HrPayroll\Models\LeaveRequestModel;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 * BR-HR-004 (ADR-008 §7) — decided annual allocations, no persisted
 * balance entity; balance computed on the fly, same shape as
 * Attendance's percentage calculation. Override authority enforcement
 * (ADR-015) — see PERMISSION_OVERRIDE.
 */
class LeaveRequestService
{
    /**
     * BR-HR-004 override authority (ADR-008 §7, enforcement wired ADR-015):
     * only a caller whose JWT permission_set carries this string may
     * supply override_reason to push a leave balance below zero.
     */
    public const PERMISSION_OVERRIDE = 'hr_payroll.leave.override';

    /**
     * RBAC (ADR-024 §3): `hr_payroll.manage` (Tier 1) OR the caller IS the
     * `employee_id` in question (Tier 2) — see per-method notes below for
     * which methods allow Tier 2 at all.
     */
    public const PERMISSION_MANAGE = 'hr_payroll.manage';

    private const ALLOCATION_CONFIG_KEYS = [
        LeaveRequest::TYPE_CL  => 'hr_payroll.leave_allocation.cl',
        LeaveRequest::TYPE_SL  => 'hr_payroll.leave_allocation.sl',
        LeaveRequest::TYPE_EL  => 'hr_payroll.leave_allocation.el',
        LeaveRequest::TYPE_ML  => 'hr_payroll.leave_allocation.ml',
        LeaveRequest::TYPE_LWP => 'hr_payroll.leave_allocation.lwp',
        LeaveRequest::TYPE_DL  => 'hr_payroll.leave_allocation.dl',
    ];

    public function __construct(
        private readonly LeaveRequestModel $leaveRequestModel,
        private readonly EmployeeModel $employeeModel,
        private readonly AuditService $auditService,
        private readonly ConfigurationService $configurationService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    /**
     * RBAC (ADR-024 §3): Tier 2 — `hr_payroll.manage` OR the caller IS the
     * `employee_id` this request is for (self-service leave application).
     */
    public function createLeaveRequest(CreateLeaveRequestRequest $request): LeaveRequestResponse
    {
        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'EMPLOYEE', $request->employeeId);

        if ($this->employeeModel->find($request->employeeId) === null) {
            throw new BusinessRuleException('EMPLOYEE_NOT_FOUND', 'Employee not found.');
        }

        if ($this->leaveRequestModel->existsOverlappingApproved($request->employeeId, $request->startDate, $request->endDate)) {
            throw new BusinessRuleException(
                'LEAVE_DATES_OVERLAP',
                'These leave dates overlap an existing approved leave for this employee.',
            );
        }

        $id = $this->leaveRequestModel->insert([
            'employee_id'          => $request->employeeId,
            'leave_type'           => $request->leaveType,
            'start_date'           => $request->startDate,
            'end_date'             => $request->endDate,
            'reason'               => $request->reason,
            'duty_leave_reference' => $request->dutyLeaveReference,
            'status'               => LeaveRequest::STATUS_PENDING,
        ], true);

        $leaveRequest = $this->leaveRequestModel->find($id);

        $this->auditService->record('LeaveRequest', $id, AuditLog::ACTION_CREATE, null, $leaveRequest->toRawArray());

        return new LeaveRequestResponse($leaveRequest);
    }

    /**
     * BR-HR-004: approval blocked if the projected balance would go
     * negative, unless override_reason is supplied — override authority
     * decided as HR role, logged (ADR-008 §7).
     */
    public function decide(int $id, DecideLeaveRequestRequest $request): LeaveRequestResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireLeaveRequest($id);

        if ($before->status !== LeaveRequest::STATUS_PENDING) {
            throw new BusinessRuleException(
                'LEAVE_REQUEST_INVALID_STATUS_TRANSITION',
                "Cannot decide a leave request in status {$before->status}.",
            );
        }

        if (! in_array($request->decision, [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED], true)) {
            throw new ValidationException(['decision' => 'decision must be Approved or Rejected.']);
        }

        if ($request->decision === LeaveRequest::STATUS_APPROVED) {
            $projectedBalance  = $this->projectedBalanceAfter($before);
            $hasOverrideReason = $request->overrideReason !== null && trim($request->overrideReason) !== '';

            if ($projectedBalance < 0) {
                if (! $hasOverrideReason) {
                    throw new BusinessRuleException(
                        'INSUFFICIENT_LEAVE_BALANCE',
                        'Approving this request would take the leave balance below zero (BR-HR-004).',
                    );
                }

                if (! in_array(self::PERMISSION_OVERRIDE, RequestContext::permissionSet(), true)) {
                    throw new AuthorizationException(
                        'OVERRIDE_NOT_PERMITTED',
                        'Only a caller with HR override authority may approve a leave request that takes the balance below zero (BR-HR-004).',
                    );
                }
            }
        }

        $this->leaveRequestModel->update($id, [
            'status'      => $request->decision,
            'approver_id' => RequestContext::userId(),
        ]);

        $after = $this->leaveRequestModel->find($id);

        if ($request->overrideReason !== null && trim($request->overrideReason) !== '') {
            $this->auditService->record('LeaveRequest', $id, AuditLog::ACTION_OVERRIDE, $before->toRawArray(), $after->toRawArray(), $request->overrideReason);
        } else {
            $this->auditService->record('LeaveRequest', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());
        }

        return new LeaveRequestResponse($after);
    }

    public function getLeaveRequest(int $id): LeaveRequestResponse
    {
        return new LeaveRequestResponse($this->requireLeaveRequest($id));
    }

    /**
     * @return list<LeaveRequestResponse>
     */
    public function listByEmployee(int $employeeId): array
    {
        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'EMPLOYEE', $employeeId);

        return array_map(
            static fn (LeaveRequest $leaveRequest): LeaveRequestResponse => new LeaveRequestResponse($leaveRequest),
            $this->leaveRequestModel->findByEmployeeId($employeeId),
        );
    }

    /**
     * Read-only entry point for Attendance's BR-ATT-005 reconciliation
     * (ADR-008 §3) — Attendance → HR & Payroll is the allowed direction.
     *
     * @return list<LeaveRequestResponse>
     */
    public function listApprovedOverlapping(int $employeeId, string $fromDate, string $toDate): array
    {
        return array_map(
            static fn (LeaveRequest $leaveRequest): LeaveRequestResponse => new LeaveRequestResponse($leaveRequest),
            $this->leaveRequestModel->findApprovedOverlapping($employeeId, $fromDate, $toDate),
        );
    }

    /**
     * Read-only balance visibility for the given calendar year — reuses
     * the exact allocation-minus-consumed computation `decide()` already
     * enforces server-side, just without a pending request's own days
     * subtracted. No new business rule; existing logic exposed as a read.
     *
     * @return array<string, array{allocation: int, consumed: int, remaining: int}>
     */
    public function getBalances(int $employeeId, int $year): array
    {
        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'EMPLOYEE', $employeeId);

        if ($this->employeeModel->find($employeeId) === null) {
            throw new BusinessRuleException('EMPLOYEE_NOT_FOUND', 'Employee not found.');
        }

        $balances = [];

        foreach (self::ALLOCATION_CONFIG_KEYS as $leaveType => $configKey) {
            $allocation = match ($leaveType) {
                LeaveRequest::TYPE_ML  => 180,
                LeaveRequest::TYPE_LWP => 999,
                LeaveRequest::TYPE_DL  => 999,
                default => (int) $this->configurationService->getNumber($configKey),
            };
            $consumed = $this->leaveRequestModel->sumApprovedDaysByEmployeeTypeYear($employeeId, $leaveType, $year);

            $balances[$leaveType] = [
                'allocation' => $allocation,
                'consumed'   => $consumed,
                'remaining'  => max(0, $allocation - $consumed),
            ];
        }

        return $balances;
    }

    private function projectedBalanceAfter(LeaveRequest $leaveRequest): int
    {
        if ($leaveRequest->leave_type === LeaveRequest::TYPE_LWP || $leaveRequest->leave_type === LeaveRequest::TYPE_DL) {
            return 0; // LWP and Duty Leave have no balance limit (always allowed)
        }

        $year          = (int) (new \DateTimeImmutable((string) $leaveRequest->start_date))->format('Y');
        $allocationKey = self::ALLOCATION_CONFIG_KEYS[$leaveRequest->leave_type] ?? null;

        $allocation = match ($leaveRequest->leave_type) {
            LeaveRequest::TYPE_ML => 180,
            default               => $allocationKey === null ? 0 : (int) $this->configurationService->getNumber($allocationKey),
        };

        $consumed   = $this->leaveRequestModel->sumApprovedDaysByEmployeeTypeYear($leaveRequest->employee_id, $leaveRequest->leave_type, $year);
        $thisRequest = (new \DateTimeImmutable((string) $leaveRequest->start_date))
            ->diff(new \DateTimeImmutable((string) $leaveRequest->end_date))->days + 1;

        return $allocation - $consumed - $thisRequest;
    }

    private function requireLeaveRequest(int $id): LeaveRequest
    {
        $leaveRequest = $this->leaveRequestModel->find($id);

        if ($leaveRequest === null) {
            throw new BusinessRuleException('LEAVE_REQUEST_NOT_FOUND', 'Leave request not found.');
        }

        return $leaveRequest;
    }
}
