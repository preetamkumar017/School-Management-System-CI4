<?php

declare(strict_types=1);

namespace App\Modules\Fees\Entities;

use App\Core\BaseEntity;

/**
 * docs/ADR/ADR-020-fees-gst-line-items.md — ENT-FEE-006.
 *
 * @property int|null   $invoice_line_item_id
 * @property int        $invoice_id
 * @property int        $fee_head_id
 * @property float       $base_amount
 * @property float       $waiver_amount
 * @property float       $taxable_amount
 * @property float|null $gst_rate
 * @property float       $gst_amount
 * @property float       $line_total
 */
class InvoiceLineItem extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'invoice_line_item_id' => 'integer',
            'invoice_id'           => 'integer',
            'fee_head_id'          => 'integer',
            'base_amount'          => 'float',
            'waiver_amount'        => 'float',
            'taxable_amount'       => 'float',
            'gst_rate'             => '?float',
            'gst_amount'           => 'float',
            'line_total'           => 'float',
        ]);

        parent::__construct($data);
    }
}
