<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/sis/Phase-4.2-Domain-Model.md — ENT-SYS-003.
 */
class CreateGuardiansTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'guardian_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'full_name'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'relationship'   => ['type' => 'ENUM', 'constraint' => ['FATHER', 'MOTHER', 'GUARDIAN', 'OTHER']],
            'mobile_number'  => ['type' => 'VARCHAR', 'constraint' => 10],
            'email'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'     => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('guardian_id', true);
        // mobile_number is deliberately not unique (Phase 4.2/Appendix-G's
        // Unique Constraints explicitly state it isn't enforced-unique —
        // GuardianModel::findByMobileNumber returns a list, not one row).
        $this->forge->addKey('mobile_number', false, false, 'idx_guardians_mobile_number');

        $this->forge->createTable('guardians', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('guardians', true);
    }
}
