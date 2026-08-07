<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-017-library-reservation-queue.md §1/§2 — BR-LIB-006. A
 * net-new entity closing the gap ADR-009 §7 deferred: Appendix-G's
 * Library catalogue never carried a Reservation card, so this is a
 * decided, documented addition, not a migration of an approved schema.
 */
class CreateReservationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'reservation_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'book_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'borrower_type'  => [
                'type'       => 'ENUM',
                'constraint' => ['Student', 'Employee'],
            ],
            'borrower_ref_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'requested_at'    => ['type' => 'DATETIME'],
            'status'          => [
                'type'       => 'ENUM',
                'constraint' => ['Waiting', 'Notified', 'Fulfilled', 'Expired', 'Cancelled'],
                'default'    => 'Waiting',
            ],
            'notified_at'             => ['type' => 'DATETIME', 'null' => true],
            'notification_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by'              => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_by'              => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'              => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'              => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('reservation_id', true);
        $this->forge->addKey(['book_id', 'status'], false, false, 'idx_reservations_book_status');
        $this->forge->addKey('requested_at', false, false, 'idx_reservations_requested_at');
        $this->forge->addKey(['borrower_type', 'borrower_ref_id'], false, false, 'idx_reservations_borrower');
        $this->forge->addKey('notification_expires_at', false, false, 'idx_reservations_notification_expires_at');

        // book_id is intra-module — real FK. borrower_ref_id is a
        // cross-module polymorphic reference (Student or Employee per
        // borrower_type, same shape as BookIssue) — no DB-level FK.
        $this->forge->addForeignKey('book_id', 'books', 'book_id', 'RESTRICT', 'RESTRICT', 'fk_reservations_books');

        $this->forge->createTable('reservations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('reservations', true);
    }
}
