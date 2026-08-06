<?php

declare(strict_types=1);

namespace App\Modules\Fees\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/fees/Phase-1-Domain-Model.md — ENT-FEE-004.
 *
 * @property int|null    $payment_id
 * @property int         $invoice_id
 * @property float       $amount_paid
 * @property string      $payment_mode
 * @property string|null $gateway_transaction_ref
 * @property \CodeIgniter\I18n\Time $paid_at
 * @property string      $status
 */
class Payment extends BaseEntity
{
    public const MODE_ONLINE        = 'ONLINE';
    public const MODE_CASH          = 'CASH';
    public const MODE_CHEQUE        = 'CHEQUE';
    public const MODE_BANK_TRANSFER = 'BANK_TRANSFER';

    public const STATUS_SUCCESS  = 'SUCCESS';
    public const STATUS_FAILED   = 'FAILED';
    public const STATUS_REFUNDED = 'REFUNDED';
    public const STATUS_VOIDED   = 'VOIDED';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'payment_id'  => 'integer',
            'invoice_id'  => 'integer',
            'amount_paid' => 'float',
        ]);

        $this->dates = array_merge($this->dates, ['paid_at']);

        parent::__construct($data);
    }
}
