<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — junction `ClassSubjectMap`.
 * No surrogate PK, no audit-column baseline — same modeling choice as
 * StudentGuardianLink in the SIS domain design.
 */
class CreateClassSubjectMapTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'class_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'subject_id' => ['type' => 'BIGINT', 'unsigned' => true],
        ]);

        $this->forge->addPrimaryKey(['class_id', 'subject_id']);

        $this->forge->addForeignKey('class_id', 'classes', 'class_id', 'RESTRICT', 'RESTRICT', 'fk_class_subject_map_classes');
        $this->forge->addForeignKey('subject_id', 'subjects', 'subject_id', 'RESTRICT', 'RESTRICT', 'fk_class_subject_map_subjects');

        $this->forge->createTable('class_subject_map', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('class_subject_map', true);
    }
}
