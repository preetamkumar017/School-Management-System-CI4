<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-023-sis-id-card-generation.md §2 — additive nullable
 * photo_document_id, mirroring Route.driver_id's own shape
 * (2026-08-07-240003_AddDriverIdToRoutesTable.php): a Student can exist
 * before a photo is uploaded, and deleting the underlying Document must
 * not cascade-delete the Student — SET NULL, same as driver_id/vehicle_id.
 */
class AddPhotoDocumentIdToStudentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('students', [
            'photo_document_id' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'medical_info',
            ],
        ]);

        $this->forge->addKey('photo_document_id', false, false, 'idx_students_photo_document_id');
        $this->forge->addForeignKey('photo_document_id', 'documents', 'document_id', 'SET NULL', 'RESTRICT', 'fk_students_documents');
        $this->forge->processIndexes('students');
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if ($db->tableExists('students')) {
            $this->forge->dropForeignKey('students', 'fk_students_documents');
            $this->forge->dropKey('students', 'idx_students_photo_document_id');
            $this->forge->dropColumn('students', 'photo_document_id');
        }
    }
}
