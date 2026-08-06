<?php

declare(strict_types=1);

namespace App\Modules\Fees\Models;

use App\Core\BaseModel;
use App\Modules\Fees\Entities\Payment;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
class PaymentModel extends BaseModel
{
    protected $table          = 'payments';
    protected $primaryKey     = 'payment_id';
    protected $returnType     = Payment::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'invoice_id',
        'amount_paid',
        'payment_mode',
        'gateway_transaction_ref',
        'paid_at',
        'status',
        'created_by',
        'updated_by',
    ];

    public function existsByGatewayTransactionRef(string $value): bool
    {
        return $this->where('gateway_transaction_ref', $value)->countAllResults() > 0;
    }

    /**
     * @return list<Payment>
     */
    public function findByInvoiceId(int $invoiceId): array
    {
        return $this->where('invoice_id', $invoiceId)->findAll();
    }

    public function sumSuccessfulByInvoiceId(int $invoiceId): float
    {
        $row = $this->asArray()
            ->selectSum('amount_paid')
            ->where('invoice_id', $invoiceId)
            ->where('status', Payment::STATUS_SUCCESS)
            ->first();

        return (float) ($row['amount_paid'] ?? 0.0);
    }
}
