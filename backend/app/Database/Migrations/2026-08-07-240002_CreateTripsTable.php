<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-019-transport-driver-trip-validity.md §2/§5 — BR-TRN-006.
 * One row per trip-start event; driver_id/vehicle_id are copied off the
 * Route's own assignment at start time (§2), not caller-supplied.
 */
class CreateTripsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'trip_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'route_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'driver_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'vehicle_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'started_at' => ['type' => 'DATETIME'],
            'status'     => [
                'type'       => 'ENUM',
                'constraint' => ['Started'],
                'default'    => 'Started',
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('trip_id', true);
        $this->forge->addKey('route_id', false, false, 'idx_trips_route_id');
        $this->forge->addKey('driver_id', false, false, 'idx_trips_driver_id');
        $this->forge->addKey('vehicle_id', false, false, 'idx_trips_vehicle_id');

        // All three are intra-module — real FKs. RESTRICT on both delete
        // and update: a Trip is a historical log row, its route/driver/
        // vehicle must not disappear out from under it.
        $this->forge->addForeignKey('route_id', 'routes', 'route_id', 'RESTRICT', 'RESTRICT', 'fk_trips_routes');
        $this->forge->addForeignKey('driver_id', 'drivers', 'driver_id', 'RESTRICT', 'RESTRICT', 'fk_trips_drivers');
        $this->forge->addForeignKey('vehicle_id', 'vehicles', 'vehicle_id', 'RESTRICT', 'RESTRICT', 'fk_trips_vehicles');

        $this->forge->createTable('trips', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('trips', true);
    }
}
