<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-020-fees-gst-line-items.md — new ENT-FEE-006, reversing
 * ADR-007 §1's "no line-item entity" decision specifically for
 * BR-FEE-007's itemization requirement.
 */
class CreateInvoiceLineItemsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'invoice_line_item_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'invoice_id'           => ['type' => 'BIGINT', 'unsigned' => true],
            'fee_head_id'          => ['type' => 'BIGINT', 'unsigned' => true],
            'base_amount'          => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'waiver_amount'        => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'taxable_amount'       => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'gst_rate'             => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'gst_amount'           => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'line_total'           => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'created_by'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_by'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'           => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('invoice_line_item_id', true);
        $this->forge->addKey('invoice_id');

        // Both invoice_id and fee_head_id are intra-module — real FKs.
        // CASCADE on invoice_id: a line item has no meaning once its
        // parent invoice is gone, unlike Trip's RESTRICT-everything
        // historical-log posture (ADR-019 §2) — line items are always
        // regenerated together with their invoice (generateInvoice,
        // recalculateForRouteChange), never standalone history.
        $this->forge->addForeignKey('invoice_id', 'invoices', 'invoice_id', 'CASCADE', 'CASCADE', 'fk_invoice_line_items_invoices');
        $this->forge->addForeignKey('fee_head_id', 'fee_heads', 'fee_head_id', 'RESTRICT', 'RESTRICT', 'fk_invoice_line_items_fee_heads');

        $this->forge->createTable('invoice_line_items', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('invoice_line_items', true);
    }
}
