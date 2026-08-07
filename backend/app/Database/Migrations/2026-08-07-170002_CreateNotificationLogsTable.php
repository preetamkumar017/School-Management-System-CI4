<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/communication/Phase-1-Domain-Model.md — ENT-COM-002.
 * Includes the decided additive failure_reason column (ADR-010 §2).
 */
class CreateNotificationLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'notification_log_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'recipient_type'      => [
                'type'       => 'ENUM',
                'constraint' => ['Guardian', 'Employee', 'User'],
            ],
            'recipient_ref_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'channel'          => [
                'type'       => 'ENUM',
                'constraint' => ['SMS', 'Email', 'Push'],
            ],
            'trigger_event'  => ['type' => 'VARCHAR', 'constraint' => 50],
            'status'         => [
                'type'       => 'ENUM',
                'constraint' => ['Queued', 'Dispatched', 'Delivered', 'Failed'],
                'default'    => 'Queued',
            ],
            'dispatched_at'  => ['type' => 'DATETIME', 'null' => true],
            'failure_reason' => ['type' => 'TEXT', 'null' => true],
            'created_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'     => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('notification_log_id', true);
        $this->forge->addKey(['recipient_type', 'recipient_ref_id'], false, false, 'idx_notification_logs_recipient');
        $this->forge->addKey('status', false, false, 'idx_notification_logs_status');
        $this->forge->addKey('dispatched_at', false, false, 'idx_notification_logs_dispatched_at');

        // recipient_ref_id is a cross-module polymorphic reference
        // (Guardian, Employee, or User per recipient_type) — no
        // DB-level FK.

        $this->forge->createTable('notification_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('notification_logs', true);
    }
}
