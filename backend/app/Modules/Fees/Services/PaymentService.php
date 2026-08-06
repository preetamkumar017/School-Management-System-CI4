<?php

declare(strict_types=1);

namespace App\Modules\Fees\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Fees\DTOs\PaymentResponse;
use App\Modules\Fees\DTOs\RecordPaymentRequest;
use App\Modules\Fees\DTOs\VoidRefundRequest;
use App\Modules\Fees\Entities\Invoice;
use App\Modules\Fees\Entities\Payment;
use App\Modules\Fees\Models\InvoiceModel;
use App\Modules\Fees\Models\PaymentModel;
use CodeIgniter\I18n\Time;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 * BR-FEE-002 (Finance-Team-only refund/void) is not enforced (ADR-007
 * §8) — matches this codebase's existing, consistent RBAC posture.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentModel $paymentModel,
        private readonly InvoiceModel $invoiceModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function recordPayment(RecordPaymentRequest $request): PaymentResponse
    {
        $invoice = $this->requireInvoice($request->invoiceId);

        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw new BusinessRuleException('INVOICE_CANCELLED', 'Cannot record a payment against a cancelled invoice.');
        }

        if (
            $request->gatewayTransactionRef !== null
            && $this->paymentModel->existsByGatewayTransactionRef($request->gatewayTransactionRef)
        ) {
            throw new BusinessRuleException(
                'DUPLICATE_PAYMENT_REFERENCE',
                'This gateway transaction reference has already been applied to another invoice (BR-FEE-006).',
            );
        }

        $id = $this->paymentModel->insert([
            'invoice_id'              => $request->invoiceId,
            'amount_paid'             => $request->amountPaid,
            'payment_mode'            => $request->paymentMode,
            'gateway_transaction_ref' => $request->gatewayTransactionRef,
            'paid_at'                 => Time::now()->toDateTimeString(),
            'status'                  => Payment::STATUS_SUCCESS,
        ], true);

        $payment = $this->paymentModel->find($id);

        $this->auditService->record('Payment', $id, AuditLog::ACTION_CREATE, null, $payment->toRawArray());

        // BR-FEE-001: is_locked becomes true the moment any payment is
        // recorded, and never reverts (ADR-007 §9).
        $totalPaid = $this->paymentModel->sumSuccessfulByInvoiceId($request->invoiceId);
        $newStatus = $totalPaid >= $invoice->total_amount ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID;

        $this->invoiceModel->update($request->invoiceId, [
            'status'    => $newStatus,
            'is_locked' => true,
        ]);

        return new PaymentResponse($payment);
    }

    public function voidPayment(int $id, VoidRefundRequest $request): PaymentResponse
    {
        return $this->changeStatus($id, Payment::STATUS_VOIDED, $request);
    }

    public function refundPayment(int $id, VoidRefundRequest $request): PaymentResponse
    {
        return $this->changeStatus($id, Payment::STATUS_REFUNDED, $request);
    }

    public function getPayment(int $id): PaymentResponse
    {
        return new PaymentResponse($this->requirePayment($id));
    }

    /**
     * @return list<PaymentResponse>
     */
    public function listByInvoice(int $invoiceId): array
    {
        return array_map(
            static fn (Payment $payment): PaymentResponse => new PaymentResponse($payment),
            $this->paymentModel->findByInvoiceId($invoiceId),
        );
    }

    private function changeStatus(int $id, string $status, VoidRefundRequest $request): PaymentResponse
    {
        $before = $this->requirePayment($id);

        if ($before->status !== Payment::STATUS_SUCCESS) {
            throw new BusinessRuleException(
                'PAYMENT_INVALID_STATUS_TRANSITION',
                "Cannot transition a payment from {$before->status} to {$status}.",
            );
        }

        $this->paymentModel->update($id, ['status' => $status]);
        $after = $this->paymentModel->find($id);

        $this->auditService->record(
            'Payment',
            $id,
            AuditLog::ACTION_OVERRIDE,
            $before->toRawArray(),
            $after->toRawArray(),
            $request->reason,
        );

        return new PaymentResponse($after);
    }

    private function requirePayment(int $id): Payment
    {
        $payment = $this->paymentModel->find($id);

        if ($payment === null) {
            throw new BusinessRuleException('PAYMENT_NOT_FOUND', 'Payment not found.');
        }

        return $payment;
    }

    private function requireInvoice(int $id): Invoice
    {
        $invoice = $this->invoiceModel->find($id);

        if ($invoice === null) {
            throw new BusinessRuleException('INVOICE_NOT_FOUND', 'Invoice not found.');
        }

        return $invoice;
    }
}
