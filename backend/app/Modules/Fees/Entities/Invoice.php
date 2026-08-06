<?php

declare(strict_types=1);

namespace App\Modules\Fees\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/fees/Phase-1-Domain-Model.md — ENT-FEE-003.
 *
 * @property int|null $invoice_id
 * @property string   $invoice_no
 * @property int      $student_id
 * @property int      $academic_session_id
 * @property float    $total_amount
 * @property string   $due_date
 * @property string   $status
 * @property bool     $is_locked
 * @property bool     $late_fee_applied
 */
class Invoice extends BaseEntity
{
    public const STATUS_UNPAID         = 'UNPAID';
    public const STATUS_PARTIALLY_PAID = 'PARTIALLY_PAID';
    public const STATUS_PAID           = 'PAID';
    public const STATUS_DEFAULTER      = 'DEFAULTER';
    public const STATUS_CANCELLED      = 'CANCELLED';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'invoice_id'          => 'integer',
            'student_id'          => 'integer',
            'academic_session_id' => 'integer',
            'total_amount'        => 'float',
            'is_locked'           => 'boolean',
            'late_fee_applied'    => 'boolean',
        ]);

        parent::__construct($data);
    }
}
