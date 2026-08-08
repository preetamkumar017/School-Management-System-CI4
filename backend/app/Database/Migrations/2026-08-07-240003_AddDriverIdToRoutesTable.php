<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-019-transport-driver-trip-validity.md §3 — BR-TRN-006.
 * Additive nullable driver_id, mirroring vehicle_id's own shape from
 * ADR-009 §8 exactly: a Route can be configured before a Driver is
 * assigned, and BR-TRN-006's own Statement locates the assignment on
 * the Route ("assigned to that route"), not on a Trip.
 */
class AddDriverIdToRoutesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('routes', [
            'driver_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'vehicle_id',
            ],
        ]);

        $this->forge->addKey('driver_id', false, false, 'idx_routes_driver_id');
        $this->forge->addForeignKey('driver_id', 'drivers', 'driver_id', 'SET NULL', 'RESTRICT', 'fk_routes_drivers');
        $this->forge->processIndexes('routes');
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if ($db->tableExists('routes')) {
            $this->forge->dropForeignKey('routes', 'fk_routes_drivers');
            $this->forge->dropKey('routes', 'idx_routes_driver_id');
            $this->forge->dropColumn('routes', 'driver_id');
        }
    }
}
