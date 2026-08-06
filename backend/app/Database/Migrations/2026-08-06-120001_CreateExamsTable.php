<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/examination/Phase-1-Domain-Model.md — ENT-EXM-001.
 */
class CreateExamsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'exam_id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'exam_name'           => ['type' => 'VARCHAR', 'constraint' => 50],
            'class_id'            => ['type' => 'BIGINT', 'unsigned' => true],
            'academic_session_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'grading_scheme_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'exam_date'           => ['type' => 'DATE'],
            'status'              => [
                'type'       => 'ENUM',
                'constraint' => ['CONFIGURED', 'ACTIVE', 'LOCKED', 'CLOSED'],
                'default'    => 'CONFIGURED',
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('exam_id', true);
        $this->forge->addUniqueKey(
            ['class_id', 'exam_name', 'academic_session_id'],
            'uq_exams_class_id_exam_name_academic_session_id',
        );
        $this->forge->addKey('grading_scheme_id', false, false, 'idx_exams_grading_scheme_id');
        $this->forge->addKey('status', false, false, 'idx_exams_status');

        // class_id/academic_session_id/grading_scheme_id are cross-module
        // references to Academic — no DB-level FK across the module
        // boundary; validated via Academic's Services in the Service
        // layer instead.

        $this->forge->createTable('exams', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('exams', true);
    }
}
