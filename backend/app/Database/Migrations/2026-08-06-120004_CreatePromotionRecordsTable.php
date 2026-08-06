<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/examination/Phase-1-Domain-Model.md — ENT-EXM-004.
 */
class CreatePromotionRecordsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'promotion_record_id'        => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'student_id'                 => ['type' => 'BIGINT', 'unsigned' => true],
            'from_session_id'            => ['type' => 'BIGINT', 'unsigned' => true],
            'to_session_id'              => ['type' => 'BIGINT', 'unsigned' => true],
            'from_class_id'              => ['type' => 'BIGINT', 'unsigned' => true],
            'to_class_id'                => ['type' => 'BIGINT', 'unsigned' => true],
            'academic_closure_confirmed' => ['type' => 'BOOLEAN', 'default' => false],
            'fee_closure_confirmed'      => ['type' => 'BOOLEAN', 'default' => false],
            'created_by'                 => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'                 => ['type' => 'DATETIME', 'null' => true],
            'updated_by'                 => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'                 => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'                 => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'                 => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'                 => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('promotion_record_id', true);
        $this->forge->addUniqueKey(['student_id', 'from_session_id'], 'uq_promotion_records_student_id_from_session_id');
        $this->forge->addKey('to_session_id', false, false, 'idx_promotion_records_to_session_id');

        // All FKs (student_id -> SIS, *_session_id/*_class_id ->
        // Academic) are cross-module — no DB-level FK, validated via the
        // owning module's Service in the Service layer.

        $this->forge->createTable('promotion_records', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('promotion_records', true);
    }
}
