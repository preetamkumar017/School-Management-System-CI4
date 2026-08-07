<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-HR-005.
 */
class CreateLeaveRequestsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'leave_request_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'      => ['type' => 'BIGINT', 'unsigned' => true],
            'leave_type'       => [
                'type'       => 'ENUM',
                'constraint' => ['CL', 'SL', 'EL'],
            ],
            'start_date' => ['type' => 'DATE'],
            'end_date'   => ['type' => 'DATE'],
            'status'     => [
                'type'       => 'ENUM',
                'constraint' => ['Pending', 'Approved', 'Rejected'],
                'default'    => 'Pending',
            ],
            'approver_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_by'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_by'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'  => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('leave_request_id', true);
        $this->forge->addKey('employee_id', false, false, 'idx_leave_requests_employee_id');
        $this->forge->addKey('status', false, false, 'idx_leave_requests_status');

        // approver_id is a cross-module reference to Administration's
        // users table — no DB-level FK.
        $this->forge->addForeignKey('employee_id', 'employees', 'employee_id', 'RESTRICT', 'RESTRICT', 'fk_leave_requests_employees');

        $this->forge->createTable('leave_requests', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('leave_requests', true);
    }
}
