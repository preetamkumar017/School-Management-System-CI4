<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DevDataSeeder extends Seeder
{
    public function run(): void
    {
        $db = $this->db;

        // 1. Seed Roles
        $adminRoleId = $db->table('roles')->where('role_name', 'IT Admin')->get()->getRow('role_id');
        if ($adminRoleId === null) {
            $adminRoleId = $db->table('roles')->insert([
                'role_name'      => 'IT Admin',
                'description'    => 'Full IT Administrator',
                'is_system_role' => true,
                'permission_set' => json_encode([
                    'academic.manage',
                    'admission.manage',
                    'sis.manage',
                    'examination.manage',
                    'timetable.manage',
                    'attendance.manage',
                    'fees.manage',
                    'hr_payroll.manage',
                    'library.manage',
                    'transport.manage',
                    'communication.manage',
                    'administration.manage',
                    'reports.manage',
                ]),
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ], true);
        } else {
            $db->table('roles')->where('role_id', $adminRoleId)->update([
                'permission_set' => json_encode([
                    'academic.manage',
                    'admission.manage',
                    'sis.manage',
                    'examination.manage',
                    'timetable.manage',
                    'attendance.manage',
                    'fees.manage',
                    'hr_payroll.manage',
                    'library.manage',
                    'transport.manage',
                    'communication.manage',
                    'administration.manage',
                    'reports.manage',
                ]),
            ]);
        }

        $employeeRoleId = $db->table('roles')->where('role_name', 'Employee')->get()->getRow('role_id');
        if ($employeeRoleId === null) {
            $employeeRoleId = $db->table('roles')->insert([
                'role_name'      => 'Employee',
                'description'    => 'General Staff / Employee',
                'is_system_role' => false,
                'permission_set' => json_encode(['read']),
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ], true);
        }

        // 2. Seed Departments
        $depts = [
            'Academics'          => null,
            'Administration'     => null,
            'Accounts'           => null,
            'IT & Support'       => null,
            'Sports & Facilities' => null,
        ];
        foreach (array_keys($depts) as $name) {
            $row = $db->table('departments')->where('department_name', $name)->get()->getRow();
            if ($row === null) {
                $depts[$name] = (int) $db->table('departments')->insert(['department_name' => $name], true);
            } else {
                $depts[$name] = (int) $row->department_id;
            }
        }

        // 3. Seed Designations
        $desigs = [
            'Principal'           => null,
            'PGT Physics Teacher' => null,
            'TGT Science Teacher' => null,
            'PRT English Teacher' => null,
            'Senior Accountant'   => null,
            'Sports Coach'        => null,
            'Transport Incharge'  => null,
        ];
        foreach (array_keys($desigs) as $name) {
            $row = $db->table('designations')->where('designation_name', $name)->get()->getRow();
            if ($row === null) {
                $desigs[$name] = (int) $db->table('designations')->insert(['designation_name' => $name], true);
            } else {
                $desigs[$name] = (int) $row->designation_id;
            }
        }

        // 4. Seed Employees
        $employeeData = [
            [
                'code'          => 'EMP-1001',
                'name'          => 'Ramesh Kumar',
                'dept_id'       => $depts['Academics'],
                'desig_id'      => $desigs['PGT Physics Teacher'],
                'type'          => 'Teaching',
                'qualification' => 'M.Sc Physics, B.Ed, CTET',
                'aadhaar'       => '123456789012',
                'pan'           => 'ABCDE1234F',
                'pf'            => '100900800700',
                'esi'           => '31001234567890001',
                'bank'          => 'State Bank of India',
                'account'       => '300123456789',
                'ifsc'          => 'SBIN0001234',
                'joining'       => '2025-04-01',
                'salary'        => ['basic' => 45000, 'hra' => 18000, 'da' => 9000],
            ],
            [
                'code'          => 'EMP-1002',
                'name'          => 'Sunita Sharma',
                'dept_id'       => $depts['Academics'],
                'desig_id'      => $desigs['TGT Science Teacher'],
                'type'          => 'Teaching',
                'qualification' => 'B.Sc Chemistry, B.Ed',
                'aadhaar'       => '987654321012',
                'pan'           => 'XYZPK9876Q',
                'pf'            => '100900800701',
                'esi'           => '31001234567890002',
                'bank'          => 'HDFC Bank',
                'account'       => '501002345678',
                'ifsc'          => 'HDFC0000123',
                'joining'       => '2025-06-15',
                'salary'        => ['basic' => 35000, 'hra' => 14000, 'da' => 7000],
            ],
            [
                'code'          => 'EMP-1003',
                'name'          => 'Vikram Singh',
                'dept_id'       => $depts['Accounts'],
                'desig_id'      => $desigs['Senior Accountant'],
                'type'          => 'NonTeaching',
                'qualification' => 'M.Com, Tally Prime Certified',
                'aadhaar'       => '456789123012',
                'pan'           => 'LMNPR4567S',
                'pf'            => '100900800702',
                'esi'           => '31001234567890003',
                'bank'          => 'ICICI Bank',
                'account'       => '000401567890',
                'ifsc'          => 'ICIC0000004',
                'joining'       => '2024-01-10',
                'salary'        => ['basic' => 40000, 'hra' => 16000],
            ],
            [
                'code'          => 'EMP-1004',
                'name'          => 'Rajesh Verma',
                'dept_id'       => $depts['Administration'],
                'desig_id'      => $desigs['Transport Incharge'],
                'type'          => 'Support',
                'qualification' => 'Higher Secondary, Heavy Motor Driving License',
                'aadhaar'       => '678912345012',
                'pan'           => 'PQRTV6789W',
                'pf'            => '100900800703',
                'esi'           => '31001234567890004',
                'bank'          => 'Punjab National Bank',
                'account'       => '110200123456',
                'ifsc'          => 'PUNB0110200',
                'joining'       => '2023-08-01',
                'salary'        => ['basic' => 25000, 'hra' => 10000],
            ],
            [
                'code'          => 'EMP-1005',
                'name'          => 'Ananya Gupta',
                'dept_id'       => $depts['Academics'],
                'desig_id'      => $desigs['PRT English Teacher'],
                'type'          => 'Teaching',
                'qualification' => 'B.A English Literature, B.Ed',
                'aadhaar'       => '345678912012',
                'pan'           => 'JKLMA3456B',
                'pf'            => '100900800704',
                'esi'           => '31001234567890005',
                'bank'          => 'Axis Bank',
                'account'       => '912010012345',
                'ifsc'          => 'UTIB0000123',
                'joining'       => '2025-09-01',
                'salary'        => ['basic' => 30000, 'hra' => 12000],
            ],
        ];

        $empIds = [];
        foreach ($employeeData as $data) {
            $row = $db->table('employees')->where('employee_code', $data['code'])->get()->getRow();
            if ($row === null) {
                $id = (int) $db->table('employees')->insert([
                    'employee_code'         => $data['code'],
                    'full_name'             => $data['name'],
                    'department_id'         => $data['dept_id'],
                    'designation_id'        => $data['desig_id'],
                    'staff_type'            => $data['type'],
                    'qualification'         => $data['qualification'],
                    'aadhaar_number'        => $data['aadhaar'],
                    'pan_number'            => $data['pan'],
                    'pf_uan'                => $data['pf'],
                    'esi_number'            => $data['esi'],
                    'bank_name'             => $data['bank'],
                    'bank_account_number'   => $data['account'],
                    'bank_ifsc_code'        => $data['ifsc'],
                    'joining_date'          => $data['joining'],
                    'salary_structure_json' => json_encode($data['salary']),
                    'status'                => 'Active',
                    'created_at'            => date('Y-m-d H:i:s'),
                    'updated_at'            => date('Y-m-d H:i:s'),
                ], true);
            } else {
                $id = (int) $row->employee_id;
                $db->table('employees')->where('employee_id', $id)->update([
                    'staff_type'            => $data['type'],
                    'qualification'         => $data['qualification'],
                    'aadhaar_number'        => $data['aadhaar'],
                    'pan_number'            => $data['pan'],
                    'pf_uan'                => $data['pf'],
                    'esi_number'            => $data['esi'],
                    'bank_name'             => $data['bank'],
                    'bank_account_number'   => $data['account'],
                    'bank_ifsc_code'        => $data['ifsc'],
                ]);
            }
            $empIds[$data['code']] = $id;
        }

        // 5. Seed Users
        $adminUser = $db->table('users')->where('username', 'admin')->get()->getRow();
        if ($adminUser === null) {
            $db->table('users')->insert([
                'username'      => 'admin',
                'password_hash' => password_hash('Admin@1234', PASSWORD_BCRYPT),
                'role_id'       => $adminRoleId,
                'owner_type'    => 'EMPLOYEE',
                'owner_ref_id'  => $empIds['EMP-1001'],
                'status'        => 'ACTIVE',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('users')->where('username', 'admin')->update([
                'password_hash' => password_hash('Admin@1234', PASSWORD_BCRYPT),
                'role_id'       => $adminRoleId,
                'owner_type'    => 'EMPLOYEE',
                'owner_ref_id'  => $empIds['EMP-1001'],
                'status'        => 'ACTIVE',
            ]);
        }

        $priyaUser = $db->table('users')->where('username', 'priya.iyer')->get()->getRow();
        if ($priyaUser === null) {
            $db->table('users')->insert([
                'username'      => 'priya.iyer',
                'password_hash' => password_hash('Employee@1234', PASSWORD_BCRYPT),
                'role_id'       => $employeeRoleId,
                'owner_type'    => 'EMPLOYEE',
                'owner_ref_id'  => $empIds['EMP-1002'],
                'status'        => 'ACTIVE',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('users')->where('username', 'priya.iyer')->update([
                'password_hash' => password_hash('Employee@1234', PASSWORD_BCRYPT),
                'role_id'       => $employeeRoleId,
                'owner_type'    => 'EMPLOYEE',
                'owner_ref_id'  => $empIds['EMP-1002'],
                'status'        => 'ACTIVE',
            ]);
        }

        // 6. Seed Attendance Closures
        $periods = ['2026-07', '2026-08'];
        foreach ($empIds as $empId) {
            foreach ($periods as $period) {
                $closure = $db->table('attendance_closures')
                    ->where('employee_id', $empId)
                    ->where('pay_period', $period)
                    ->get()->getRow();
                if ($closure === null) {
                    $db->table('attendance_closures')->insert([
                        'employee_id' => $empId,
                        'pay_period'  => $period,
                        'closed_at'   => date('Y-m-d H:i:s'),
                        'closed_by'   => 1,
                    ]);
                }
            }
        }

        // 7. Seed Leave Requests
        $leaveSeeds = [
            [
                'emp_id' => $empIds['EMP-1002'], // Sunita Sharma
                'type'   => 'CL',
                'start'  => '2026-07-10',
                'end'    => '2026-07-11',
                'reason' => 'Family function at hometown',
                'status' => 'Approved',
            ],
            [
                'emp_id' => $empIds['EMP-1001'], // Ramesh Kumar
                'type'   => 'DL',
                'start'  => '2026-08-10',
                'end'    => '2026-08-12',
                'reason' => 'CBSE Board Examination Evaluation Duty',
                'ref'    => 'CBSE/EVAL/2026/ORDER-992',
                'status' => 'Pending',
            ],
            [
                'emp_id' => $empIds['EMP-1005'], // Ananya Gupta
                'type'   => 'ML',
                'start'  => '2026-09-01',
                'end'    => '2026-11-30',
                'reason' => 'Maternity Leave requested for 90 days',
                'status' => 'Approved',
            ],
            [
                'emp_id' => $empIds['EMP-1003'], // Vikram Singh
                'type'   => 'SL',
                'start'  => '2026-08-01',
                'end'    => '2026-08-02',
                'reason' => 'Fever and doctor recommended rest',
                'status' => 'Pending',
            ],
            [
                'emp_id' => $empIds['EMP-1004'], // Rajesh Verma
                'type'   => 'LWP',
                'start'  => '2026-08-05',
                'end'    => '2026-08-06',
                'reason' => 'Personal emergency leave without pay',
                'status' => 'Approved',
            ],
        ];

        foreach ($leaveSeeds as $l) {
            $exists = $db->table('leave_requests')
                ->where('employee_id', $l['emp_id'])
                ->where('start_date', $l['start'])
                ->get()->getRow();
            if ($exists === null) {
                $db->table('leave_requests')->insert([
                    'employee_id'          => $l['emp_id'],
                    'leave_type'           => $l['type'],
                    'start_date'           => $l['start'],
                    'end_date'             => $l['end'],
                    'reason'               => $l['reason'],
                    'duty_leave_reference' => $l['ref'] ?? null,
                    'status'               => $l['status'],
                    'created_at'           => date('Y-m-d H:i:s'),
                    'updated_at'           => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // 8. Seed Payroll Runs
        $payrollSeeds = [
            [
                'emp_id'     => $empIds['EMP-1001'],
                'period'     => '2026-07',
                'lwp'        => 0,
                'gross'      => 72000.00,
                'earnings'   => ['Basic' => 45000, 'HRA' => 18000, 'DA' => 9000],
                'deductions' => ['PF' => 5400, 'ESI' => 500, 'PT' => 200, 'TDS' => 600],
                'net'        => 65300.00,
                'status'     => 'Processed',
            ],
            [
                'emp_id'     => $empIds['EMP-1002'],
                'period'     => '2026-07',
                'lwp'        => 0,
                'gross'      => 56000.00,
                'earnings'   => ['Basic' => 35000, 'HRA' => 14000, 'DA' => 7000],
                'deductions' => ['PF' => 4200, 'ESI' => 450, 'PT' => 200, 'TDS' => 400],
                'net'        => 50750.00,
                'status'     => 'Processed',
            ],
            [
                'emp_id'     => $empIds['EMP-1001'],
                'period'     => '2026-08',
                'lwp'        => 0,
                'gross'      => 72000.00,
                'earnings'   => ['Basic' => 45000, 'HRA' => 18000, 'DA' => 9000],
                'deductions' => ['PF' => 5400, 'ESI' => 500, 'PT' => 200, 'TDS' => 600],
                'net'        => 65300.00,
                'status'     => 'Processed',
            ],
            [
                'emp_id'     => $empIds['EMP-1002'],
                'period'     => '2026-08',
                'lwp'        => 0,
                'gross'      => 56000.00,
                'earnings'   => ['Basic' => 35000, 'HRA' => 14000, 'DA' => 7000],
                'deductions' => ['PF' => 4200, 'ESI' => 450, 'PT' => 200, 'TDS' => 400],
                'net'        => 50750.00,
                'status'     => 'Approved',
            ],
            [
                'emp_id'     => $empIds['EMP-1004'],
                'period'     => '2026-08',
                'lwp'        => 2,
                'gross'      => 35000.00,
                'earnings'   => ['Basic' => 25000, 'HRA' => 10000],
                'deductions' => ['PF' => 3000, 'ESI' => 300, 'PT' => 200],
                'net'        => 31500.00,
                'status'     => 'Draft',
            ],
        ];

        foreach ($payrollSeeds as $p) {
            $exists = $db->table('payroll_runs')
                ->where('employee_id', $p['emp_id'])
                ->where('pay_period', $p['period'])
                ->get()->getRow();
            if ($exists === null) {
                $db->table('payroll_runs')->insert([
                    'employee_id'     => $p['emp_id'],
                    'pay_period'      => $p['period'],
                    'lwp_days'        => $p['lwp'],
                    'gross_pay'       => $p['gross'],
                    'earnings_json'   => json_encode($p['earnings']),
                    'deductions_json' => json_encode($p['deductions']),
                    'net_pay'         => $p['net'],
                    'status'          => $p['status'],
                    'created_at'      => date('Y-m-d H:i:s'),
                    'updated_at'      => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
