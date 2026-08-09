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
            $adminRoleId = (int) $db->table('roles')->insert([
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
            $adminRoleId = (int) $adminRoleId;
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

        $hrRoleId = $db->table('roles')->where('role_name', 'HR Team')->get()->getRow('role_id');
        if ($hrRoleId === null) {
            $hrRoleId = (int) $db->table('roles')->insert([
                'role_name'      => 'HR Team',
                'description'    => 'HR & Payroll Manager',
                'is_system_role' => false,
                'permission_set' => json_encode(['hr_payroll.manage']),
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ], true);
        } else {
            $hrRoleId = (int) $hrRoleId;
            $db->table('roles')->where('role_id', $hrRoleId)->update([
                'permission_set' => json_encode(['hr_payroll.manage']),
            ]);
        }

        $employeeRoleId = $db->table('roles')->where('role_name', 'Employee')->get()->getRow('role_id');
        if ($employeeRoleId === null) {
            $employeeRoleId = (int) $db->table('roles')->insert([
                'role_name'      => 'Employee',
                'description'    => 'General Staff / Employee',
                'is_system_role' => false,
                'permission_set' => json_encode(['read']),
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ], true);
        } else {
            $employeeRoleId = (int) $employeeRoleId;
        }

        // 2. Seed Departments
        $depts = [
            'Academics'           => null,
            'Administration'      => null,
            'Accounts'            => null,
            'IT & Support'        => null,
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
            'HR Manager'          => null,
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
            [
                'code'          => 'EMP-1006',
                'name'          => 'Kavita Nair',
                'dept_id'       => $depts['Administration'],
                'desig_id'      => $desigs['HR Manager'],
                'type'          => 'Administrative',
                'qualification' => 'MBA in HR Management',
                'aadhaar'       => '567890123412',
                'pan'           => 'KVTNR5678M',
                'pf'            => '100900800705',
                'esi'           => '31001234567890006',
                'bank'          => 'HDFC Bank',
                'account'       => '501009876543',
                'ifsc'          => 'HDFC0000123',
                'joining'       => '2023-05-01',
                'salary'        => ['basic' => 55000, 'hra' => 22000, 'da' => 11000],
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

        $hrUser = $db->table('users')->where('username', 'hr.manager')->get()->getRow();
        if ($hrUser === null) {
            $db->table('users')->insert([
                'username'      => 'hr.manager',
                'password_hash' => password_hash('Hr@1234', PASSWORD_BCRYPT),
                'role_id'       => $hrRoleId,
                'owner_type'    => 'EMPLOYEE',
                'owner_ref_id'  => $empIds['EMP-1006'],
                'status'        => 'ACTIVE',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('users')->where('username', 'hr.manager')->update([
                'password_hash' => password_hash('Hr@1234', PASSWORD_BCRYPT),
                'role_id'       => $hrRoleId,
                'owner_type'    => 'EMPLOYEE',
                'owner_ref_id'  => $empIds['EMP-1006'],
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

        // 9. Seed School Holidays (2026 Indian Gazetted + School Holidays)
        $holidays = [
            ['date' => '2026-01-01', 'name' => 'New Year Day',          'type' => 'School',    'desc' => 'School Holiday'],
            ['date' => '2026-01-14', 'name' => 'Makar Sankranti',       'type' => 'Gazetted',  'desc' => 'Harvest festival'],
            ['date' => '2026-01-26', 'name' => 'Republic Day',          'type' => 'Gazetted',  'desc' => 'National Holiday', 'recurring' => 1],
            ['date' => '2026-03-25', 'name' => 'Holi',                  'type' => 'Gazetted',  'desc' => 'Festival of Colours'],
            ['date' => '2026-04-02', 'name' => 'Ram Navami',            'type' => 'Gazetted',  'desc' => 'Hindu Festival'],
            ['date' => '2026-04-03', 'name' => 'Good Friday',           'type' => 'Gazetted',  'desc' => 'Christian Holiday'],
            ['date' => '2026-04-14', 'name' => 'Dr Ambedkar Jayanti',   'type' => 'Gazetted',  'desc' => 'National Holiday'],
            ['date' => '2026-05-01', 'name' => 'Labour Day',            'type' => 'Gazetted',  'desc' => 'International Workers Day'],
            ['date' => '2026-06-11', 'name' => 'Summer Vacation Ends',  'type' => 'School',    'desc' => 'School resumes after summer break'],
            ['date' => '2026-08-15', 'name' => 'Independence Day',      'type' => 'Gazetted',  'desc' => 'National Holiday', 'recurring' => 1],
            ['date' => '2026-09-05', 'name' => 'Teachers Day',          'type' => 'School',    'desc' => 'Dr Radhakrishnan Birthday'],
            ['date' => '2026-09-21', 'name' => 'Milad un Nabi',         'type' => 'Gazetted',  'desc' => 'Prophet Birthday'],
            ['date' => '2026-10-02', 'name' => 'Gandhi Jayanti',        'type' => 'Gazetted',  'desc' => 'National Holiday', 'recurring' => 1],
            ['date' => '2026-10-20', 'name' => 'Dussehra',              'type' => 'Gazetted',  'desc' => 'Vijayadashami'],
            ['date' => '2026-11-05', 'name' => 'Diwali',                'type' => 'Gazetted',  'desc' => 'Festival of Lights'],
            ['date' => '2026-11-06', 'name' => 'Diwali (2nd day)',      'type' => 'School',    'desc' => 'School Holiday'],
            ['date' => '2026-11-15', 'name' => 'Guru Nanak Jayanti',    'type' => 'Gazetted',  'desc' => 'Sikh Festival'],
            ['date' => '2026-12-25', 'name' => 'Christmas Day',         'type' => 'Gazetted',  'desc' => 'Christian Holiday', 'recurring' => 1],
            ['date' => '2026-12-31', 'name' => 'School Year Closing',   'type' => 'School',    'desc' => 'Annual closing day'],
        ];

        foreach ($holidays as $h) {
            $exists = $db->table('school_holidays')->where('holiday_date', $h['date'])->get()->getRow();
            if ($exists === null) {
                $db->table('school_holidays')->insert([
                    'holiday_date' => $h['date'],
                    'name'         => $h['name'],
                    'type'         => $h['type'],
                    'description'  => $h['desc'] ?? null,
                    'is_recurring' => $h['recurring'] ?? 0,
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // 10. Seed Default Leave Types (can be customised per school)
        // sandwich_rule: NULL=inherit global, 1=calendar days, 0=working days only
        $leaveTypes = [
            [
                'code'              => 'CL',
                'name'              => 'Casual Leave',
                'description'       => 'Short-duration personal leave for casual reasons',
                'max_days_per_year' => 12,
                'is_paid'           => 1,
                'balance_check'     => 1,
                'sandwich_rule'     => 0,   // Working days only — skip Sundays & holidays
                'color_hex'         => '#3b82f6',
                'sort_order'        => 1,
            ],
            [
                'code'              => 'SL',
                'name'              => 'Sick Leave',
                'description'       => 'Leave for medical illness or health conditions',
                'max_days_per_year' => 10,
                'is_paid'           => 1,
                'balance_check'     => 1,
                'sandwich_rule'     => null, // Inherit global setting
                'color_hex'         => '#f59e0b',
                'sort_order'        => 2,
            ],
            [
                'code'              => 'EL',
                'name'              => 'Earned Leave',
                'description'       => 'Leave earned through service (carry-forward eligible)',
                'max_days_per_year' => 15,
                'is_paid'           => 1,
                'balance_check'     => 1,
                'sandwich_rule'     => 1,   // Calendar days — sandwich rule applies
                'color_hex'         => '#10b981',
                'sort_order'        => 3,
            ],
            [
                'code'              => 'ML',
                'name'              => 'Maternity Leave',
                'description'       => '26 weeks paid maternity leave as per Maternity Benefit Act',
                'max_days_per_year' => 180,
                'is_paid'           => 1,
                'balance_check'     => 0,   // No balance check — always approve
                'sandwich_rule'     => 1,   // Calendar days
                'color_hex'         => '#ec4899',
                'sort_order'        => 4,
            ],
            [
                'code'              => 'LWP',
                'name'              => 'Leave Without Pay',
                'description'       => 'Unpaid leave — deducted from salary',
                'max_days_per_year' => 0,   // 0 = unlimited
                'is_paid'           => 0,
                'balance_check'     => 0,
                'sandwich_rule'     => 0,   // Working days only
                'color_hex'         => '#ef4444',
                'sort_order'        => 5,
            ],
            [
                'code'              => 'DL',
                'name'              => 'Duty Leave',
                'description'       => 'Leave for official duties, training, or seminars',
                'max_days_per_year' => 0,   // Unlimited
                'is_paid'           => 1,
                'balance_check'     => 0,
                'sandwich_rule'     => null, // Inherit global
                'color_hex'         => '#8b5cf6',
                'sort_order'        => 6,
            ],
        ];

        foreach ($leaveTypes as $lt) {
            $exists = $db->table('leave_types')->where('code', $lt['code'])->get()->getRow();
            if ($exists === null) {
                $db->table('leave_types')->insert([
                    'code'              => $lt['code'],
                    'name'              => $lt['name'],
                    'description'       => $lt['description'],
                    'max_days_per_year' => $lt['max_days_per_year'],
                    'is_paid'           => $lt['is_paid'],
                    'balance_check'     => $lt['balance_check'],
                    'sandwich_rule'     => $lt['sandwich_rule'],
                    'color_hex'         => $lt['color_hex'],
                    'sort_order'        => $lt['sort_order'],
                    'is_active'         => 1,
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
