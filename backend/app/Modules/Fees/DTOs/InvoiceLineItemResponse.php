<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

use App\Modules\Fees\Entities\InvoiceLineItem;

/**
 * docs/ADR/ADR-020-fees-gst-line-items.md
 */
final class InvoiceLineItemResponse
{
    public readonly int $invoiceLineItemId;
    public readonly int $invoiceId;
    public readonly int $feeHeadId;
    public readonly float $baseAmount;
    public readonly float $waiverAmount;
    public readonly float $taxableAmount;
    public readonly ?float $gstRate;
    public readonly float $gstAmount;
    public readonly float $lineTotal;

    public function __construct(InvoiceLineItem $lineItem)
    {
        $this->invoiceLineItemId = $lineItem->invoice_line_item_id;
        $this->invoiceId         = $lineItem->invoice_id;
        $this->feeHeadId         = $lineItem->fee_head_id;
        $this->baseAmount        = $lineItem->base_amount;
        $this->waiverAmount      = $lineItem->waiver_amount;
        $this->taxableAmount     = $lineItem->taxable_amount;
        $this->gstRate           = $lineItem->gst_rate;
        $this->gstAmount         = $lineItem->gst_amount;
        $this->lineTotal         = $lineItem->line_total;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoice_line_item_id' => $this->invoiceLineItemId,
            'invoice_id'           => $this->invoiceId,
            'fee_head_id'          => $this->feeHeadId,
            'base_amount'          => $this->baseAmount,
            'waiver_amount'        => $this->waiverAmount,
            'taxable_amount'       => $this->taxableAmount,
            'gst_rate'             => $this->gstRate,
            'gst_amount'           => $this->gstAmount,
            'line_total'           => $this->lineTotal,
        ];
    }
}
