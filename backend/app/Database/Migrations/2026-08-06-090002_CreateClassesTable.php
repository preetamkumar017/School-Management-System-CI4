<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — ENT-ACAD-002 (`Class`).
 */
class CreateClassesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'class_id'       => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'class_name'     => ['type' => 'VARCHAR', 'constraint' => 20],
            'sequence_order' => ['type' => 'INT'],
            'created_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'     => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('class_id', true);
        $this->forge->addUniqueKey('class_name', 'uq_classes_class_name');
        $this->forge->addUniqueKey('sequence_order', 'uq_classes_sequence_order');

        $this->forge->createTable('classes', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('classes', true);
    }
}
