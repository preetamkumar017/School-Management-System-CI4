<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeeOnboardingChecklistsMigration extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'checklist_id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type'     => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'item_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'is_done' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'done_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'done_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
            ],
            'remarks' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            'sort_order' => [
                'type'    => 'TINYINT',
                'constraint' => 3,
                'unsigned' => true,
                'default' => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('checklist_id', true);
        $this->forge->addKey('employee_id');
        $this->forge->createTable('employee_onboarding_checklists');
    }

    public function down(): void
    {
        $this->forge->dropTable('employee_onboarding_checklists', true);
    }
}
