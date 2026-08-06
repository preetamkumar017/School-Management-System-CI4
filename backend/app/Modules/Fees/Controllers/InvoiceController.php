<?php

declare(strict_types=1);

namespace App\Modules\Fees\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Fees\DTOs\GenerateInvoiceRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/fees/invoices
 */
#[OA\Tag(name: 'Invoices')]
class InvoiceController extends BaseController
{
    #[OA\Post(
        path: '/fees/invoices',
        tags: ['Invoices'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/InvoiceGenerateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Generated — total_amount computed server-side (ADR-007 §1).', content: new OA\JsonContent(ref: '#/components/schemas/InvoiceResponse')),
            new OA\Response(response: 422, description: 'STUDENT_HAS_NO_SECTION.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body      = $this->request->getJSON(true) ?? [];
        $studentId = (int) ($body['student_id'] ?? 0);
        $sessionId = (int) ($body['academic_session_id'] ?? 0);
        $dueDate   = (string) ($body['due_date'] ?? '');

        $fields = [];

        if ($studentId <= 0) {
            $fields['student_id'] = 'student_id is required.';
        }

        if ($sessionId <= 0) {
            $fields['academic_session_id'] = 'academic_session_id is required.';
        }

        if (strtotime($dueDate) === false) {
            $fields['due_date'] = 'due_date is required and must be a valid date.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::invoiceService()->generateInvoice(new GenerateInvoiceRequest($studentId, $sessionId, $dueDate));

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/fees/invoices/{id}/apply-late-fee',
        tags: ['Invoices'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'BR-FEE-004 — explicit trigger, not a scheduled job (ADR-007 §4).', content: new OA\JsonContent(ref: '#/components/schemas/InvoiceResponse')),
            new OA\Response(response: 422, description: 'LATE_FEE_ALREADY_APPLIED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function applyLateFee(int $id)
    {
        $response = Services::invoiceService()->applyLateFee($id);

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/fees/invoices/{id}/flag-defaulter',
        tags: ['Invoices'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'BR-FEE-008 — explicit trigger, not a scheduled job (ADR-007 §4).', content: new OA\JsonContent(ref: '#/components/schemas/InvoiceResponse')),
            new OA\Response(response: 422, description: 'INVOICE_NOT_OVERDUE.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function flagDefaulter(int $id)
    {
        $response = Services::invoiceService()->flagOverdueAsDefaulter($id);

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/fees/invoices/{id}',
        tags: ['Invoices'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/InvoiceResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::invoiceService()->getInvoice($id)->toArray());
    }

    #[OA\Get(
        path: '/fees/invoices',
        tags: ['Invoices'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'student_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/InvoiceResponse')),
            ),
        ],
    )]
    public function index()
    {
        $studentId = (int) ($this->request->getGet('student_id') ?? 0);

        if ($studentId <= 0) {
            throw new ValidationException(['student_id' => 'student_id query parameter is required.']);
        }

        $responses = Services::invoiceService()->listByStudent($studentId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
