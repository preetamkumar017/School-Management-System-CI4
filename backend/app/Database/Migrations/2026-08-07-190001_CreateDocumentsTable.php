<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/administration/Phase-8-Document-Design.md — ENT-SYS-006.
 * `PayrollRun` added to the owner_type enum beyond Appendix-G's literal
 * list — payslips are a named gap this pass closes (ADR-012 §1).
 */
class CreateDocumentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'document_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'owner_type'  => [
                'type'       => 'ENUM',
                'constraint' => ['Application', 'Student', 'Invoice', 'ReportCard', 'PayrollRun'],
            ],
            'owner_ref_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'document_type'  => ['type' => 'VARCHAR', 'constraint' => 50],
            'file_path'      => ['type' => 'VARCHAR', 'constraint' => 500],
            'uploaded_by'    => ['type' => 'BIGINT', 'unsigned' => true],
            'uploaded_at'    => ['type' => 'DATETIME'],
            'created_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'     => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('document_id', true);
        $this->forge->addKey(['owner_type', 'owner_ref_id'], false, false, 'idx_documents_owner');

        // owner_ref_id is a cross-module polymorphic reference — no
        // DB-level FK. uploaded_by is a plain reference to Administration's
        // own users table (intra-module) — also no DB-level FK, since
        // Administration's own User/Document entities don't use real FKs
        // between each other today (matches AuditLog's existing pattern).

        $this->forge->createTable('documents', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('documents', true);
    }
}
