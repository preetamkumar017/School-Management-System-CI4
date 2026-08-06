<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/fees/Phase-1-Domain-Model.md — ENT-FEE-003. Includes the
 * decided additive late_fee_applied column (ADR-007 §4/Phase 3).
 */
class CreateInvoicesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'invoice_id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'invoice_no'          => ['type' => 'VARCHAR', 'constraint' => 20],
            'student_id'          => ['type' => 'BIGINT', 'unsigned' => true],
            'academic_session_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'total_amount'        => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'due_date'            => ['type' => 'DATE'],
            'status'              => [
                'type'       => 'ENUM',
                'constraint' => ['UNPAID', 'PARTIALLY_PAID', 'PAID', 'DEFAULTER', 'CANCELLED'],
                'default'    => 'UNPAID',
            ],
            'is_locked'        => ['type' => 'BOOLEAN', 'default' => false],
            'late_fee_applied' => ['type' => 'BOOLEAN', 'default' => false],
            'created_by'       => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_by'       => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'       => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'       => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('invoice_id', true);
        $this->forge->addUniqueKey('invoice_no', 'uq_invoices_invoice_no');
        $this->forge->addKey('student_id', false, false, 'idx_invoices_student_id');
        $this->forge->addKey('status', false, false, 'idx_invoices_status');
        $this->forge->addKey('due_date', false, false, 'idx_invoices_due_date');

        // student_id/academic_session_id are cross-module references — no
        // DB-level FK.

        $this->forge->createTable('invoices', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('invoices', true);
    }
}
