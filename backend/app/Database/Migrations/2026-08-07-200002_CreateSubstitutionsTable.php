<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md — ENT-TT-003
 * (FR-16 / BR-TT-004). One row per absent-teacher/date/period — applies
 * "for that date only" and never mutates timetable_entries (that is
 * BR-TT-005's revise() mechanism, kept separate per ADR-013 §3).
 */
class CreateSubstitutionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'substitution_id'      => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'timetable_entry_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'absent_employee_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'substitute_employee_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'substitution_date'    => ['type' => 'DATE'],
            'status'               => [
                'type'       => 'ENUM',
                'constraint' => ['ASSIGNED', 'UNSUPERVISED'],
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('substitution_id', true);
        $this->forge->addUniqueKey(
            ['timetable_entry_id', 'substitution_date'],
            'uq_substitutions_entry_date',
        );

        // timetable_entry_id is a same-module FK (timetable_entries
        // already exists). absent_employee_id/substitute_employee_id are
        // cross-module references to HR & Payroll's Employee — no
        // DB-level FK, same convention as timetable_entries.employee_id.
        $this->forge->addForeignKey('timetable_entry_id', 'timetable_entries', 'timetable_entry_id', 'RESTRICT', 'RESTRICT', 'fk_substitutions_timetable_entries');

        $this->forge->createTable('substitutions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('substitutions', true);
    }
}
