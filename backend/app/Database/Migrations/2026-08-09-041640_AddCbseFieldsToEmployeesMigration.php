<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCbseFieldsToEmployeesMigration extends Migration
{
    public function up(): void
    {
        $fields = [
            'cbse_classification' => [
                'type'       => "ENUM('PRT','TGT','PGT','None')",
                'null'       => true,
                'default'    => 'None',
                'after'      => 'staff_type',
            ],
            'cbse_teacher_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'cbse_classification',
            ],
        ];

        $this->forge->addColumn('employees', $fields);
    }

    public function down(): void
    {
        $this->forge->dropColumn('employees', ['cbse_classification', 'cbse_teacher_code']);
    }
}
