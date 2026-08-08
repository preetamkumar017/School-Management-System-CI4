<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add Indian School HRMS attributes (Staff type, qualification, KYC, Bank details, probation/confirmation dates)
 * to employees table (docs/design/hr-payroll/HR-Module-School-Refinement-Plan.md).
 */
class AddIndianSchoolFieldsToEmployeesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('employees', [
            'staff_type' => [
                'type'       => 'ENUM',
                'constraint' => ['Teaching', 'NonTeaching', 'Support', 'Administrative'],
                'default'    => 'Teaching',
                'after'      => 'designation_id',
            ],
            'qualification' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'after'      => 'staff_type',
            ],
            'aadhaar_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 12,
                'null'       => true,
                'after'      => 'qualification',
            ],
            'pan_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'after'      => 'aadhaar_number',
            ],
            'pf_uan' => [
                'type'       => 'VARCHAR',
                'constraint' => 12,
                'null'       => true,
                'after'      => 'pan_number',
            ],
            'esi_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 17,
                'null'       => true,
                'after'      => 'pf_uan',
            ],
            'bank_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'esi_number',
            ],
            'bank_account_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
                'after'      => 'bank_name',
            ],
            'bank_ifsc_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'bank_account_number',
            ],
            'probation_end_date' => [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'joining_date',
            ],
            'confirmation_date' => [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'probation_end_date',
            ],
        ]);
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if ($db->tableExists('employees')) {
            $columns = [
                'staff_type',
                'qualification',
                'aadhaar_number',
                'pan_number',
                'pf_uan',
                'esi_number',
                'bank_name',
                'bank_account_number',
                'bank_ifsc_code',
                'probation_end_date',
                'confirmation_date',
            ];

            foreach ($columns as $column) {
                if ($db->fieldExists($column, 'employees')) {
                    $this->forge->dropColumn('employees', $column);
                }
            }
        }
    }
}
