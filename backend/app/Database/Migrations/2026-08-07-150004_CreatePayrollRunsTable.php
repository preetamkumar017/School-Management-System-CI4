<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-HR-004.
 */
class CreatePayrollRunsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'payroll_run_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'pay_period'      => ['type' => 'VARCHAR', 'constraint' => 10],
            'gross_pay'       => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'deductions_json' => ['type' => 'JSON'],
            'net_pay'         => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'status'          => [
                'type'       => 'ENUM',
                'constraint' => ['Draft', 'Approved', 'Processed'],
                'default'    => 'Draft',
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('payroll_run_id', true);
        $this->forge->addUniqueKey(['employee_id', 'pay_period'], 'uq_payroll_runs_employee_period');

        $this->forge->addForeignKey('employee_id', 'employees', 'employee_id', 'RESTRICT', 'RESTRICT', 'fk_payroll_runs_employees');

        $this->forge->createTable('payroll_runs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('payroll_runs', true);
    }
}
