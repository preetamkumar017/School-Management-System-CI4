<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-HR-003.
 */
class CreateDesignationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'designation_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'designation_name' => ['type' => 'VARCHAR', 'constraint' => 50],
            'created_by'       => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_by'       => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'       => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'       => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('designation_id', true);
        $this->forge->addUniqueKey('designation_name', 'uq_designations_designation_name');

        $this->forge->createTable('designations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('designations', true);
    }
}
