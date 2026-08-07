<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-ATT-002. Lives in
 * the Attendance module per Appendix-G's own Module: ATT designation,
 * built alongside HR & Payroll per ADR-008 §3.
 */
class CreateStaffAttendanceRecordsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'staff_attendance_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'         => ['type' => 'BIGINT', 'unsigned' => true],
            'attendance_date'     => ['type' => 'DATE'],
            'state'               => [
                'type'       => 'ENUM',
                'constraint' => ['Present', 'On Leave', 'Unauthorized'],
            ],
            'is_reconciled' => ['type' => 'BOOLEAN', 'default' => false],
            'created_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'    => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('staff_attendance_id', true);
        $this->forge->addUniqueKey(['employee_id', 'attendance_date'], 'uq_staff_attendance_records_employee_date');

        // employee_id is a cross-module reference to HR & Payroll's
        // employees table (validated via EmployeeService) — no DB-level FK.

        $this->forge->createTable('staff_attendance_records', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('staff_attendance_records', true);
    }
}
