<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-019-transport-driver-trip-validity.md §1 — BR-TRN-006. A
 * net-new entity closing the gap ADR-009 §14 deferred: Appendix-G's
 * Transport catalogue never carried a Driver card, so this is a decided,
 * documented addition, not a migration of an approved schema.
 */
class CreateDriversTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'driver_id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'full_name'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'license_number'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'license_valid_until' => ['type' => 'DATE', 'null' => true],
            'status'              => [
                'type'       => 'ENUM',
                'constraint' => ['Active', 'Inactive'],
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

        $this->forge->addKey('driver_id', true);
        $this->forge->addUniqueKey('license_number', 'uq_drivers_license_number');

        $this->forge->createTable('drivers', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('drivers', true);
    }
}
