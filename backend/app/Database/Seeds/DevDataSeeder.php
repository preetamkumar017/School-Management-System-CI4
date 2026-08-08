<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DevDataSeeder extends Seeder
{
    public function run(): void
    {
        $db = $this->db;

        // Seed Roles
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

        // Seed Users
        $adminUser = $db->table('users')->where('username', 'admin')->get()->getRow();
        if ($adminUser === null) {
            $db->table('users')->insert([
                'username'      => 'admin',
                'password_hash' => password_hash('Admin@1234', PASSWORD_BCRYPT),
                'role_id'       => $adminRoleId,
                'owner_type'    => 'EMPLOYEE',
                'owner_ref_id'  => 1,
                'status'        => 'ACTIVE',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('users')->where('username', 'admin')->update([
                'password_hash' => password_hash('Admin@1234', PASSWORD_BCRYPT),
                'role_id'       => $adminRoleId,
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
                'owner_ref_id'  => 2,
                'status'        => 'ACTIVE',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        } else {
            $db->table('users')->where('username', 'priya.iyer')->update([
                'password_hash' => password_hash('Employee@1234', PASSWORD_BCRYPT),
                'role_id'       => $employeeRoleId,
                'status'        => 'ACTIVE',
            ]);
        }
    }
}
