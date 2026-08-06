<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/administration/Phase-1-Domain-Model.md — Role (ENT-SYS-002).
 */
class CreateRolesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'role_id'        => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'role_name'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_system_role' => ['type' => 'BOOLEAN', 'default' => false],
            'permission_set' => ['type' => 'JSON'],
            'created_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'     => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('role_id', true);
        // Known simplification: this unique constraint isn't soft-delete-aware
        // (School-ERP-Database-Supplement.md documents the generated-column
        // workaround for that). Not applied here — Role is rarely deleted in
        // practice (Phase 1: "rarely deprecated"), so the edge case of
        // reusing a soft-deleted role's name isn't worth the complexity yet.
        $this->forge->addUniqueKey('role_name', 'uq_roles_role_name');

        $this->forge->createTable('roles', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('roles', true);
    }
}
