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
    protected function setUp(): void
    {
        parent::setUp();

        // Seed default leave types for test execution
        $db = \Config\Database::connect();
        $leaveTypes = [
            [
                'code'              => 'CL',
                'name'              => 'Casual Leave',
                'description'       => 'Casual Leave',
                'max_days_per_year' => 12,
                'is_paid'           => 1,
                'balance_check'     => 1,
                'sandwich_rule'     => null,
                'color_hex'         => '#3b82f6',
                'sort_order'        => 1,
                'is_active'         => 1,
            ],
            [
                'code'              => 'SL',
                'name'              => 'Sick Leave',
                'description'       => 'Sick Leave',
                'max_days_per_year' => 10,
                'is_paid'           => 1,
                'balance_check'     => 1,
                'sandwich_rule'     => null,
                'color_hex'         => '#f59e0b',
                'sort_order'        => 2,
                'is_active'         => 1,
            ],
            [
                'code'              => 'EL',
                'name'              => 'Earned Leave',
                'description'       => 'Earned Leave',
                'max_days_per_year' => 15,
                'is_paid'           => 1,
                'balance_check'     => 1,
                'sandwich_rule'     => null,
                'color_hex'         => '#10b981',
                'sort_order'        => 3,
                'is_active'         => 1,
            ],
            [
                'code'              => 'ML',
                'name'              => 'Maternity Leave',
                'description'       => 'Maternity Leave',
                'max_days_per_year' => 180,
                'is_paid'           => 1,
                'balance_check'     => 0,
                'sandwich_rule'     => null,
                'color_hex'         => '#ec4899',
                'sort_order'        => 4,
                'is_active'         => 1,
            ],
            [
                'code'              => 'LWP',
                'name'              => 'Leave Without Pay',
                'description'       => 'Leave Without Pay',
                'max_days_per_year' => 0,
                'is_paid'           => 0,
                'balance_check'     => 0,
                'sandwich_rule'     => null,
                'color_hex'         => '#ef4444',
                'sort_order'        => 5,
                'is_active'         => 1,
            ],
            [
                'code'              => 'DL',
                'name'              => 'Duty Leave',
                'description'       => 'Duty Leave',
                'max_days_per_year' => 0,
                'is_paid'           => 1,
                'balance_check'     => 0,
                'sandwich_rule'     => null,
                'color_hex'         => '#8b5cf6',
                'sort_order'        => 6,
                'is_active'         => 1,
            ],
        ];

        foreach ($leaveTypes as $lt) {
            $db->table('leave_types')->insert($lt);
        }
    }

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
