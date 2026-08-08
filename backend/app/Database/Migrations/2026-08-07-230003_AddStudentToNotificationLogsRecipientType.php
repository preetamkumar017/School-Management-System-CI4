<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-017-library-reservation-queue.md §7 — a reservation
 * holder can be a Student (Library's most common patron), but
 * `NotificationLog.recipient_type`'s ENUM (ADR-010's approved shape) was
 * only ever Guardian/Employee/User. Widening an existing ENUM column is
 * additive (no data loss — every already-stored value remains valid),
 * the same category of change as `AddRouteIdToFeeStructuresTable`
 * adding a column, just an ALTER on a constraint instead.
 */
class AddStudentToNotificationLogsRecipientType extends Migration
{
    public function up(): void
    {
        $this->forge->modifyColumn('notification_logs', [
            'recipient_type' => [
                'name'       => 'recipient_type',
                'type'       => 'ENUM',
                'constraint' => ['Guardian', 'Employee', 'User', 'Student'],
            ],
        ]);
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if (! $db->tableExists('notification_logs')) {
            return;
        }

        // Narrowing the ENUM back would truncate-fail on any row already
        // using 'Student' (a real possibility once ReservationService has
        // run) — delete those rows first so down() is a safe rollback in
        // every environment, including this project's test suite (whose
        // DatabaseTestTrait usage fully regresses/re-migrates around each
        // test, so rows a prior test committed can still be present when
        // this runs).
        $db->table('notification_logs')->where('recipient_type', 'Student')->delete();

        $this->forge->modifyColumn('notification_logs', [
            'recipient_type' => [
                'name'       => 'recipient_type',
                'type'       => 'ENUM',
                'constraint' => ['Guardian', 'Employee', 'User'],
            ],
        ]);
    }
}
