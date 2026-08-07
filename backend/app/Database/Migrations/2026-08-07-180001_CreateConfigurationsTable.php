<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/administration/Phase-7-Configuration-Design.md —
 * ENT-SYS-005. Seeds the eleven keys ADR-011 §4 migrates from
 * already-shipped Services' private constants — per Appendix-G's own
 * Lifecycle line ("Created at implementation"), not via a runtime POST
 * (ADR-011 §2).
 */
class CreateConfigurationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'setting_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'setting_value' => ['type' => 'VARCHAR', 'constraint' => 500],
            'data_type'     => [
                'type'       => 'ENUM',
                'constraint' => ['String', 'Number', 'Boolean', 'Date'],
            ],
            'module'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'is_editable' => ['type' => 'BOOLEAN', 'default' => true],
            'created_by'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_by'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'  => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('setting_id', true);
        $this->forge->addUniqueKey('setting_key', 'uq_configurations_setting_key');

        $this->forge->createTable('configurations', true);

        $now = date('Y-m-d H:i:s');

        $this->db->table('configurations')->insertBatch([
            ['setting_key' => 'library.max_books_per_borrower', 'setting_value' => '3', 'data_type' => 'Number', 'module' => 'Library', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'library.fine_per_day_rate', 'setting_value' => '2.00', 'data_type' => 'Number', 'module' => 'Library', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'library.replacement_charge', 'setting_value' => '500.00', 'data_type' => 'Number', 'module' => 'Library', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'library.outstanding_fine_threshold', 'setting_value' => '0.00', 'data_type' => 'Number', 'module' => 'Library', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'timetable.weekly_load_ceiling', 'setting_value' => '30', 'data_type' => 'Number', 'module' => 'Timetable', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'attendance.exam_eligibility_min_percentage', 'setting_value' => '75.0', 'data_type' => 'Number', 'module' => 'Attendance', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'attendance.edit_window_days', 'setting_value' => '0', 'data_type' => 'Number', 'module' => 'Attendance', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'examination.anomaly_threshold_percentage_points', 'setting_value' => '30.0', 'data_type' => 'Number', 'module' => 'Examination', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'fees.late_fee_rate_percentage', 'setting_value' => '5.0', 'data_type' => 'Number', 'module' => 'Fees', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'hr_payroll.leave_allocation.cl', 'setting_value' => '12', 'data_type' => 'Number', 'module' => 'HrPayroll', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'hr_payroll.leave_allocation.sl', 'setting_value' => '10', 'data_type' => 'Number', 'module' => 'HrPayroll', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'hr_payroll.leave_allocation.el', 'setting_value' => '15', 'data_type' => 'Number', 'module' => 'HrPayroll', 'is_editable' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('configurations', true);
    }
}
