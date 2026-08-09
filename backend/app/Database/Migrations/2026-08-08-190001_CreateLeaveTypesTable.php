<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the leave_types table — stores per-school configurable leave types.
 * Replaces hardcoded PHP constants (CL/SL/EL/ML/DL/LWP).
 *
 * sandwich_rule:
 *   NULL  = inherit global hr_payroll.sandwich_rule_enabled config
 *   1     = Calendar Days (sandwich rule ON — count Sundays & holidays)
 *   0     = Working Days Only (skip Sundays & holidays)
 */
class CreateLeaveTypesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'leave_type_id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code'              => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            'name'              => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'description'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'max_days_per_year' => ['type' => 'INT', 'default' => 0, 'null' => false],  // 0 = unlimited
            'is_paid'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'null' => false],
            'balance_check'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'null' => false],
            'sandwich_rule'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => null, 'null' => true],
            'color_hex'         => ['type' => 'VARCHAR', 'constraint' => 7, 'default' => '#6366f1', 'null' => false],
            'sort_order'        => ['type' => 'INT', 'default' => 0, 'null' => false],
            'is_active'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'null' => false],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('leave_type_id', true);
        $this->forge->addUniqueKey('code', 'uq_leave_type_code');
        $this->forge->createTable('leave_types');
    }

    public function down(): void
    {
        $this->forge->dropTable('leave_types', true);
    }
}
