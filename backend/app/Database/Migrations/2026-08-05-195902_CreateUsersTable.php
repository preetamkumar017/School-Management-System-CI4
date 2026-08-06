<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/administration/Phase-1-Domain-Model.md — User (ENT-SYS-001).
 */
class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'user_id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'username'           => ['type' => 'VARCHAR', 'constraint' => 50],
            'password_hash'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'role_id'            => ['type' => 'BIGINT', 'unsigned' => true],
            'owner_type'         => ['type' => 'ENUM', 'constraint' => ['EMPLOYEE', 'STUDENT', 'GUARDIAN']],
            'owner_ref_id'       => ['type' => 'BIGINT', 'unsigned' => true],
            'status'             => [
                'type'       => 'ENUM',
                'constraint' => ['ACTIVE', 'LOCKED', 'DEACTIVATED'],
                'default'    => 'ACTIVE',
            ],
            'failed_login_count' => ['type' => 'INT', 'default' => 0],
            'last_login_at'      => ['type' => 'DATETIME', 'null' => true],
            'created_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'         => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('user_id', true);
        $this->forge->addUniqueKey('username', 'uq_users_username');
        $this->forge->addKey('role_id', false, false, 'idx_users_role_id');
        $this->forge->addKey(['owner_type', 'owner_ref_id'], false, false, 'idx_users_owner');

        // FK to roles: RESTRICT-only (Company Development Standard §4.6) —
        // a Role referenced by any User cannot be deleted out from under it.
        $this->forge->addForeignKey('role_id', 'roles', 'role_id', 'RESTRICT', 'RESTRICT', 'fk_users_roles');

        // owner_ref_id is polymorphic (Employee/Student/Guardian, three
        // different modules) — no DB-level FK, per the cross-module rule;
        // integrity is an application-layer check (Phase 1).

        $this->forge->createTable('users', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
