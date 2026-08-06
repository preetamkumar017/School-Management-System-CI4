<?php

declare(strict_types=1);

namespace App\Modules\Fees\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/fees/Phase-1-Domain-Model.md — ENT-FEE-001.
 *
 * @property int|null $fee_head_id
 * @property string   $fee_head_name
 * @property bool      $is_taxable
 * @property float|null $gst_rate
 */
class FeeHead extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'fee_head_id' => 'integer',
            'is_taxable'  => 'boolean',
            'gst_rate'    => '?float',
        ]);

        parent::__construct($data);
    }
}
