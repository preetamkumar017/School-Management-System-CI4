<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

use App\Modules\Fees\Entities\FeeHead;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
final class FeeHeadResponse
{
    public readonly int $feeHeadId;
    public readonly string $feeHeadName;
    public readonly bool $isTaxable;
    public readonly ?float $gstRate;

    public function __construct(FeeHead $feeHead)
    {
        $this->feeHeadId   = $feeHead->fee_head_id;
        $this->feeHeadName = $feeHead->fee_head_name;
        $this->isTaxable   = $feeHead->is_taxable;
        $this->gstRate     = $feeHead->gst_rate;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fee_head_id'   => $this->feeHeadId,
            'fee_head_name' => $this->feeHeadName,
            'is_taxable'    => $this->isTaxable,
            'gst_rate'      => $this->gstRate,
        ];
    }
}
