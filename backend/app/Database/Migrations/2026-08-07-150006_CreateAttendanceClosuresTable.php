<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — additive table,
 * ADR-008 §4. Owned by HR & Payroll; written only by Attendance's
 * StaffAttendanceService::closePeriod() (one-way push, never read back
 * by Attendance) — mirrors ADR-005 §10's locked_by_closed_exam pattern.
 */
class CreateAttendanceClosuresTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'attendance_closure_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'           => ['type' => 'BIGINT', 'unsigned' => true],
            'pay_period'            => ['type' => 'VARCHAR', 'constraint' => 10],
            'closed_at'             => ['type' => 'DATETIME'],
            'closed_by'             => ['type' => 'BIGINT', 'unsigned' => true],
        ]);

        $this->forge->addKey('attendance_closure_id', true);
        $this->forge->addUniqueKey(['employee_id', 'pay_period'], 'uq_attendance_closures_employee_period');

        $this->forge->addForeignKey('employee_id', 'employees', 'employee_id', 'RESTRICT', 'RESTRICT', 'fk_attendance_closures_employees');

        $this->forge->createTable('attendance_closures', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('attendance_closures', true);
    }
}
