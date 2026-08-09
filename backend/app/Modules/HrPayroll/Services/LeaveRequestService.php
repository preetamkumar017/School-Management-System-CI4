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
use App\Modules\HrPayroll\Models\HolidayModel;
use App\Modules\HrPayroll\Models\LeaveRequestModel;
use App\Modules\HrPayroll\Models\LeaveTypeModel;

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

    public function __construct(
        private readonly LeaveRequestModel $leaveRequestModel,
        private readonly EmployeeModel $employeeModel,
        private readonly HolidayModel $holidayModel,
        private readonly LeaveTypeModel $leaveTypeModel,
        private readonly AuditService $auditService,
        private readonly ConfigurationService $configurationService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    /**
     * Fetch holiday date strings for a given year from the school_holidays table.
     * Falls back to empty array if table doesn't exist yet (e.g. unit tests).
     * @return list<string>
     */
    private function getHolidaysForYear(int $year): array
    {
        try {
            return $this->holidayModel->getHolidayDatesForYear($year);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get the global sandwich_rule setting (fallback = true = calendar days).
     */
    private function getGlobalSandwichRule(): bool
    {
        try {
            $val = $this->configurationService->getString('hr_payroll.sandwich_rule_enabled');
            return filter_var($val, FILTER_VALIDATE_BOOLEAN);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Resolve the effective sandwich rule for a given leave type code.
     * Per-type sandwich_rule (0/1) overrides the global setting.
     * NULL in the DB means inherit global.
     */
    private function resolveSandwichRule(string $leaveTypeCode): bool
    {
        try {
            $leaveType = $this->leaveTypeModel->findByCode($leaveTypeCode);
            if ($leaveType !== null && $leaveType['sandwich_rule'] !== null) {
                return (bool) $leaveType['sandwich_rule'];
            }
        } catch (\Throwable) {
            // table may not exist in isolated test DBs
        }
        return $this->getGlobalSandwichRule();
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

        // Notify HR Managers (users with hr_payroll.manage role permission)
        try {
            $db = \Config\Database::connect();
            $hrUsers = $db->table('users')
                ->select('users.owner_ref_id')
                ->join('roles', 'users.role_id = roles.role_id')
                ->where('users.owner_type', 'EMPLOYEE')
                ->like('roles.permission_set', 'hr_payroll.manage')
                ->get()
                ->getResultArray();

            $notificationService = \Config\Services::notificationLogService();
            $employee = $this->employeeModel->find($request->employeeId);
            $empName = $employee !== null ? $employee->full_name : "Staff member";

            foreach ($hrUsers as $hrUser) {
                $response = $notificationService->createInternal(new \App\Modules\Communication\DTOs\CreateNotificationLogRequest(
                    \App\Modules\Communication\Entities\NotificationLog::RECIPIENT_EMPLOYEE,
                    (int) $hrUser['owner_ref_id'],
                    \App\Modules\Communication\Entities\NotificationLog::CHANNEL_SMS,
                    'Leave Request Pending Approval',
                    "New leave request submitted by {$empName} for {$request->leaveType} from {$request->startDate} to {$request->endDate}."
                ));
                // Automatically dispatch it through gateway
                $notificationService->dispatchInternal($response->notificationLogId);
            }
        } catch (\Throwable $e) {
            // Log/ignore notification failures so core business transaction doesn't fail
            log_message('error', 'Failed to dispatch leave creation notifications: ' . $e->getMessage());
        }

        return $this->buildResponse($leaveRequest);
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

        // Notify the Employee of the decision (Approve/Reject)
        try {
            $notificationService = \Config\Services::notificationLogService();
            $decisionText = $request->decision === LeaveRequest::STATUS_APPROVED ? 'Approved' : 'Rejected';

            $response = $notificationService->createInternal(new \App\Modules\Communication\DTOs\CreateNotificationLogRequest(
                \App\Modules\Communication\Entities\NotificationLog::RECIPIENT_EMPLOYEE,
                (int) $before->employee_id,
                \App\Modules\Communication\Entities\NotificationLog::CHANNEL_SMS,
                "Leave Request {$decisionText}",
                "Your leave request for {$before->leave_type} from {$before->start_date} to {$before->end_date} has been {$decisionText}."
            ));
            // Automatically dispatch it through gateway
            $notificationService->dispatchInternal($response->notificationLogId);
        } catch (\Throwable $e) {
            // Log/ignore notification failures so core business transaction doesn't fail
            log_message('error', 'Failed to dispatch leave decision notification: ' . $e->getMessage());
        }

        return $this->buildResponse($after);
    }

    public function getLeaveRequest(int $id): LeaveRequestResponse
    {
        return $this->buildResponse($this->requireLeaveRequest($id));
    }

    /**
     * @return list<LeaveRequestResponse>
     */
    public function listByEmployee(int $employeeId): array
    {
        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'EMPLOYEE', $employeeId);

        return array_map(
            fn (LeaveRequest $leaveRequest): LeaveRequestResponse => $this->buildResponse($leaveRequest),
            $this->leaveRequestModel->findByEmployeeId($employeeId),
        );
    }

    /**
     * @return list<LeaveRequestResponse>
     */
    public function listAll(?string $status = null): array
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $query = $this->leaveRequestModel;
        if ($status !== null && $status !== '') {
            $query = $query->where('status', $status);
        }

        return array_map(
            fn (LeaveRequest $leaveRequest): LeaveRequestResponse => $this->buildResponse($leaveRequest),
            $query->findAll(),
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
            fn (LeaveRequest $leaveRequest): LeaveRequestResponse => $this->buildResponse($leaveRequest),
            $this->leaveRequestModel->findApprovedOverlapping($employeeId, $fromDate, $toDate),
        );
    }

    /**
     * Read-only balance visibility for the given calendar year.
     * Loops over ALL active leave types from DB (dynamic, per-school).
     * Each leave type uses its own sandwich_rule (or inherits global).
     *
     * @return array<string, array{allocation: int, consumed: int, remaining: int, name: string, is_paid: int}>
     */
    public function getBalances(int $employeeId, int $year): array
    {
        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'EMPLOYEE', $employeeId);

        if ($this->employeeModel->find($employeeId) === null) {
            throw new BusinessRuleException('EMPLOYEE_NOT_FOUND', 'Employee not found.');
        }

        $balances   = [];
        $yearHolidays = $this->getHolidaysForYear($year);

        try {
            $leaveTypes = $this->leaveTypeModel->findActive();
        } catch (\Throwable) {
            $leaveTypes = [];
        }

        foreach ($leaveTypes as $lt) {
            $code       = $lt['code'];
            $allocation = (int) $lt['max_days_per_year'];  // 0 = unlimited
            $noLimit    = ! $lt['balance_check'] || $allocation === 0;

            // Per-type sandwich rule override
            $sandwichRule = $lt['sandwich_rule'] !== null
                ? (bool) $lt['sandwich_rule']
                : $this->getGlobalSandwichRule();

            $holidays = $sandwichRule ? [] : $yearHolidays;
            $consumed = $this->leaveRequestModel->sumApprovedDaysByEmployeeTypeYear($employeeId, $code, $year, $sandwichRule, $holidays);

            $balances[$code] = [
                'name'       => $lt['name'],
                'is_paid'    => (int) $lt['is_paid'],
                'allocation' => $noLimit ? 999 : $allocation,
                'consumed'   => $consumed,
                'remaining'  => $noLimit ? 999 : max(0, $allocation - $consumed),
                'no_limit'   => $noLimit,
                'color_hex'  => $lt['color_hex'] ?? '#6366f1',
            ];
        }

        return $balances;
    }

    private function projectedBalanceAfter(LeaveRequest $leaveRequest): int
    {
        // Fetch leave type config from DB
        $leaveTypeConfig = null;
        try {
            $leaveTypeConfig = $this->leaveTypeModel->findByCode($leaveRequest->leave_type);
        } catch (\Throwable) {
            // table may not exist in isolated test DBs
        }

        // If balance_check is disabled OR no leave type config, allow unlimited
        if ($leaveTypeConfig === null || ! $leaveTypeConfig['balance_check']) {
            return 0; // treat as unlimited — always pass balance check
        }

        $maxDays = (int) $leaveTypeConfig['max_days_per_year'];
        if ($maxDays === 0) {
            return 0; // explicit unlimited
        }

        $year = (int) (new \DateTimeImmutable((string) $leaveRequest->start_date))->format('Y');

        // Per-type sandwich rule
        $sandwichRule = $leaveTypeConfig['sandwich_rule'] !== null
            ? (bool) $leaveTypeConfig['sandwich_rule']
            : $this->getGlobalSandwichRule();

        $holidays    = $sandwichRule ? [] : $this->getHolidaysForYear($year);
        $consumed    = $this->leaveRequestModel->sumApprovedDaysByEmployeeTypeYear($leaveRequest->employee_id, $leaveRequest->leave_type, $year, $sandwichRule, $holidays);
        $thisRequest = LeaveRequestModel::calculateDays((string) $leaveRequest->start_date, (string) $leaveRequest->end_date, $sandwichRule, $holidays);

        return $maxDays - $consumed - $thisRequest;
    }

    public function cancelLeaveRequest(int $id, string $reason): LeaveRequestResponse
    {
        $leaveRequest = $this->requireLeaveRequest($id);

        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'EMPLOYEE', $leaveRequest->employee_id);

        if ($leaveRequest->status !== LeaveRequest::STATUS_PENDING) {
            throw new BusinessRuleException(
                'LEAVE_REQUEST_NOT_PENDING',
                'Only pending leave requests can be cancelled.'
            );
        }

        $this->leaveRequestModel->update($id, [
            'status' => LeaveRequest::STATUS_CANCELLED,
            'reason' => trim($leaveRequest->reason . "\n[Cancelled: " . $reason . "]"),
        ]);

        $after = $this->leaveRequestModel->find($id);

        $this->auditService->record('LeaveRequest', $id, AuditLog::ACTION_UPDATE, $leaveRequest->toRawArray(), $after->toRawArray(), 'Cancelled: ' . $reason);

        // Notify HR Managers
        try {
            $db = \Config\Database::connect();
            $hrUsers = $db->table('users')
                ->select('users.owner_ref_id')
                ->join('roles', 'users.role_id = roles.role_id')
                ->where('users.owner_type', 'EMPLOYEE')
                ->like('roles.permission_set', 'hr_payroll.manage')
                ->get()
                ->getResultArray();

            $notificationService = \Config\Services::notificationLogService();
            $employee = $this->employeeModel->find($leaveRequest->employee_id);
            $empName = $employee !== null ? $employee->full_name : "Staff member";

            foreach ($hrUsers as $hrUser) {
                $response = $notificationService->createInternal(new \App\Modules\Communication\DTOs\CreateNotificationLogRequest(
                    \App\Modules\Communication\Entities\NotificationLog::RECIPIENT_EMPLOYEE,
                    (int) $hrUser['owner_ref_id'],
                    \App\Modules\Communication\Entities\NotificationLog::CHANNEL_SMS,
                    'Leave Request Cancelled',
                    "Leave request submitted by {$empName} has been cancelled by the employee. Reason: {$reason}."
                ));
                $notificationService->dispatchInternal($response->notificationLogId);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to dispatch leave cancellation notification: ' . $e->getMessage());
        }

        return $this->buildResponse($after);
    }

    private function buildResponse(LeaveRequest $lr): LeaveRequestResponse
    {
        $startDate = new \DateTimeImmutable((string) $lr->start_date);
        $endDate = new \DateTimeImmutable((string) $lr->end_date);
        $appliedDays = (int) $startDate->diff($endDate)->format('%a') + 1;

        $leaveTypeConfig = null;
        try {
            $leaveTypeConfig = $this->leaveTypeModel->findByCode($lr->leave_type);
        } catch (\Throwable $e) {}

        $sandwichRule = $leaveTypeConfig !== null && $leaveTypeConfig['sandwich_rule'] !== null
            ? (bool) $leaveTypeConfig['sandwich_rule']
            : $this->getGlobalSandwichRule();

        $year = (int) $startDate->format('Y');
        $holidays = $sandwichRule ? [] : $this->getHolidaysForYear($year);
        $deductibleDays = LeaveRequestModel::calculateDays((string) $lr->start_date, (string) $lr->end_date, $sandwichRule, $holidays);

        return new LeaveRequestResponse($lr, $appliedDays, $deductibleDays);
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
