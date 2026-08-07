<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/transport/Phase-1-Domain-Model.md — ENT-TRN-002.
 */
class CreateVehiclesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'vehicle_id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'registration_no'     => ['type' => 'VARCHAR', 'constraint' => 20],
            'capacity'            => ['type' => 'INT'],
            'gps_device_id'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'license_valid_until' => ['type' => 'DATE', 'null' => true],
            'created_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'          => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('vehicle_id', true);
        $this->forge->addUniqueKey('registration_no', 'uq_vehicles_registration_no');

        $this->forge->createTable('vehicles', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('vehicles', true);
    }
}
