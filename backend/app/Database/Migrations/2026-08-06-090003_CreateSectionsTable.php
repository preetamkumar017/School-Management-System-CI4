<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — ENT-ACAD-003.
 */
class CreateSectionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'section_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'class_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'section_name' => ['type' => 'VARCHAR', 'constraint' => 10],
            'capacity'     => ['type' => 'INT'],
            'created_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'   => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('section_id', true);
        $this->forge->addUniqueKey(['class_id', 'section_name'], 'uq_sections_class_id_section_name');

        // FK to classes: RESTRICT-only (Company Development Standard §4.6).
        $this->forge->addForeignKey('class_id', 'classes', 'class_id', 'RESTRICT', 'RESTRICT', 'fk_sections_classes');

        $this->forge->createTable('sections', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('sections', true);
    }
}
