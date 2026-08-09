<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExtraProfileFieldsToEmployeesMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('employees', [
            'experience_years' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => true,
                'default'    => null,
                'after'      => 'qualification',
            ],
            'emergency_contact_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'default'    => null,
                'after'      => 'experience_years',
            ],
            'emergency_contact_phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'default'    => null,
                'after'      => 'emergency_contact_name',
            ],
            'documents_json' => [
                'type'    => 'JSON',
                'null'    => true,
                'default' => null,
                'after'   => 'emergency_contact_phone',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('employees', [
            'experience_years',
            'emergency_contact_name',
            'emergency_contact_phone',
            'documents_json',
        ]);
    }
}
