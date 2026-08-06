<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/sis/Phase-4.2-Domain-Model.md — ENT-SIS-001 (Revision 2).
 */
class CreateStudentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'student_id'       => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'admission_number' => ['type' => 'VARCHAR', 'constraint' => 20],
            'full_name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'dob'              => ['type' => 'DATE'],
            'aadhaar_number'   => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'section_id'       => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'application_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'category'         => [
                'type'       => 'ENUM',
                'constraint' => ['GENERAL', 'RTE'],
                'default'    => 'GENERAL',
            ],
            'medical_info' => ['type' => 'TEXT', 'null' => true],
            'status'       => [
                'type'       => 'ENUM',
                'constraint' => ['DRAFT', 'ACTIVE', 'PROMOTED', 'EXITED', 'ARCHIVED'],
                'default'    => 'DRAFT',
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('student_id', true);
        $this->forge->addUniqueKey('admission_number', 'uq_students_admission_number');
        $this->forge->addUniqueKey('aadhaar_number', 'uq_students_aadhaar_number');
        $this->forge->addUniqueKey('application_id', 'uq_students_application_id');
        $this->forge->addKey('section_id', false, false, 'idx_students_section_id');
        $this->forge->addKey('status', false, false, 'idx_students_status');

        // section_id (Academic's Section) and application_id (Admission's
        // Application) are cross-module references — no DB-level FK across
        // the module boundary; validated via the owning module's Service
        // in the Service layer instead.

        $this->forge->createTable('students', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('students', true);
    }
}
