<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/timetable/Phase-1-Domain-Model.md — ENT-TT-001. Table named
 * `timetable_entries` (grammatically correct plural), not Appendix-G's
 * literal `timetable_entrys`.
 */
class CreateTimetableEntriesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'timetable_entry_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'section_id'         => ['type' => 'BIGINT', 'unsigned' => true],
            'subject_id'         => ['type' => 'BIGINT', 'unsigned' => true],
            'employee_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'day_of_week'        => [
                'type'       => 'ENUM',
                'constraint' => ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'],
            ],
            'period_no'  => ['type' => 'INT'],
            'room_id'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'version_no' => ['type' => 'INT', 'default' => 1],
            'status'     => [
                'type'       => 'ENUM',
                'constraint' => ['DRAFT', 'PUBLISHED'],
                'default'    => 'DRAFT',
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('timetable_entry_id', true);
        $this->forge->addUniqueKey(
            ['section_id', 'day_of_week', 'period_no'],
            'uq_timetable_entries_section_day_period',
        );
        $this->forge->addUniqueKey(
            ['employee_id', 'day_of_week', 'period_no'],
            'uq_timetable_entries_employee_day_period',
        );

        // section_id/subject_id are cross-module references to Academic —
        // no DB-level FK. employee_id is an intended reference to HR &
        // Payroll's Employee, which doesn't exist yet (ADR-006 §1) — not
        // even a plain cross-module FK target today, stored only.

        $this->forge->createTable('timetable_entries', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('timetable_entries', true);
    }
}
