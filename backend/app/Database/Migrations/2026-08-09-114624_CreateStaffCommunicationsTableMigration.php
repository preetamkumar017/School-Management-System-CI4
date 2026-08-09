<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStaffCommunicationsTableMigration extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'communication_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'type'               => ['type' => 'ENUM', 'constraint' => ['Circular', 'Notice', 'Announcement', 'HR Notification', 'Alert']],
            'title'              => ['type' => 'VARCHAR', 'constraint' => 255],
            'message'            => ['type' => 'TEXT'],
            'target_audience'    => ['type' => 'ENUM', 'constraint' => ['All Staff', 'Specific Department', 'Specific Designation'], 'default' => 'All Staff'],
            'target_audience_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'publish_date'       => ['type' => 'DATE'],
            'expiry_date'        => ['type' => 'DATE', 'null' => true],
            'is_pinned'          => ['type' => 'BOOLEAN', 'default' => false],
            'status'             => ['type' => 'ENUM', 'constraint' => ['Draft', 'Published', 'Archived'], 'default' => 'Draft'],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_by'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('communication_id');
        $this->forge->createTable('staff_communications', true);
    }

    public function down()
    {
        $this->forge->dropTable('staff_communications', true);
    }
}
