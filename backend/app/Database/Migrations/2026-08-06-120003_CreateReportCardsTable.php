<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/examination/Phase-1-Domain-Model.md — ENT-EXM-003.
 */
class CreateReportCardsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'report_card_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'student_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'exam_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'grade_summary'  => ['type' => 'JSON'],
            'gpa'            => ['type' => 'DECIMAL', 'constraint' => '3,2'],
            'class_rank'     => ['type' => 'INT', 'null' => true],
            'is_published'   => ['type' => 'BOOLEAN', 'default' => false],
            'published_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'     => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('report_card_id', true);
        $this->forge->addUniqueKey(['student_id', 'exam_id'], 'uq_report_cards_student_id_exam_id');

        // exam_id is intra-module — real FK. student_id (SIS) is
        // cross-module — no DB-level FK.
        $this->forge->addForeignKey('exam_id', 'exams', 'exam_id', 'RESTRICT', 'RESTRICT', 'fk_report_cards_exams');

        $this->forge->createTable('report_cards', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('report_cards', true);
    }
}
