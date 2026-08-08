<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-021-communication-sms-email-gateway.md §c — additive
 * nullable column. NotificationLog's approved Appendix-G schema has no
 * message-body field; a real dispatch needs real content, so the
 * caller supplies it at NotificationLogService::create() time (the
 * same caller-supplied-at-create shape as override_reason).
 */
class AddMessageBodyToNotificationLogsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('notification_logs', [
            'message_body' => ['type' => 'TEXT', 'null' => true, 'after' => 'trigger_event'],
        ]);
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if ($db->tableExists('notification_logs')) {
            $this->forge->dropColumn('notification_logs', 'message_body');
        }
    }
}
