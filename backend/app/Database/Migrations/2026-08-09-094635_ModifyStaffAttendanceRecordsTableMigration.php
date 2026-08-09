<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ModifyStaffAttendanceRecordsTableMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('staff_attendance_records', [
            'first_in_time'   => ['type' => 'DATETIME', 'null' => true, 'after' => 'attendance_date'],
            'last_out_time'   => ['type' => 'DATETIME', 'null' => true, 'after' => 'first_in_time'],
            'total_hours'     => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0, 'after' => 'last_out_time'],
            'late_minutes'    => ['type' => 'INT', 'default' => 0, 'after' => 'total_hours'],
            'early_minutes'   => ['type' => 'INT', 'default' => 0, 'after' => 'late_minutes'],
            'overtime_hours'  => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0, 'after' => 'early_minutes'],
            'is_half_day'     => ['type' => 'BOOLEAN', 'default' => false, 'after' => 'overtime_hours'],
        ]);

        $this->db->query("ALTER TABLE `staff_attendance_records` MODIFY COLUMN `state` ENUM('Present', 'On Leave', 'Unauthorized', 'Half Day', 'Missing Punch') NOT NULL");
    }

    public function down(): void
    {
        // Prevent data truncation error on rollback by resetting new enum values
        $this->db->table('staff_attendance_records')
            ->whereIn('state', ['Half Day', 'Missing Punch'])
            ->update(['state' => 'Present']);

        if ($this->db->fieldExists('first_in_time', 'staff_attendance_records')) {
            $this->forge->dropColumn('staff_attendance_records', [
                'first_in_time', 'last_out_time', 'total_hours', 'late_minutes', 'early_minutes', 'overtime_hours', 'is_half_day'
            ]);
        }
        $this->db->query("ALTER TABLE `staff_attendance_records` MODIFY COLUMN `state` ENUM('Present', 'On Leave', 'Unauthorized') NOT NULL");
    }
}
