<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Expand leave_type ENUM to include ML (Maternity Leave), LWP (Loss of Pay), DL (Duty Leave)
 * and add reason / duty_leave_reference columns (docs/design/hr-payroll/HR-Module-School-Refinement-Plan.md).
 */
class AddSchoolLeaveTypesToLeaveRequestsTable extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('leave_requests', [
            'leave_type' => [
                'name'       => 'leave_type',
                'type'       => 'ENUM',
                'constraint' => ['CL', 'SL', 'EL', 'ML', 'LWP', 'DL'],
            ],
        ]);

        $this->forge->addColumn('leave_requests', [
            'reason' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'end_date',
            ],
            'duty_leave_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'reason',
            ],
        ]);
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if ($db->tableExists('leave_requests')) {
            $db->query("DELETE FROM leave_requests WHERE leave_type IN ('ML', 'LWP', 'DL')");

            if ($db->fieldExists('reason', 'leave_requests')) {
                $this->forge->dropColumn('leave_requests', ['reason', 'duty_leave_reference']);
            }

            $db->query("ALTER TABLE leave_requests MODIFY COLUMN leave_type ENUM('CL', 'SL', 'EL') NOT NULL");
        }
    }
}
