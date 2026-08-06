<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/fees/Phase-1-Domain-Model.md — ENT-FEE-005.
 */
class CreateScholarshipWaiversTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'scholarship_waiver_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'student_id'            => ['type' => 'BIGINT', 'unsigned' => true],
            'fee_head_id'           => ['type' => 'BIGINT', 'unsigned' => true],
            'waiver_type'           => [
                'type'       => 'ENUM',
                'constraint' => ['RTE', 'MERIT', 'SIBLING', 'STAFF_WARD'],
            ],
            'waiver_amount' => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'created_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'    => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('scholarship_waiver_id', true);
        $this->forge->addKey('student_id', false, false, 'idx_scholarship_waivers_student_id');

        // fee_head_id is intra-module — real FK. student_id is
        // cross-module — no DB-level FK.
        $this->forge->addForeignKey('fee_head_id', 'fee_heads', 'fee_head_id', 'RESTRICT', 'RESTRICT', 'fk_scholarship_waivers_fee_heads');

        $this->forge->createTable('scholarship_waivers', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('scholarship_waivers', true);
    }
}
