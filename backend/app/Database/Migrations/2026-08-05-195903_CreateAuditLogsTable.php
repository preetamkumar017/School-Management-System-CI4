<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/administration/Phase-1-Domain-Model.md — AuditLog (ENT-SYS-004).
 * Write-once: no updated_at/deleted_at, no soft-delete — nothing in this
 * codebase has a code path to UPDATE or DELETE a row in this table.
 */
class CreateAuditLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'audit_log_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'entity_name'  => ['type' => 'VARCHAR', 'constraint' => 50],
            'record_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'action'       => [
                'type'       => 'ENUM',
                'constraint' => ['CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'REJECT', 'OVERRIDE'],
            ],
            'performed_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'performed_at' => ['type' => 'DATETIME'],
            'old_value'    => ['type' => 'JSON', 'null' => true],
            'new_value'    => ['type' => 'JSON', 'null' => true],
            'ip_address'   => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'reason'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);

        $this->forge->addKey('audit_log_id', true);
        $this->forge->addKey(['entity_name', 'record_id'], false, false, 'idx_audit_logs_entity');
        $this->forge->addKey('performed_by', false, false, 'idx_audit_logs_performed_by');
        $this->forge->addKey('performed_at', false, false, 'idx_audit_logs_performed_at');

        $this->forge->addForeignKey('performed_by', 'users', 'user_id', 'RESTRICT', 'RESTRICT', 'fk_audit_logs_users');

        $this->forge->createTable('audit_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_logs', true);
    }
}
