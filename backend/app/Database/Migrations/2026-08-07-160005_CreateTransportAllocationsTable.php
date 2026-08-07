<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/transport/Phase-1-Domain-Model.md — ENT-TRN-003.
 */
class CreateTransportAllocationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'transport_allocation_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'student_id'              => ['type' => 'BIGINT', 'unsigned' => true],
            'route_id'                => ['type' => 'BIGINT', 'unsigned' => true],
            'stop_name'               => ['type' => 'VARCHAR', 'constraint' => 50],
            'emergency_contact'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'status'                  => [
                'type'       => 'ENUM',
                'constraint' => ['Active', 'Waitlisted', 'De-allocated'],
                'default'    => 'Active',
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('transport_allocation_id', true);
        $this->forge->addKey('student_id', false, false, 'idx_transport_allocations_student_id');
        $this->forge->addKey('route_id', false, false, 'idx_transport_allocations_route_id');

        // student_id is a cross-module reference to SIS's students table
        // — no DB-level FK. route_id is intra-module — real FK.
        $this->forge->addForeignKey('route_id', 'routes', 'route_id', 'RESTRICT', 'RESTRICT', 'fk_transport_allocations_routes');

        $this->forge->createTable('transport_allocations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('transport_allocations', true);
    }
}
