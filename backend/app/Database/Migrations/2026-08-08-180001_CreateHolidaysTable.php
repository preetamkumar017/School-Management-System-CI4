<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the school_holidays table to store Gazetted, Restricted, and
 * School-specific holidays.  The leave-day calculator skips these dates
 * when sandwich_rule is OFF.
 */
class CreateHolidaysTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'holiday_id'  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'holiday_date'=> ['type' => 'DATE', 'null' => false],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'type'        => [
                'type'       => 'ENUM',
                'constraint' => ['Gazetted', 'Restricted', 'School'],
                'default'    => 'Gazetted',
                'null'       => false,
            ],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_recurring'=> ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('holiday_id', true);
        $this->forge->addKey('holiday_date');
        $this->forge->addUniqueKey('holiday_date', 'uq_holiday_date');
        $this->forge->createTable('school_holidays');
    }

    public function down(): void
    {
        $this->forge->dropTable('school_holidays', true);
    }
}
