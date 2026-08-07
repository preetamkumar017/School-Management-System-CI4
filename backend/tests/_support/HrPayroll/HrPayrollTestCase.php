<?php

declare(strict_types=1);

namespace Tests\Support\HrPayroll;

use App\Modules\HrPayroll\Models\AttendanceClosureModel;
use App\Modules\HrPayroll\Models\DepartmentModel;
use App\Modules\HrPayroll\Models\DesignationModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Modules\HrPayroll\Models\LeaveRequestModel;
use App\Modules\HrPayroll\Models\PayrollRunModel;
use CodeIgniter\I18n\Time;
use Tests\Support\Fees\FeesTestCase;

/**
 * @internal
 */
abstract class HrPayrollTestCase extends FeesTestCase
{
    protected function createDepartmentFixture(?string $name = null): int
    {
        return (new DepartmentModel())->insert(['department_name' => $name ?? ('Dept ' . uniqid('', true))], true);
    }

    protected function createDesignationFixture(?string $name = null): int
    {
        return (new DesignationModel())->insert(['designation_name' => $name ?? ('Desig ' . uniqid('', true))], true);
    }

    protected function createEmployeeFixture(
        ?int $departmentId = null,
        ?int $designationId = null,
        string $status = 'Active',
        ?string $exitDate = null,
    ): int {
        return (new EmployeeModel())->insert([
            'employee_code'          => 'EMP-' . random_int(100000, 999999),
            'full_name'              => 'Employee ' . uniqid('', true),
            'department_id'          => $departmentId ?? $this->createDepartmentFixture(),
            'designation_id'         => $designationId ?? $this->createDesignationFixture(),
            'joining_date'           => '2020-01-01',
            'exit_date'              => $exitDate,
            'salary_structure_json'  => ['basic' => 30000],
            'status'                 => $status,
        ], true);
    }

    protected function createAttendanceClosureFixture(int $employeeId, string $payPeriod): int
    {
        return (new AttendanceClosureModel())->insert([
            'employee_id' => $employeeId,
            'pay_period'  => $payPeriod,
            'closed_at'   => Time::now()->toDateTimeString(),
            'closed_by'   => 1,
        ], true);
    }

    protected function createPayrollRunFixture(
        ?int $employeeId = null,
        string $payPeriod = '2026-07',
        float $grossPay = 50000.0,
        string $status = 'Draft',
    ): int {
        return (new PayrollRunModel())->insert([
            'employee_id'     => $employeeId ?? $this->createEmployeeFixture(),
            'pay_period'      => $payPeriod,
            'gross_pay'       => $grossPay,
            'deductions_json' => ['PF' => 1800.0],
            'net_pay'         => $grossPay - 1800.0,
            'status'          => $status,
        ], true);
    }

    protected function createLeaveRequestFixture(
        ?int $employeeId = null,
        string $leaveType = 'CL',
        string $startDate = '2026-01-05',
        string $endDate = '2026-01-05',
        string $status = 'Pending',
    ): int {
        return (new LeaveRequestModel())->insert([
            'employee_id' => $employeeId ?? $this->createEmployeeFixture(),
            'leave_type'  => $leaveType,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'status'      => $status,
        ], true);
    }
}
