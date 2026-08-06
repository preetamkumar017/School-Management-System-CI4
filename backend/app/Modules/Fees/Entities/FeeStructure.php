<?php

declare(strict_types=1);

namespace App\Modules\Fees\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/fees/Phase-1-Domain-Model.md — ENT-FEE-002.
 *
 * @property int|null $fee_structure_id
 * @property int      $class_id
 * @property int      $fee_head_id
 * @property int      $academic_session_id
 * @property string   $category
 * @property float    $amount
 */
class FeeStructure extends BaseEntity
{
    public const CATEGORY_GENERAL = 'GENERAL';
    public const CATEGORY_RTE     = 'RTE';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'fee_structure_id'    => 'integer',
            'class_id'            => 'integer',
            'fee_head_id'         => 'integer',
            'academic_session_id' => 'integer',
            'amount'              => 'float',
        ]);

        parent::__construct($data);
    }
}
