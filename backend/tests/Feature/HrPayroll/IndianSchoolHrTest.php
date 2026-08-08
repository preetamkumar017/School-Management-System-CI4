<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\HrPayroll\Entities\Employee;
use App\Modules\HrPayroll\Entities\LeaveRequest;
use Config\Services;
use Tests\Support\Administration\AdministrationTestCase;

/**
 * Feature tests for Indian School HRMS extensions (staff classification, KYC, LWP, Duty Leave, Payslip earnings).
 */
class IndianSchoolHrTest extends AdministrationTestCase
{
    private int $departmentId;
    private int $designationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->departmentId  = (int) $this->db->table('departments')->insert(['department_name' => 'Academics ' . uniqid('', true)], true);
        $this->designationId = (int) $this->db->table('designations')->insert(['designation_name' => 'PGT Teacher ' . uniqid('', true)], true);

        // Seed permission and login
        $roleId = $this->createRole(['hr_payroll.manage', 'attendance.manage']);
        $user   = $this->createUser($roleId);
        $tokens = $this->loginAs($user['username']);
        $this->withHeaders(['Authorization' => 'Bearer ' . $tokens['access_token']]);
    }

    public function testCreateEmployeeWithIndianSchoolKycAndStaffType(): void
    {
        $employeeCode = 'EMP-' . rand(1000, 9999);

        $result = $this->post('/api/v1/hr-payroll/employees', [
            'employee_code'         => $employeeCode,
            'full_name'             => 'Ramesh Kumar',
            'department_id'         => $this->departmentId,
            'designation_id'        => $this->designationId,
            'joining_date'          => '2026-04-01',
            'salary_structure_json' => ['basic' => 45000, 'hra' => 18000, 'da' => 9000],
            'staff_type'            => Employee::STAFF_TYPE_TEACHING,
            'qualification'         => 'M.Sc Physics, B.Ed, CTET Qualified',
            'aadhaar_number'        => '123456789012',
            'pan_number'            => 'ABCDE1234F',
            'pf_uan'                => '100900800700',
            'esi_number'            => '31001234567890001',
            'bank_name'             => 'State Bank of India',
            'bank_account_number'   => '300123456789',
            'bank_ifsc_code'        => 'SBIN0001234',
            'probation_end_date'    => '2026-10-01',
            'confirmation_date'     => '2026-10-02',
        ]);

        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true)['data'];
        $this->assertSame($employeeCode, $json['employee_code']);
        $this->assertSame('Ramesh Kumar', $json['full_name']);
        $this->assertSame(Employee::STAFF_TYPE_TEACHING, $json['staff_type']);
        $this->assertSame('M.Sc Physics, B.Ed, CTET Qualified', $json['qualification']);
        $this->assertSame('123456789012', $json['aadhaar_number']);
        $this->assertSame('ABCDE1234F', $json['pan_number']);
        $this->assertSame('100900800700', $json['pf_uan']);
        $this->assertSame('31001234567890001', $json['esi_number']);
        $this->assertSame('State Bank of India', $json['bank_name']);
        $this->assertSame('300123456789', $json['bank_account_number']);
        $this->assertSame('SBIN0001234', $json['bank_ifsc_code']);
    }

    public function testCreateSchoolLeaveTypesMaternityAndDutyLeave(): void
    {
        $employeeCode = 'EMP-' . rand(1000, 9999);
        $empResult    = $this->post('/api/v1/hr-payroll/employees', [
            'employee_code'         => $employeeCode,
            'full_name'             => 'Sunita Sharma',
            'department_id'         => $this->departmentId,
            'designation_id'        => $this->designationId,
            'joining_date'          => '2026-01-01',
            'salary_structure_json' => ['basic' => 40000],
        ]);
        $empId = json_decode($empResult->getJSON(), true)['data']['employee_id'];

        // Maternity Leave
        $mlResult = $this->post('/api/v1/hr-payroll/leave-requests', [
            'employee_id' => $empId,
            'leave_type'  => LeaveRequest::TYPE_ML,
            'start_date'  => '2026-09-01',
            'end_date'    => '2026-11-30',
            'reason'      => 'Maternity leave requested for 90 days',
        ]);
        $mlResult->assertStatus(201);

        $mlJson = json_decode($mlResult->getJSON(), true)['data'];
        $this->assertSame(LeaveRequest::TYPE_ML, $mlJson['leave_type']);
        $this->assertSame('Maternity leave requested for 90 days', $mlJson['reason']);

        // Duty Leave
        $dlResult = $this->post('/api/v1/hr-payroll/leave-requests', [
            'employee_id'          => $empId,
            'leave_type'           => LeaveRequest::TYPE_DL,
            'start_date'           => '2026-08-10',
            'end_date'             => '2026-08-12',
            'reason'               => 'CBSE Board Examination Evaluation Duty',
            'duty_leave_reference' => 'CBSE/EVAL/2026/ORDER-992',
        ]);
        $dlResult->assertStatus(201);

        $dlJson = json_decode($dlResult->getJSON(), true)['data'];
        $this->assertSame(LeaveRequest::TYPE_DL, $dlJson['leave_type']);
        $this->assertSame('CBSE/EVAL/2026/ORDER-992', $dlJson['duty_leave_reference']);
    }

    public function testCreatePayrollRunWithEarningsAndLwp(): void
    {
        $employeeCode = 'EMP-' . rand(1000, 9999);
        $empResult    = $this->post('/api/v1/hr-payroll/employees', [
            'employee_code'         => $employeeCode,
            'full_name'             => 'Vikram Singh',
            'department_id'         => $this->departmentId,
            'designation_id'        => $this->designationId,
            'joining_date'          => '2026-01-01',
            'salary_structure_json' => ['basic' => 50000],
        ]);
        $empId = json_decode($empResult->getJSON(), true)['data']['employee_id'];

        $payPeriod = '2026-08';
        Services::employeeService()->recordAttendanceClosure($empId, $payPeriod, 1);

        $payrollResult = $this->post('/api/v1/hr-payroll/payroll-runs', [
            'employee_id'     => $empId,
            'pay_period'      => $payPeriod,
            'gross_pay'       => 60000.00,
            'deductions_json' => ['PF' => 4800, 'ESI' => 450, 'PT' => 200],
            'earnings_json'   => ['Basic' => 40000, 'HRA' => 12000, 'DA' => 8000],
            'lwp_days'        => 2,
        ]);
        $payrollResult->assertStatus(201);

        $payrollJson = json_decode($payrollResult->getJSON(), true)['data'];
        $this->assertSame(2, $payrollJson['lwp_days']);
        $this->assertSame(60000, $payrollJson['gross_pay']);
        $this->assertSame(54550, $payrollJson['net_pay']);
        $this->assertIsArray($payrollJson['earnings_json']);
        $this->assertSame(40000, $payrollJson['earnings_json']['Basic']);
    }
}
