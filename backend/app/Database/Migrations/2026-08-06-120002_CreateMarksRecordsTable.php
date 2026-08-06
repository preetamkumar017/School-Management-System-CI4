<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/examination/Phase-1-Domain-Model.md — ENT-EXM-002.
 */
class CreateMarksRecordsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'marks_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'exam_id'         => ['type' => 'BIGINT', 'unsigned' => true],
            'student_id'      => ['type' => 'BIGINT', 'unsigned' => true],
            'subject_id'      => ['type' => 'BIGINT', 'unsigned' => true],
            'marks_obtained'  => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'max_marks'       => ['type' => 'DECIMAL', 'constraint' => '5,2'],
            'is_flagged'      => ['type' => 'BOOLEAN', 'default' => false],
            'is_locked'       => ['type' => 'BOOLEAN', 'default' => false],
            'created_by'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_by'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'      => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('marks_record_id', true);
        $this->forge->addUniqueKey(
            ['exam_id', 'student_id', 'subject_id'],
            'uq_marks_records_exam_id_student_id_subject_id',
        );
        $this->forge->addKey('student_id', false, false, 'idx_marks_records_student_id');
        $this->forge->addKey('subject_id', false, false, 'idx_marks_records_subject_id');

        // exam_id is intra-module (Exam also lives in Examination) — real
        // FK. student_id (SIS) and subject_id (Academic) are cross-module
        // — no DB-level FK, validated via the owning module's Service.
        $this->forge->addForeignKey('exam_id', 'exams', 'exam_id', 'RESTRICT', 'RESTRICT', 'fk_marks_records_exams');

        $this->forge->createTable('marks_records', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('marks_records', true);
    }
}
