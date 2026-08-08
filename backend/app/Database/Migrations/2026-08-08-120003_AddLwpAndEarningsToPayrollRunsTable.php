<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add lwp_days and earnings_json to payroll_runs table
 * (docs/design/hr-payroll/HR-Module-School-Refinement-Plan.md).
 */
class AddLwpAndEarningsToPayrollRunsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('payroll_runs', [
            'lwp_days' => [
                'type'    => 'INT',
                'default' => 0,
                'after'   => 'pay_period',
            ],
            'earnings_json' => [
                'type'  => 'JSON',
                'null'  => true,
                'after' => 'gross_pay',
            ],
        ]);
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if ($db->tableExists('payroll_runs')) {
            $columns = ['lwp_days', 'earnings_json'];
            foreach ($columns as $column) {
                if ($db->fieldExists($column, 'payroll_runs')) {
                    $this->forge->dropColumn('payroll_runs', $column);
                }
            }
        }
    }
}
