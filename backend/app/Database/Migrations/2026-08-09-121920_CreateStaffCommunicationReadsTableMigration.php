<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStaffCommunicationReadsTableMigration extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'               => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'communication_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'user_id'          => ['type' => 'BIGINT', 'unsigned' => true],
            'read_at'          => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['communication_id', 'user_id']);
        $this->forge->addForeignKey('communication_id', 'staff_communications', 'communication_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('staff_communication_reads', true);
    }

    public function down()
    {
        $this->forge->dropTable('staff_communication_reads', true);
    }
}
