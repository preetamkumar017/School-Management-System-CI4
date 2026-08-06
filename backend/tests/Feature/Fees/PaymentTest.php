<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Fees\Models\InvoiceModel;
use Tests\Support\Fees\FeesTestCase;

/**
 * @internal
 */
final class PaymentTest extends FeesTestCase
{
    public function testFullPaymentLocksTheInvoiceAndMarksItPaid(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $invoiceId = $this->createInvoiceFixture(null, null, 5000.0);

        $pay = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/payments', [
            'invoice_id'   => $invoiceId,
            'amount_paid'  => 5000,
            'payment_mode' => 'CASH',
        ]);
        $pay->assertStatus(201);

        $invoice = (new InvoiceModel())->find($invoiceId);
        $this->assertSame('PAID', $invoice->status);
        $this->assertTrue($invoice->is_locked);
    }

    public function testPartialPaymentMarksTheInvoicePartiallyPaid(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $invoiceId = $this->createInvoiceFixture(null, null, 5000.0);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/payments', [
            'invoice_id'   => $invoiceId,
            'amount_paid'  => 3000,
            'payment_mode' => 'CASH',
        ])->assertStatus(201);

        $invoice = (new InvoiceModel())->find($invoiceId);
        $this->assertSame('PARTIALLY_PAID', $invoice->status);
        $this->assertTrue($invoice->is_locked);
    }

    public function testDuplicateGatewayTransactionReferenceIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $ref     = 'TXN' . uniqid('', true);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/payments', [
            'invoice_id'              => $this->createInvoiceFixture(),
            'amount_paid'             => 1000,
            'payment_mode'            => 'ONLINE',
            'gateway_transaction_ref' => $ref,
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/payments', [
                'invoice_id'              => $this->createInvoiceFixture(),
                'amount_paid'             => 1000,
                'payment_mode'            => 'ONLINE',
                'gateway_transaction_ref' => $ref,
            ]),
            BusinessRuleException::class,
            'DUPLICATE_PAYMENT_REFERENCE',
            422,
        );
    }

    public function testVoidingAPaymentDoesNotReopenTheInvoice(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $invoiceId = $this->createInvoiceFixture(null, null, 5000.0);
        $paymentId = $this->createPaymentFixture($invoiceId, 5000.0);

        (new InvoiceModel())->update($invoiceId, ['status' => 'PAID', 'is_locked' => true]);

        $void = $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/fees/payments/{$paymentId}/void", [
            'reason' => 'Payment recorded in error.',
        ]);
        $void->assertStatus(200);
        $this->assertSame('VOIDED', $this->decode($void)['data']['status']);

        $invoice = (new InvoiceModel())->find($invoiceId);
        $this->assertSame('PAID', $invoice->status);
        $this->assertTrue($invoice->is_locked);
    }
}
