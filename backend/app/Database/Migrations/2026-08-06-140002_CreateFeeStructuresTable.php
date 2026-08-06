<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/fees/Phase-1-Domain-Model.md — ENT-FEE-002.
 */
class CreateFeeStructuresTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'fee_structure_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'class_id'            => ['type' => 'BIGINT', 'unsigned' => true],
            'fee_head_id'         => ['type' => 'BIGINT', 'unsigned' => true],
            'academic_session_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'category'            => [
                'type'       => 'ENUM',
                'constraint' => ['GENERAL', 'RTE'],
                'default'    => 'GENERAL',
            ],
            'amount'     => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('fee_structure_id', true);
        $this->forge->addUniqueKey(
            ['class_id', 'fee_head_id', 'academic_session_id', 'category'],
            'uq_fee_structures_class_head_session_category',
        );

        // fee_head_id is intra-module — real FK. class_id/academic_session_id
        // are cross-module references to Academic — no DB-level FK.
        $this->forge->addForeignKey('fee_head_id', 'fee_heads', 'fee_head_id', 'RESTRICT', 'RESTRICT', 'fk_fee_structures_fee_heads');

        $this->forge->createTable('fee_structures', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('fee_structures', true);
    }
}
