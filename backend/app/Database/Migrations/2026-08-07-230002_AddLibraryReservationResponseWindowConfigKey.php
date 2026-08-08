<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-017-library-reservation-queue.md §4 — a new Configuration
 * key added after `CreateConfigurationsTable` already shipped (same
 * additive-migration shape `AddAdmissionSeatHoldPeriodConfigKey` used).
 * 48 hours (2 days): documented default, see the ADR for the reasoning.
 */
class AddLibraryReservationResponseWindowConfigKey extends Migration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('configurations')->insert([
            'setting_key'   => 'library.reservation_response_window_hours',
            'setting_value' => '48',
            'data_type'     => 'Number',
            'module'        => 'Library',
            'is_editable'   => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if ($db->tableExists('configurations')) {
            $db->table('configurations')->where('setting_key', 'library.reservation_response_window_hours')->delete();
        }
    }
}
