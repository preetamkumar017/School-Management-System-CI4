<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\I18n\Time;

class AddAttendanceConfigurationsMigration extends Migration
{
    public function up(): void
    {
        $now = Time::now()->toDateTimeString();
        $this->db->table('configurations')->insertBatch([
            ['setting_key' => 'attendance.overtime_enabled', 'setting_value' => 'false', 'data_type' => 'Boolean', 'module' => 'Attendance', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'attendance.half_day_threshold_hours', 'setting_value' => '4.5', 'data_type' => 'Number', 'module' => 'Attendance', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'attendance.full_day_threshold_hours', 'setting_value' => '8.0', 'data_type' => 'Number', 'module' => 'Attendance', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'attendance.late_coming_grace_minutes', 'setting_value' => '15', 'data_type' => 'Number', 'module' => 'Attendance', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'attendance.early_leaving_grace_minutes', 'setting_value' => '15', 'data_type' => 'Number', 'module' => 'Attendance', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'attendance.standard_shift_start', 'setting_value' => '08:00:00', 'data_type' => 'String', 'module' => 'Attendance', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'attendance.standard_shift_end', 'setting_value' => '16:00:00', 'data_type' => 'String', 'module' => 'Attendance', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $keys = [
            'attendance.overtime_enabled',
            'attendance.half_day_threshold_hours',
            'attendance.full_day_threshold_hours',
            'attendance.late_coming_grace_minutes',
            'attendance.early_leaving_grace_minutes',
            'attendance.standard_shift_start',
            'attendance.standard_shift_end'
        ];
        if ($this->db->tableExists('configurations')) {
            $this->db->table('configurations')->whereIn('setting_key', $keys)->delete();
        }
    }
}
