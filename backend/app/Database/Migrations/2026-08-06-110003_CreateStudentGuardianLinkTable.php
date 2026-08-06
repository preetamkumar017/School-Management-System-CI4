<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/sis/Phase-4.2-Domain-Model.md — junction `StudentGuardianLink`.
 * Intra-module (both Student and Guardian live in Sis), so a real DB-level
 * FK is used here — unlike Admission/Academic's cross-module references.
 */
class CreateStudentGuardianLinkTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'student_id'         => ['type' => 'BIGINT', 'unsigned' => true],
            'guardian_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'is_primary_contact' => ['type' => 'BOOLEAN', 'default' => false],
        ]);

        $this->forge->addPrimaryKey(['student_id', 'guardian_id']);

        $this->forge->addForeignKey('student_id', 'students', 'student_id', 'RESTRICT', 'RESTRICT', 'fk_student_guardian_link_students');
        $this->forge->addForeignKey('guardian_id', 'guardians', 'guardian_id', 'RESTRICT', 'RESTRICT', 'fk_student_guardian_link_guardians');

        $this->forge->createTable('student_guardian_link', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('student_guardian_link', true);
    }
}
