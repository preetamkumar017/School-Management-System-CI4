<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

use App\Modules\Fees\Entities\Payment;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
final class PaymentResponse
{
    public readonly int $paymentId;
    public readonly int $invoiceId;
    public readonly float $amountPaid;
    public readonly string $paymentMode;
    public readonly ?string $gatewayTransactionRef;
    public readonly string $paidAt;
    public readonly string $status;

    public function __construct(Payment $payment)
    {
        $this->paymentId             = $payment->payment_id;
        $this->invoiceId             = $payment->invoice_id;
        $this->amountPaid            = $payment->amount_paid;
        $this->paymentMode           = $payment->payment_mode;
        $this->gatewayTransactionRef = $payment->gateway_transaction_ref;
        $this->paidAt                = $payment->paid_at->toDateTimeString();
        $this->status                = $payment->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'payment_id'              => $this->paymentId,
            'invoice_id'              => $this->invoiceId,
            'amount_paid'             => $this->amountPaid,
            'payment_mode'            => $this->paymentMode,
            'gateway_transaction_ref' => $this->gatewayTransactionRef,
            'paid_at'                 => $this->paidAt,
            'status'                  => $this->status,
        ];
    }
}
