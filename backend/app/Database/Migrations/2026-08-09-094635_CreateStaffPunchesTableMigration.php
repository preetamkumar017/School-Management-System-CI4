<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStaffPunchesTableMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'punch_id'     => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'punch_time'   => ['type' => 'DATETIME'],
            'punch_type'   => ['type' => 'ENUM', 'constraint' => ['In', 'Out']],
            'source'       => ['type' => 'ENUM', 'constraint' => ['Biometric', 'Manual', 'Web'], 'default' => 'Biometric'],
            'device_id'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
        ]);
        
        $this->forge->addKey('punch_id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('punch_time');
        
        $this->forge->createTable('staff_punches', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('staff_punches', true);
    }
}
