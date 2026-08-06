<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/fees/Phase-1-Domain-Model.md — ENT-FEE-004.
 */
class CreatePaymentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'payment_id'              => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'invoice_id'              => ['type' => 'BIGINT', 'unsigned' => true],
            'amount_paid'             => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'payment_mode'            => [
                'type'       => 'ENUM',
                'constraint' => ['ONLINE', 'CASH', 'CHEQUE', 'BANK_TRANSFER'],
            ],
            'gateway_transaction_ref' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'paid_at'                 => ['type' => 'DATETIME'],
            'status'                  => [
                'type'       => 'ENUM',
                'constraint' => ['SUCCESS', 'FAILED', 'REFUNDED', 'VOIDED'],
                'default'    => 'SUCCESS',
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('payment_id', true);
        $this->forge->addUniqueKey('gateway_transaction_ref', 'uq_payments_gateway_transaction_ref');
        $this->forge->addKey('invoice_id', false, false, 'idx_payments_invoice_id');

        // invoice_id is intra-module — real FK.
        $this->forge->addForeignKey('invoice_id', 'invoices', 'invoice_id', 'RESTRICT', 'RESTRICT', 'fk_payments_invoices');

        $this->forge->createTable('payments', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('payments', true);
    }
}
