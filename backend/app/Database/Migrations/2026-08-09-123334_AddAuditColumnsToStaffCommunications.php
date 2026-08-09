<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAuditColumnsToStaffCommunications extends Migration
{
    public function up()
    {
        $this->forge->addColumn('staff_communications', [
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false, 'after' => 'deleted_at'],
            'deleted_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'is_deleted'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('staff_communications', 'is_deleted');
        $this->forge->dropColumn('staff_communications', 'deleted_by');
    }
}
