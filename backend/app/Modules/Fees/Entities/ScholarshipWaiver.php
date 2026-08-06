<?php

declare(strict_types=1);

namespace App\Modules\Fees\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/fees/Phase-1-Domain-Model.md — ENT-FEE-005.
 *
 * @property int|null $scholarship_waiver_id
 * @property int      $student_id
 * @property int      $fee_head_id
 * @property string   $waiver_type
 * @property float    $waiver_amount
 */
class ScholarshipWaiver extends BaseEntity
{
    public const TYPE_RTE        = 'RTE';
    public const TYPE_MERIT      = 'MERIT';
    public const TYPE_SIBLING    = 'SIBLING';
    public const TYPE_STAFF_WARD = 'STAFF_WARD';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'scholarship_waiver_id' => 'integer',
            'student_id'            => 'integer',
            'fee_head_id'           => 'integer',
            'waiver_amount'         => 'float',
        ]);

        parent::__construct($data);
    }
}
