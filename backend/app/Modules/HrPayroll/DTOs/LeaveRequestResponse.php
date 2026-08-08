<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

use App\Modules\HrPayroll\Entities\LeaveRequest;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 */
final class LeaveRequestResponse
{
    public readonly int $leaveRequestId;
    public readonly int $employeeId;
    public readonly string $leaveType;
    public readonly string $startDate;
    public readonly string $endDate;
    public readonly ?string $reason;
    public readonly ?string $dutyLeaveReference;
    public readonly string $status;
    public readonly ?int $approverId;

    public function __construct(LeaveRequest $leaveRequest)
    {
        $this->leaveRequestId     = $leaveRequest->leave_request_id;
        $this->employeeId         = $leaveRequest->employee_id;
        $this->leaveType          = $leaveRequest->leave_type;
        $this->startDate          = $leaveRequest->start_date;
        $this->endDate            = $leaveRequest->end_date;
        $this->reason             = $leaveRequest->reason;
        $this->dutyLeaveReference = $leaveRequest->duty_leave_reference;
        $this->status             = $leaveRequest->status;
        $this->approverId         = $leaveRequest->approver_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'leave_request_id'     => $this->leaveRequestId,
            'employee_id'          => $this->employeeId,
            'leave_type'           => $this->leaveType,
            'start_date'           => $this->startDate,
            'end_date'             => $this->endDate,
            'reason'               => $this->reason,
            'duty_leave_reference' => $this->dutyLeaveReference,
            'status'               => $this->status,
            'approver_id'          => $this->approverId,
        ];
    }
}
