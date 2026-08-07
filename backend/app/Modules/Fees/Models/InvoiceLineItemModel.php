<?php

declare(strict_types=1);

namespace App\Modules\Fees\Models;

use App\Core\BaseModel;
use App\Modules\Fees\Entities\InvoiceLineItem;

/**
 * docs/ADR/ADR-020-fees-gst-line-items.md
 */
class InvoiceLineItemModel extends BaseModel
{
    protected $table          = 'invoice_line_items';
    protected $primaryKey     = 'invoice_line_item_id';
    protected $returnType     = InvoiceLineItem::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'invoice_id',
        'fee_head_id',
        'base_amount',
        'waiver_amount',
        'taxable_amount',
        'gst_rate',
        'gst_amount',
        'line_total',
        'created_by',
        'updated_by',
    ];

    /**
     * @return list<InvoiceLineItem>
     */
    public function findByInvoiceId(int $invoiceId): array
    {
        return $this->where('invoice_id', $invoiceId)->findAll();
    }

    /**
     * ADR-020: line items are always regenerated together with their
     * invoice (generateInvoice, recalculateForRouteChange) — a hard
     * delete of the previous set, not a soft-delete/history trail, since
     * they carry no independent audit meaning apart from the invoice
     * that owns them.
     */
    public function deleteByInvoiceId(int $invoiceId): void
    {
        $this->where('invoice_id', $invoiceId)->delete(null, true);
    }
}
