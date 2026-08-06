<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — ENT-ACAD-005.
 */
class CreateGradingSchemesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'grading_scheme_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'scheme_name'       => ['type' => 'VARCHAR', 'constraint' => 50],
            'board_type'        => ['type' => 'ENUM', 'constraint' => ['CBSE', 'ICSE', 'STATE_BOARD']],
            'grade_band_json'   => ['type' => 'JSON'],
            'created_by'        => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_by'        => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'        => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'        => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('grading_scheme_id', true);
        $this->forge->addUniqueKey('scheme_name', 'uq_grading_schemes_scheme_name');

        $this->forge->createTable('grading_schemes', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('grading_schemes', true);
    }
}
