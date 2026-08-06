<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — ENT-ACAD-004.
 */
class CreateSubjectsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'subject_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'subject_name' => ['type' => 'VARCHAR', 'constraint' => 50],
            'subject_code' => ['type' => 'VARCHAR', 'constraint' => 10],
            'created_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'   => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('subject_id', true);
        $this->forge->addUniqueKey('subject_code', 'uq_subjects_subject_code');

        $this->forge->createTable('subjects', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('subjects', true);
    }
}
