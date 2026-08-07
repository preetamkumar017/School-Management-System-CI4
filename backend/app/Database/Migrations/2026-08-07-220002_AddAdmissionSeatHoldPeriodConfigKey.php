<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-016-admission-seat-hold-and-waitlist.md §2 — a new
 * Configuration key added after `CreateConfigurationsTable` already
 * shipped (same additive-migration shape `AddRouteIdToFeeStructuresTable`
 * used for a column). 72 hours (3 days): documented default, see the
 * ADR for the reasoning.
 */
class AddAdmissionSeatHoldPeriodConfigKey extends Migration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('configurations')->insert([
            'setting_key'   => 'admission.seat_hold_period_hours',
            'setting_value' => '72',
            'data_type'     => 'Number',
            'module'        => 'Admission',
            'is_editable'   => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    public function down(): void
    {
        $this->db->table('configurations')->where('setting_key', 'admission.seat_hold_period_hours')->delete();
    }
}
