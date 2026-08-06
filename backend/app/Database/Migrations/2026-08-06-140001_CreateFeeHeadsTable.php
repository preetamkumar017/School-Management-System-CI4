<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/fees/Phase-1-Domain-Model.md — ENT-FEE-001.
 */
class CreateFeeHeadsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'fee_head_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'fee_head_name' => ['type' => 'VARCHAR', 'constraint' => 50],
            'is_taxable'    => ['type' => 'BOOLEAN', 'default' => false],
            'gst_rate'      => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'created_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'    => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('fee_head_id', true);
        $this->forge->addUniqueKey('fee_head_name', 'uq_fee_heads_fee_head_name');

        $this->forge->createTable('fee_heads', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('fee_heads', true);
    }
}
