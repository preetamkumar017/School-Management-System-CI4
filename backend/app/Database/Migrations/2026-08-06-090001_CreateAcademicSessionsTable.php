<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — ENT-ACAD-001.
 */
class CreateAcademicSessionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'academic_session_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'session_name'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'start_date'          => ['type' => 'DATE'],
            'end_date'            => ['type' => 'DATE'],
            'status'              => [
                'type'       => 'ENUM',
                'constraint' => ['PLANNED', 'ACTIVE', 'CLOSED', 'ARCHIVED'],
                'default'    => 'PLANNED',
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('academic_session_id', true);
        // Known simplification: not soft-delete-aware (same tradeoff Role's
        // unique key made) — AcademicSession is "created ahead of the
        // session... rarely modified" (Phase 1), so reuse of a soft-deleted
        // session_name isn't a real scenario worth the generated-column
        // workaround yet.
        $this->forge->addUniqueKey('session_name', 'uq_academic_sessions_session_name');

        // Non-overlapping date-range constraint is a cross-row rule,
        // enforced in AcademicSessionService, not here (Phase 1 §4.8).

        $this->forge->createTable('academic_sessions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('academic_sessions', true);
    }
}
