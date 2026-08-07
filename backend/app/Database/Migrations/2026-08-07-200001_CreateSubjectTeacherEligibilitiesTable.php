<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md — ENT-TT-002.
 * Net-new per ADR-013 §2 — Appendix-C BR-TT-004 names a "configured
 * subject-teacher eligibility mapping" but no such entity exists in
 * Appendix-G's Data Dictionary.
 */
class CreateSubjectTeacherEligibilitiesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'subject_teacher_eligibility_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'                    => ['type' => 'BIGINT', 'unsigned' => true],
            'subject_id'                     => ['type' => 'BIGINT', 'unsigned' => true],
            'created_by'                     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'                     => ['type' => 'DATETIME', 'null' => true],
            'updated_by'                     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'                     => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'                     => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'                     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'                     => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('subject_teacher_eligibility_id', true);
        $this->forge->addUniqueKey(
            ['employee_id', 'subject_id'],
            'uq_subject_teacher_eligibilities_employee_subject',
        );

        // employee_id/subject_id are cross-module references (HR &
        // Payroll's Employee, Academic's Subject) — no DB-level FK, same
        // convention as timetable_entries.

        $this->forge->createTable('subject_teacher_eligibilities', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('subject_teacher_eligibilities', true);
    }
}
