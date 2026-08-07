<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-HR-002.
 */
class CreateDepartmentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'department_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'department_name' => ['type' => 'VARCHAR', 'constraint' => 50],
            'created_by'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_by'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'      => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('department_id', true);
        $this->forge->addUniqueKey('department_name', 'uq_departments_department_name');

        $this->forge->createTable('departments', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('departments', true);
    }
}
