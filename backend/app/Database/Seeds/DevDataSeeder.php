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
        $academicsDeptId = $db->table('departments')->where('department_name', 'Academics')->get()->getRow('department_id');
        if ($academicsDeptId === null) {
            $academicsDeptId = $db->table('departments')->insert(['department_name' => 'Academics'], true);
        }
        $adminDeptId = $db->table('departments')->where('department_name', 'Administration')->get()->getRow('department_id');
        if ($adminDeptId === null) {
            $adminDeptId = $db->table('departments')->insert(['department_name' => 'Administration'], true);
        }
        $accountsDeptId = $db->table('departments')->where('department_name', 'Accounts')->get()->getRow('department_id');
        if ($accountsDeptId === null) {
            $accountsDeptId = $db->table('departments')->insert(['department_name' => 'Accounts'], true);
        }

        // 3. Seed Designations
        $pgtDesigId = $db->table('designations')->where('designation_name', 'PGT Physics Teacher')->get()->getRow('designation_id');
        if ($pgtDesigId === null) {
            $pgtDesigId = $db->table('designations')->insert(['designation_name' => 'PGT Physics Teacher'], true);
        }
        $tgtDesigId = $db->table('designations')->where('designation_name', 'TGT Science Teacher')->get()->getRow('designation_id');
        if ($tgtDesigId === null) {
            $tgtDesigId = $db->table('designations')->insert(['designation_name' => 'TGT Science Teacher'], true);
        }
        $accDesigId = $db->table('designations')->where('designation_name', 'Senior Accountant')->get()->getRow('designation_id');
        if ($accDesigId === null) {
            $accDesigId = $db->table('designations')->insert(['designation_name' => 'Senior Accountant'], true);
        }

        // 4. Seed Employees
        $emp1Id = $db->table('employees')->where('employee_code', 'EMP-1001')->get()->getRow('employee_id');
        if ($emp1Id === null) {
            $emp1Id = $db->table('employees')->insert([
                'employee_code'         => 'EMP-1001',
                'full_name'             => 'Ramesh Kumar',
                'department_id'         => $academicsDeptId,
                'designation_id'        => $pgtDesigId,
                'staff_type'            => 'Teaching',
                'qualification'         => 'M.Sc Physics, B.Ed, CTET',
                'aadhaar_number'        => '123456789012',
                'pan_number'            => 'ABCDE1234F',
                'pf_uan'                => '100900800700',
                'esi_number'            => '31001234567890001',
                'bank_name'             => 'State Bank of India',
                'bank_account_number'   => '300123456789',
                'bank_ifsc_code'        => 'SBIN0001234',
                'joining_date'          => '2025-04-01',
                'salary_structure_json' => json_encode(['basic' => 45000, 'hra' => 18000, 'da' => 9000]),
                'status'                => 'Active',
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ], true);
        }

        $emp2Id = $db->table('employees')->where('employee_code', 'EMP-1002')->get()->getRow('employee_id');
        if ($emp2Id === null) {
            $emp2Id = $db->table('employees')->insert([
                'employee_code'         => 'EMP-1002',
                'full_name'             => 'Sunita Sharma',
                'department_id'         => $academicsDeptId,
                'designation_id'        => $tgtDesigId,
                'staff_type'            => 'Teaching',
                'qualification'         => 'B.Sc Chemistry, B.Ed',
                'aadhaar_number'        => '987654321012',
                'pan_number'            => 'XYZPK9876Q',
                'joining_date'          => '2025-06-15',
                'salary_structure_json' => json_encode(['basic' => 35000, 'hra' => 14000, 'da' => 7000]),
                'status'                => 'Active',
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ], true);
        }

        $emp3Id = $db->table('employees')->where('employee_code', 'EMP-1003')->get()->getRow('employee_id');
        if ($emp3Id === null) {
            $emp3Id = $db->table('employees')->insert([
                'employee_code'         => 'EMP-1003',
                'full_name'             => 'Vikram Singh',
                'department_id'         => $accountsDeptId,
                'designation_id'        => $accDesigId,
                'staff_type'            => 'NonTeaching',
                'qualification'         => 'M.Com, Tally Prime Certified',
                'joining_date'          => '2024-01-10',
                'salary_structure_json' => json_encode(['basic' => 40000, 'hra' => 16000]),
                'status'                => 'Active',
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ], true);
        }

        // 5. Seed Users
        $adminUser = $db->table('users')->where('username', 'admin')->get()->getRow();
        if ($adminUser === null) {
            $db->table('users')->insert([
                'username'      => 'admin',
                'password_hash' => password_hash('Admin@1234', PASSWORD_BCRYPT),
                'role_id'       => $adminRoleId,
                'owner_type'    => 'EMPLOYEE',
                'owner_ref_id'  => $emp1Id,
                'status'        => 'ACTIVE',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('users')->where('username', 'admin')->update([
                'password_hash' => password_hash('Admin@1234', PASSWORD_BCRYPT),
                'role_id'       => $adminRoleId,
                'owner_ref_id'  => $emp1Id,
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
                'owner_ref_id'  => $emp2Id,
                'status'        => 'ACTIVE',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('users')->where('username', 'priya.iyer')->update([
                'password_hash' => password_hash('Employee@1234', PASSWORD_BCRYPT),
                'role_id'       => $employeeRoleId,
                'owner_ref_id'  => $emp2Id,
                'status'        => 'ACTIVE',
            ]);
        }
    }
}
