<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ADR-014 §1 (BR-FEE-003/BR-TRN-005): additive nullable route_id lets a
 * route-tier fee coexist with the existing class/session/category-keyed
 * rows. NULL means "applies regardless of route" (all existing rows);
 * a real value means "applies only to a student on that route." Cross-
 * module reference to Transport's routes table — no DB-level FK, same
 * convention class_id/academic_session_id already use for Academic.
 */
class AddRouteIdToFeeStructuresTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('fee_structures', [
            'route_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'academic_session_id',
            ],
        ]);

        $this->forge->dropKey('fee_structures', 'uq_fee_structures_class_head_session_category');

        $this->forge->addKey('route_id', false, false, 'idx_fee_structures_route_id');
        $this->forge->addUniqueKey(
            ['class_id', 'fee_head_id', 'academic_session_id', 'category', 'route_id'],
            'uq_fee_structures_class_head_session_category_route',
        );
        $this->forge->processIndexes('fee_structures');
    }

    public function down(): void
    {
        // A route-tier row and its base-row sibling legitimately share
        // class_id/fee_head_id/academic_session_id/category once route_id
        // exists (they only differ by route_id) — that's the whole point
        // of this migration. Reinstating the narrower pre-route_id unique
        // key can't coexist with that data, so down() clears the table
        // first; this mirrors down() being a schema-rollback convenience
        // for dev/test, not a data-preserving operation.
        $this->db->table('fee_structures')->truncate();

        $this->forge->dropKey('fee_structures', 'uq_fee_structures_class_head_session_category_route');
        $this->forge->dropKey('fee_structures', 'idx_fee_structures_route_id');

        $this->forge->addUniqueKey(
            ['class_id', 'fee_head_id', 'academic_session_id', 'category'],
            'uq_fee_structures_class_head_session_category',
        );
        $this->forge->processIndexes('fee_structures');

        $this->forge->dropColumn('fee_structures', 'route_id');
    }
}
