<?php

declare(strict_types=1);

namespace App\Modules\Fees\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Fees\DTOs\RecordPaymentRequest;
use App\Modules\Fees\DTOs\VoidRefundRequest;
use App\Modules\Fees\Entities\Payment;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/fees/payments
 */
#[OA\Tag(name: 'Payments')]
class PaymentController extends BaseController
{
    private const VALID_MODES = [
        Payment::MODE_ONLINE,
        Payment::MODE_CASH,
        Payment::MODE_CHEQUE,
        Payment::MODE_BANK_TRANSFER,
    ];

    #[OA\Post(
        path: '/fees/payments',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PaymentRecordRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Recorded — locks the invoice (BR-FEE-001).', content: new OA\JsonContent(ref: '#/components/schemas/PaymentResponse')),
            new OA\Response(response: 422, description: 'DUPLICATE_PAYMENT_REFERENCE or INVOICE_CANCELLED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body                  = $this->request->getJSON(true) ?? [];
        $invoiceId             = (int) ($body['invoice_id'] ?? 0);
        $amountPaid            = $body['amount_paid'] ?? null;
        $paymentMode           = (string) ($body['payment_mode'] ?? '');
        $gatewayTransactionRef = isset($body['gateway_transaction_ref']) && $body['gateway_transaction_ref'] !== ''
            ? (string) $body['gateway_transaction_ref']
            : null;

        $fields = [];

        if ($invoiceId <= 0) {
            $fields['invoice_id'] = 'invoice_id is required.';
        }

        if (! is_numeric($amountPaid) || (float) $amountPaid <= 0) {
            $fields['amount_paid'] = 'amount_paid is required and must be a positive number.';
        }

        if (! in_array($paymentMode, self::VALID_MODES, true)) {
            $fields['payment_mode'] = 'payment_mode must be one of ONLINE, CASH, CHEQUE, BANK_TRANSFER.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::paymentService()->recordPayment(
            new RecordPaymentRequest($invoiceId, (float) $amountPaid, $paymentMode, $gatewayTransactionRef),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/fees/payments/{id}/void',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string')])),
        responses: [new OA\Response(response: 200, description: 'BR-FEE-002 (role restriction not enforced, ADR-007 §8).', content: new OA\JsonContent(ref: '#/components/schemas/PaymentResponse'))],
    )]
    public function void(int $id)
    {
        $response = Services::paymentService()->voidPayment($id, new VoidRefundRequest($this->requireReason()));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/fees/payments/{id}/refund',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string')])),
        responses: [new OA\Response(response: 200, description: 'BR-FEE-002 (role restriction not enforced, ADR-007 §8).', content: new OA\JsonContent(ref: '#/components/schemas/PaymentResponse'))],
    )]
    public function refund(int $id)
    {
        $response = Services::paymentService()->refundPayment($id, new VoidRefundRequest($this->requireReason()));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/fees/payments/{id}',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/PaymentResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::paymentService()->getPayment($id)->toArray());
    }

    #[OA\Get(
        path: '/fees/payments',
        tags: ['Payments'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'invoice_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/PaymentResponse')),
            ),
        ],
    )]
    public function index()
    {
        $invoiceId = (int) ($this->request->getGet('invoice_id') ?? 0);

        if ($invoiceId <= 0) {
            throw new ValidationException(['invoice_id' => 'invoice_id query parameter is required.']);
        }

        $responses = Services::paymentService()->listByInvoice($invoiceId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    private function requireReason(): string
    {
        $body   = $this->request->getJSON(true) ?? [];
        $reason = (string) ($body['reason'] ?? '');

        if (trim($reason) === '') {
            throw new ValidationException(['reason' => 'reason is required.']);
        }

        return $reason;
    }
}
