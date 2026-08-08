<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-016-admission-seat-hold-and-waitlist.md §1: additive
 * nullable `hold_expires_at` — a `SHORTLISTED` application with a
 * non-null, past `hold_expires_at` is a seat offer whose hold has
 * lapsed (BR-ADM-007). No new status value is introduced; `SHORTLISTED`
 * already represents "offered a seat, pending confirmation."
 */
class AddHoldExpiresAtToApplicationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('applications', [
            'hold_expires_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'after'   => 'decided_at',
            ],
        ]);

        $this->forge->addKey('hold_expires_at', false, false, 'idx_applications_hold_expires_at');
        $this->forge->processIndexes('applications');
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if ($db->tableExists('applications')) {
            $this->forge->dropKey('applications', 'idx_applications_hold_expires_at');
            $this->forge->dropColumn('applications', 'hold_expires_at');
        }
    }
}
