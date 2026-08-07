<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/transport/Phase-1-Domain-Model.md — ENT-TRN-001. Includes
 * the decided additive vehicle_id column (ADR-009 §8).
 */
class CreateRoutesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'route_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'route_name' => ['type' => 'VARCHAR', 'constraint' => 50],
            'stops_json' => ['type' => 'JSON'],
            'capacity'   => ['type' => 'INT'],
            'vehicle_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('route_id', true);
        $this->forge->addUniqueKey('route_name', 'uq_routes_route_name');
        $this->forge->addKey('vehicle_id', false, false, 'idx_routes_vehicle_id');

        $this->forge->addForeignKey('vehicle_id', 'vehicles', 'vehicle_id', 'SET NULL', 'RESTRICT', 'fk_routes_vehicles');

        $this->forge->createTable('routes', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('routes', true);
    }
}
