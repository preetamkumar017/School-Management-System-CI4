<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/admission/Phase-1-Domain-Model.md — ENT-ADM-002.
 */
class CreateSeatAllocationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'seat_allocation_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'class_id'            => ['type' => 'BIGINT', 'unsigned' => true],
            'academic_session_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'total_capacity'      => ['type' => 'INT'],
            'rte_quota_capacity'  => ['type' => 'INT'],
            'seats_filled'        => ['type' => 'INT', 'default' => 0],
            'rte_seats_filled'    => ['type' => 'INT', 'default' => 0],
            'created_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'          => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('seat_allocation_id', true);
        $this->forge->addUniqueKey(
            ['class_id', 'academic_session_id'],
            'uq_seat_allocations_class_id_academic_session_id',
        );

        // class_id/academic_session_id are cross-module references to
        // Academic's classes/academic_sessions tables — no DB-level FK
        // across the module boundary; validated via Academic's
        // ClassService/AcademicSessionService in the Service layer.

        $this->forge->createTable('seat_allocations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('seat_allocations', true);
    }
}
