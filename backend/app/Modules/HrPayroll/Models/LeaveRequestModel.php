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
    /**
     * BR-HR-004 (ADR-008 §7): sum of day-counts across this employee's
     * Approved requests of the given type whose start_date falls in the
     * given calendar year.
     */
    public function sumApprovedDaysByEmployeeTypeYear(int $employeeId, string $leaveType, int $year, bool $sandwichRule = false, array $holidays = []): int
    {
        $rows = $this->where('employee_id', $employeeId)
            ->where('leave_type', $leaveType)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('YEAR(start_date)', $year)
            ->findAll();

        $days = 0;

        foreach ($rows as $row) {
            $days += self::calculateDays((string) $row->start_date, (string) $row->end_date, $sandwichRule, $holidays);
        }

        return $days;
    }


    /**
     * Calculate the number of days for a leave request.
     *
     * @param string        $startDate    Y-m-d
     * @param string        $endDate      Y-m-d
     * @param bool          $sandwichRule true  → count all calendar days (incl. Sundays & holidays)
     *                                    false → count only working days (skip Sundays & $holidays)
     * @param list<string>  $holidays     Date strings to skip when sandwichRule=false  e.g. ['2026-08-15']
     */
    public static function calculateDays(
        string $startDate,
        string $endDate,
        bool $sandwichRule = true,
        array $holidays = []
    ): int {
        $start = new \DateTimeImmutable($startDate);
        $end   = new \DateTimeImmutable($endDate);

        if ($sandwichRule) {
            return $start->diff($end)->days + 1;
        }

        $holidaySet = array_flip($holidays); // O(1) lookup
        $days    = 0;
        $current = $start;

        while ($current <= $end) {
            $dayOfWeek = (int) $current->format('w');
            $dateStr   = $current->format('Y-m-d');

            // Skip Sundays (0) and holidays
            if ($dayOfWeek !== 0 && ! isset($holidaySet[$dateStr])) {
                $days++;
            }
            $current = $current->modify('+1 day');
        }

        return max(1, $days);
    }


    public function existsOverlappingApproved(int $employeeId, string $startDate, string $endDate): bool
    {
        return $this->where('employee_id', $employeeId)
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_PENDING])
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
