<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAppraisalTablesMigration extends Migration
{
    public function up()
    {
        // 1. appraisal_cycles
        $this->forge->addField([
            'cycle_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'start_date' => ['type' => 'DATE'],
            'end_date'   => ['type' => 'DATE'],
            'status'     => ['type' => 'ENUM', 'constraint' => ['Draft', 'Active', 'Closed'], 'default' => 'Draft'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('cycle_id');
        $this->forge->createTable('appraisal_cycles', true);

        // 2. appraisals
        $this->forge->addField([
            'appraisal_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'cycle_id'         => ['type' => 'INT', 'unsigned' => true],
            'employee_id'      => ['type' => 'BIGINT', 'unsigned' => true],
            'evaluator_id'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'self_rating'      => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'evaluator_rating' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'final_rating'     => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'status'           => ['type' => 'ENUM', 'constraint' => ['Self Appraisal Pending', 'Review Pending', 'Completed'], 'default' => 'Self Appraisal Pending'],
            'recommendation'   => ['type' => 'ENUM', 'constraint' => ['None', 'Increment', 'Promotion'], 'default' => 'None'],
            'evaluator_comments'=>['type' => 'TEXT', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('appraisal_id');
        $this->forge->addForeignKey('cycle_id', 'appraisal_cycles', 'cycle_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('employee_id', 'employees', 'employee_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('appraisals', true);

        // 3. appraisal_kpis
        $this->forge->addField([
            'kpi_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'appraisal_id'   => ['type' => 'INT', 'unsigned' => true],
            'kpi_name'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'weightage'      => ['type' => 'INT', 'default' => 100], // e.g. 50%
            'self_score'     => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'evaluator_score'=> ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'self_comments'  => ['type' => 'TEXT', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('kpi_id');
        $this->forge->addForeignKey('appraisal_id', 'appraisals', 'appraisal_id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('appraisal_kpis', true);
    }

    public function down()
    {
        $this->forge->dropTable('appraisal_kpis', true);
        $this->forge->dropTable('appraisals', true);
        $this->forge->dropTable('appraisal_cycles', true);
    }
}
