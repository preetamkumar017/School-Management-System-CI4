<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-HR-001.
 */
class CreateEmployeesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'employee_id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_code'         => ['type' => 'VARCHAR', 'constraint' => 20],
            'full_name'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'department_id'         => ['type' => 'BIGINT', 'unsigned' => true],
            'designation_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'joining_date'          => ['type' => 'DATE'],
            'exit_date'             => ['type' => 'DATE', 'null' => true],
            'salary_structure_json' => ['type' => 'JSON'],
            'status'                => [
                'type'       => 'ENUM',
                'constraint' => ['Active', 'Exited'],
                'default'    => 'Active',
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('employee_id', true);
        $this->forge->addUniqueKey('employee_code', 'uq_employees_employee_code');
        $this->forge->addKey('department_id', false, false, 'idx_employees_department_id');
        $this->forge->addKey('designation_id', false, false, 'idx_employees_designation_id');

        $this->forge->addForeignKey('department_id', 'departments', 'department_id', 'RESTRICT', 'RESTRICT', 'fk_employees_departments');
        $this->forge->addForeignKey('designation_id', 'designations', 'designation_id', 'RESTRICT', 'RESTRICT', 'fk_employees_designations');

        $this->forge->createTable('employees', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('employees', true);
    }
}
