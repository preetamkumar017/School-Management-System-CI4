<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExtraClassToTimetableEntriesMigration extends Migration
{
    public function up()
    {
        $this->forge->addColumn('timetable_entries', [
            'is_extra_class' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'null'       => false,
                'after'      => 'status'
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('is_extra_class', 'timetable_entries')) {
            $this->forge->dropColumn('timetable_entries', 'is_extra_class');
        }
    }
}
