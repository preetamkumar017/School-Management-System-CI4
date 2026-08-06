<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

final class RecordPaymentRequest
{
    public function __construct(
        public readonly int $invoiceId,
        public readonly float $amountPaid,
        public readonly string $paymentMode,
        public readonly ?string $gatewayTransactionRef,
    ) {
    }
}
