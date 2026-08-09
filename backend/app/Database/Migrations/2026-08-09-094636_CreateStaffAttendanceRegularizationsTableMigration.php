<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStaffAttendanceRegularizationsTableMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'regularization_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'staff_attendance_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'requested_state'     => ['type' => 'ENUM', 'constraint' => ['Present', 'Half Day', 'On Leave']],
            'reason'              => ['type' => 'TEXT', 'null' => true],
            'status'              => ['type' => 'ENUM', 'constraint' => ['Pending', 'Approved', 'Rejected'], 'default' => 'Pending'],
            'approver_id'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'approved_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'created_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
        ]);
        
        $this->forge->addKey('regularization_id', true);
        $this->forge->addForeignKey('staff_attendance_id', 'staff_attendance_records', 'staff_attendance_id', 'CASCADE', 'CASCADE');
        
        $this->forge->createTable('staff_attendance_regularizations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('staff_attendance_regularizations', true);
    }
}
