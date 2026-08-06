<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/admission/Phase-1-Domain-Model.md — ENT-ADM-001.
 */
class CreateApplicationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'application_id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'application_reference_no' => ['type' => 'VARCHAR', 'constraint' => 20],
            'applicant_name'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'dob'                     => ['type' => 'DATE'],
            'class_applied_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'aadhaar_number'          => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'category'                => [
                'type'       => 'ENUM',
                'constraint' => ['GENERAL', 'RTE'],
                'default'    => 'GENERAL',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['SUBMITTED', 'VERIFIED', 'SHORTLISTED', 'WAITLISTED', 'ADMITTED', 'REJECTED'],
                'default'    => 'SUBMITTED',
            ],
            'submitted_at' => ['type' => 'DATETIME'],
            'decided_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'   => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('application_id', true);
        $this->forge->addUniqueKey('application_reference_no', 'uq_applications_reference_no');
        $this->forge->addKey('status', false, false, 'idx_applications_status');
        $this->forge->addKey('class_applied_id', false, false, 'idx_applications_class_applied_id');

        // class_applied_id is a cross-module reference to Academic's
        // classes table — no DB-level FK across the module boundary
        // (Company Development Standard's cross-module rule); validated
        // via Academic's ClassService in the Service layer instead.

        $this->forge->createTable('applications', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('applications', true);
    }
}
