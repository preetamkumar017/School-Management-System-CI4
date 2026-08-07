<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/library/Phase-1-Domain-Model.md — ENT-LIB-002. Includes the
 * decided additive status/replacement_charge_amount/fine_settled columns
 * (ADR-009 §1, §4, §6).
 */
class CreateBookIssuesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'book_issue_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'book_id'       => ['type' => 'BIGINT', 'unsigned' => true],
            'borrower_type' => [
                'type'       => 'ENUM',
                'constraint' => ['Student', 'Employee'],
            ],
            'borrower_ref_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'issue_date'      => ['type' => 'DATE'],
            'due_date'        => ['type' => 'DATE'],
            'return_date'     => ['type' => 'DATE', 'null' => true],
            'fine_amount'     => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0.00],
            'status'          => [
                'type'       => 'ENUM',
                'constraint' => ['Issued', 'Returned', 'Lost'],
                'default'    => 'Issued',
            ],
            'replacement_charge_amount' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'default' => 0.00],
            'fine_settled'               => ['type' => 'BOOLEAN', 'default' => false],
            'created_by'                 => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'                 => ['type' => 'DATETIME', 'null' => true],
            'updated_by'                 => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'                 => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'                 => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'                 => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'                 => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('book_issue_id', true);
        $this->forge->addKey('book_id', false, false, 'idx_book_issues_book_id');
        $this->forge->addKey(['borrower_type', 'borrower_ref_id'], false, false, 'idx_book_issues_borrower');
        $this->forge->addKey('due_date', false, false, 'idx_book_issues_due_date');

        // book_id is intra-module — real FK. borrower_ref_id is a
        // cross-module polymorphic reference (Student or Employee per
        // borrower_type) — no DB-level FK.
        $this->forge->addForeignKey('book_id', 'books', 'book_id', 'RESTRICT', 'RESTRICT', 'fk_book_issues_books');

        $this->forge->createTable('book_issues', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('book_issues', true);
    }
}
