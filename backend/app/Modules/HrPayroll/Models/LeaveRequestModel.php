<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;
use App\Modules\HrPayroll\Entities\LeaveRequest;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 */
class LeaveRequestModel extends BaseModel
{
    protected $table          = 'leave_requests';
    protected $primaryKey     = 'leave_request_id';
    protected $returnType     = LeaveRequest::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'reason',
        'duty_leave_reference',
        'status',
        'approver_id',
        'created_by',
        'updated_by',
    ];

    /**
     * @return list<LeaveRequest>
     */
    public function findByEmployeeId(int $employeeId): array
    {
        return $this->where('employee_id', $employeeId)->findAll();
    }

    /**
     * BR-HR-004 (ADR-008 §7): sum of day-counts across this employee's
     * Approved requests of the given type whose start_date falls in the
     * given calendar year.
     */
    public function sumApprovedDaysByEmployeeTypeYear(int $employeeId, string $leaveType, int $year): int
    {
        $rows = $this->where('employee_id', $employeeId)
            ->where('leave_type', $leaveType)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('YEAR(start_date)', $year)
            ->findAll();

        $days = 0;

        foreach ($rows as $row) {
            $days += (new \DateTimeImmutable((string) $row->start_date))
                ->diff(new \DateTimeImmutable((string) $row->end_date))->days + 1;
        }

        return $days;
    }

    public function existsOverlappingApproved(int $employeeId, string $startDate, string $endDate): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date <=', $endDate)
            ->where('end_date >=', $startDate)
            ->countAllResults() > 0;
    }

    /**
     * @return list<LeaveRequest>
     */
    public function findApprovedOverlapping(int $employeeId, string $fromDate, string $toDate): array
    {
        return $this->where('employee_id', $employeeId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date <=', $toDate)
            ->where('end_date >=', $fromDate)
            ->findAll();
    }
}
