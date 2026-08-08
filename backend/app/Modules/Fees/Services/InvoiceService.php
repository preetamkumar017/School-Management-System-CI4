<?php

declare(strict_types=1);

namespace App\Modules\Fees\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\RequestContext;
use App\Core\Pdf\PdfRenderer;
use App\Modules\Administration\DTOs\DocumentResponse;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\ConfigurationService;
use App\Modules\Administration\Services\DocumentService;
use App\Modules\Fees\DTOs\GenerateInvoiceRequest;
use App\Modules\Fees\DTOs\InvoiceLineItemResponse;
use App\Modules\Fees\DTOs\InvoiceResponse;
use App\Modules\Fees\Entities\Invoice;
use App\Modules\Fees\Entities\InvoiceLineItem;
use App\Modules\Fees\Models\FeeHeadModel;
use App\Modules\Fees\Models\InvoiceLineItemModel;
use App\Modules\Fees\Models\InvoiceModel;
use App\Modules\Fees\Models\ScholarshipWaiverModel;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 * docs/ADR/ADR-020-fees-gst-line-items.md (BR-FEE-007) — line-item
 * generation/regeneration.
 *
 * RBAC (ADR-024 §3, Phase 2): `fees.manage` (Tier 1) gates
 * generation/late-fee/defaulter-flagging (staff only). `getInvoice()`/
 * `getLineItems()`/`listByStudent()`/`generateInvoicePdf()` allow Tier 2 —
 * a Student may read/download their own Invoice. Void/refund stays
 * exactly as ADR-018 already wired it on `PaymentService`, not touched
 * here. `recalculateForRouteChange()` stays ungated — it's Transport's
 * one-way internal trigger (`TransportAllocationService::changeRoute()`),
 * not a user-facing Fees write.
 */
class InvoiceService
{
    public const PERMISSION_MANAGE = 'fees.manage';

    public function __construct(
        private readonly InvoiceModel $invoiceModel,
        private readonly InvoiceLineItemModel $invoiceLineItemModel,
        private readonly ScholarshipWaiverModel $scholarshipWaiverModel,
        private readonly FeeHeadModel $feeHeadModel,
        private readonly AuditService $auditService,
        private readonly ConfigurationService $configurationService,
        private readonly DocumentService $documentService,
        private readonly PdfRenderer $pdfRenderer,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    /**
     * ADR-007 §1/§2: total_amount is computed (sum of matching
     * FeeStructure rows minus matching ScholarshipWaivers), never
     * client-supplied; the student must have a section_id (resolves to
     * class_id) or generation is rejected.
     *
     * ADR-014 §1 (BR-FEE-003): if the student has an active
     * TransportAllocation, its route_id is folded into the FeeStructure
     * lookup so a route-tier fee is included automatically — Fees
     * querying Transport here is the same "reach into another module for
     * a fact needed by this request" shape already used for
     * StudentService/AcademicSessionService/SectionService above, not a
     * live/polling dependency.
     */
    public function generateInvoice(GenerateInvoiceRequest $request): InvoiceResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $student = AppServices::studentService()->getStudentForCrossModuleRead($request->studentId);

        if ($student->sectionId === null) {
            throw new BusinessRuleException(
                'STUDENT_HAS_NO_SECTION',
                'This student has no section assigned; a class cannot be resolved for invoicing.',
            );
        }

        AppServices::academicSessionService()->getSession($request->academicSessionId);

        $section = AppServices::sectionService()->getSection($student->sectionId);
        $routeId = AppServices::transportAllocationService()->getActiveAllocationForStudent($request->studentId)?->routeId;

        $lineItems   = $this->buildLineItems($section->classId, $request->academicSessionId, $student->category, $routeId, $request->studentId);
        $totalAmount = array_sum(array_column($lineItems, 'line_total'));

        $id = $this->invoiceModel->insert([
            'invoice_no'          => $this->generateInvoiceNo(),
            'student_id'          => $request->studentId,
            'academic_session_id' => $request->academicSessionId,
            'total_amount'        => round($totalAmount, 2),
            'due_date'            => $request->dueDate,
            'status'              => Invoice::STATUS_UNPAID,
        ], true);

        $this->persistLineItems($id, $lineItems);

        $invoice = $this->invoiceModel->find($id);

        $this->auditService->record('Invoice', $id, AuditLog::ACTION_CREATE, null, $invoice->toRawArray());

        return new InvoiceResponse($invoice);
    }

    /**
     * ADR-014 §1 (BR-TRN-005): explicit trigger, not a scheduled job —
     * same shape as applyLateFee/flagOverdueAsDefaulter. Called by
     * TransportAllocationService::changeRoute() the moment a student's
     * route actually changes; Fees never watches Transport for this.
     * Only untouched UNPAID invoices are recomputed (see
     * InvoiceModel::findRecalculableByStudentId) — anything already
     * paid against, partially paid, defaulted, cancelled, or locked is
     * left exactly as it was.
     *
     * @return list<InvoiceResponse>
     */
    public function recalculateForRouteChange(int $studentId, ?int $newRouteId): array
    {
        $student = AppServices::studentService()->getStudentForCrossModuleRead($studentId);

        if ($student->sectionId === null) {
            return [];
        }

        $section = AppServices::sectionService()->getSection($student->sectionId);
        $updated = [];

        foreach ($this->invoiceModel->findRecalculableByStudentId($studentId) as $before) {
            $invoiceId = $before->invoice_id;

            if ($invoiceId === null) {
                continue;
            }

            $lineItems   = $this->buildLineItems($section->classId, $before->academic_session_id, $student->category, $newRouteId, $studentId);
            $totalAmount = array_sum(array_column($lineItems, 'line_total'));

            $this->invoiceModel->update($invoiceId, ['total_amount' => round($totalAmount, 2)]);

            // ADR-020: line items are regenerated, not patched — the
            // previous set is discarded and replaced wholesale, the same
            // "recompute from scratch" posture computeTotalAmount already
            // used before this ADR, now extended to the line-item table.
            $this->invoiceLineItemModel->deleteByInvoiceId($invoiceId);
            $this->persistLineItems($invoiceId, $lineItems);

            $after = $this->requireInvoice($invoiceId);

            $this->auditService->record('Invoice', $invoiceId, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

            $updated[] = new InvoiceResponse($after);
        }

        return $updated;
    }

    /**
     * ADR-014 §2 (BR-SIS-001): what PromotionService now queries instead
     * of taking a caller-supplied boolean — true when this student has
     * no outstanding (UNPAID/PARTIALLY_PAID/DEFAULTER) invoice for the
     * session being closed out of.
     */
    public function hasOutstandingBalance(int $studentId, int $academicSessionId): bool
    {
        return $this->invoiceModel->existsOutstandingByStudentIdAndSession($studentId, $academicSessionId);
    }

    /**
     * ADR-020 (BR-FEE-007): one line item per matching FeeStructure row —
     * the route-tier fee row (ADR-014 §1) is just another FeeStructure
     * match here, keyed to its own FeeHead like any other, not a special
     * case. GST is computed on the post-waiver (net) amount — ADR-020 §c.
     *
     * @return list<array{fee_head_id: int, base_amount: float, waiver_amount: float, taxable_amount: float, gst_rate: ?float, gst_amount: float, line_total: float}>
     */
    private function buildLineItems(int $classId, int $academicSessionId, string $category, ?int $routeId, int $studentId): array
    {
        $structures = AppServices::feeStructureService()->listByClassSessionCategory($classId, $academicSessionId, $category, $routeId);

        $feeHeadIds = array_map(static fn ($structure) => $structure->feeHeadId, $structures);
        $waivers    = $this->scholarshipWaiverModel->findByStudentIdAndFeeHeadIds($studentId, $feeHeadIds);

        $waiverByFeeHead = [];

        foreach ($waivers as $waiver) {
            $waiverByFeeHead[$waiver->fee_head_id] = ($waiverByFeeHead[$waiver->fee_head_id] ?? 0.0) + $waiver->waiver_amount;
        }

        $lineItems = [];

        foreach ($structures as $structure) {
            $feeHead      = $this->feeHeadModel->find($structure->feeHeadId);
            $waiverAmount = min($structure->amount, $waiverByFeeHead[$structure->feeHeadId] ?? 0.0);
            $taxable      = max(0.0, $structure->amount - $waiverAmount);
            $gstRate      = $feeHead !== null && $feeHead->is_taxable ? $feeHead->gst_rate : null;
            $gstAmount    = $gstRate !== null ? round($taxable * ($gstRate / 100), 2) : 0.0;

            $lineItems[] = [
                'fee_head_id'    => $structure->feeHeadId,
                'base_amount'    => round($structure->amount, 2),
                'waiver_amount'  => round($waiverAmount, 2),
                'taxable_amount' => round($taxable, 2),
                'gst_rate'       => $gstRate,
                'gst_amount'     => $gstAmount,
                'line_total'     => round($taxable + $gstAmount, 2),
            ];
        }

        return $lineItems;
    }

    /**
     * @param list<array{fee_head_id: int, base_amount: float, waiver_amount: float, taxable_amount: float, gst_rate: ?float, gst_amount: float, line_total: float}> $lineItems
     */
    private function persistLineItems(int $invoiceId, array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $lineItemId = $this->invoiceLineItemModel->insert([
                'invoice_id'     => $invoiceId,
                'fee_head_id'    => $lineItem['fee_head_id'],
                'base_amount'    => $lineItem['base_amount'],
                'waiver_amount'  => $lineItem['waiver_amount'],
                'taxable_amount' => $lineItem['taxable_amount'],
                'gst_rate'       => $lineItem['gst_rate'],
                'gst_amount'     => $lineItem['gst_amount'],
                'line_total'     => $lineItem['line_total'],
            ], true);

            $inserted = $this->invoiceLineItemModel->find($lineItemId);

            $this->auditService->record('InvoiceLineItem', $lineItemId, AuditLog::ACTION_CREATE, null, $inserted->toRawArray());
        }
    }

    /**
     * @return list<InvoiceLineItemResponse>
     */
    public function getLineItems(int $invoiceId): array
    {
        $invoice = $this->requireInvoice($invoiceId);

        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'STUDENT', $invoice->student_id);

        return array_map(
            static fn (InvoiceLineItem $lineItem): InvoiceLineItemResponse => new InvoiceLineItemResponse($lineItem),
            $this->invoiceLineItemModel->findByInvoiceId($invoiceId),
        );
    }

    /**
     * BR-FEE-004 — explicit trigger, not a scheduled job (ADR-007 §4).
     */
    public function applyLateFee(int $id): InvoiceResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireInvoice($id);

        if ($before->late_fee_applied) {
            throw new BusinessRuleException('LATE_FEE_ALREADY_APPLIED', 'A late fee has already been applied to this invoice.');
        }

        $lateFee = round($before->total_amount * ($this->configurationService->getNumber('fees.late_fee_rate_percentage') / 100), 2);

        $this->invoiceModel->update($id, [
            'total_amount'     => $before->total_amount + $lateFee,
            'late_fee_applied' => true,
        ]);

        $after = $this->invoiceModel->find($id);

        $this->auditService->record('Invoice', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new InvoiceResponse($after);
    }

    /**
     * BR-FEE-008 — explicit trigger, not a scheduled job (ADR-007 §4).
     */
    public function flagOverdueAsDefaulter(int $id): InvoiceResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireInvoice($id);

        $isOverdue = strtotime((string) $before->due_date) < strtotime(Time::now()->toDateString());

        if (! $isOverdue || in_array($before->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED], true)) {
            throw new BusinessRuleException(
                'INVOICE_NOT_OVERDUE',
                'This invoice is not past its due date, or is already paid/cancelled.',
            );
        }

        $this->invoiceModel->update($id, ['status' => Invoice::STATUS_DEFAULTER]);

        $after = $this->invoiceModel->find($id);

        $this->auditService->record('Invoice', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new InvoiceResponse($after);
    }

    public function getInvoice(int $id): InvoiceResponse
    {
        $invoice = $this->requireInvoice($id);

        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'STUDENT', $invoice->student_id);

        return new InvoiceResponse($invoice);
    }

    /**
     * ADR-012 §3: doubles as the "receipt" once status is PAID/
     * PARTIALLY_PAID — no separate receipt template (ADR-012 Context).
     */
    public function generateInvoicePdf(int $id): DocumentResponse
    {
        $invoice = $this->requireInvoice($id);

        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'STUDENT', $invoice->student_id);

        $student   = AppServices::studentService()->getStudentForCrossModuleRead($invoice->student_id);
        $lineItems = $this->invoiceLineItemModel->findByInvoiceId($id);

        $rows = '';

        foreach ($lineItems as $lineItem) {
            $feeHead  = $this->feeHeadModel->find((int) $lineItem->fee_head_id);
            $headName = $feeHead !== null ? $feeHead->fee_head_name : ('Fee Head #' . $lineItem->fee_head_id);
            $gstCell  = $lineItem->gst_rate !== null
                ? htmlspecialchars((string) $lineItem->gst_rate) . '% / ' . htmlspecialchars((string) $lineItem->gst_amount)
                : '&mdash;';

            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($headName) . '</td>'
                . '<td>' . htmlspecialchars((string) $lineItem->base_amount) . '</td>'
                . '<td>' . htmlspecialchars((string) $lineItem->waiver_amount) . '</td>'
                . '<td>' . $gstCell . '</td>'
                . '<td>' . htmlspecialchars((string) $lineItem->line_total) . '</td>'
                . '</tr>';
        }

        // BR-FEE-007 post-condition: "Receipt correctly itemizes GST only
        // for taxable fee heads" — one row per InvoiceLineItem (ADR-020),
        // GST column blank for non-taxable heads. total_amount remains the
        // single authoritative grand total (line totals + any late fee,
        // ADR-020 §d).
        $html = '<html><body>'
            . '<h2>Invoice ' . htmlspecialchars($invoice->invoice_no) . '</h2>'
            . '<p><strong>Student:</strong> ' . htmlspecialchars($student->fullName) . ' (' . htmlspecialchars($student->admissionNumber) . ')</p>'
            . '<p><strong>Due Date:</strong> ' . htmlspecialchars((string) $invoice->due_date) . '</p>'
            . '<table border="1" cellpadding="4" cellspacing="0">'
            . '<thead><tr><th>Fee Head</th><th>Base Amount</th><th>Waiver</th><th>GST Rate / Amount</th><th>Line Total</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . ($invoice->late_fee_applied ? '<p><em>Includes a late fee (BR-FEE-004), applied as a non-taxable adjustment outside the line items above.</em></p>' : '')
            . '<p><strong>Total Amount:</strong> ' . htmlspecialchars((string) $invoice->total_amount) . '</p>'
            . '<p><strong>Status:</strong> ' . htmlspecialchars($invoice->status) . '</p>'
            . '</body></html>';

        $pdfBytes = $this->pdfRenderer->render($html);

        return $this->documentService->store(
            'Invoice',
            $id,
            'Invoice',
            $pdfBytes,
            (int) RequestContext::userId(),
        );
    }

    /**
     * docs/ADR/ADR-022-reports-dashboard.md — Fee collection summary
     * (report area 1): Reports composes this instead of touching
     * InvoiceModel directly (ADR-010 §7).
     *
     * @return array{total: float, byClass: array<int, float>, defaulterCount: int}
     */
    public function getOutstandingSummaryForSession(int $academicSessionId): array
    {
        return [
            'total'          => $this->invoiceModel->sumOutstandingBySession($academicSessionId),
            'byClass'        => $this->invoiceModel->sumOutstandingByClassForSession($academicSessionId),
            'defaulterCount' => $this->invoiceModel->countDefaultersBySession($academicSessionId),
        ];
    }

    /**
     * @return list<InvoiceResponse>
     */
    public function listByStudent(int $studentId): array
    {
        $this->moduleAuthorizer->assertManageOrOwner(self::PERMISSION_MANAGE, 'STUDENT', $studentId);

        return array_map(
            static fn (Invoice $invoice): InvoiceResponse => new InvoiceResponse($invoice),
            $this->invoiceModel->findByStudentId($studentId),
        );
    }

    private function requireInvoice(int $id): Invoice
    {
        $invoice = $this->invoiceModel->find($id);

        if ($invoice === null) {
            throw new BusinessRuleException('INVOICE_NOT_FOUND', 'Invoice not found.');
        }

        return $invoice;
    }

    /**
     * ADR-007 §10 — same candidate-with-retry pattern as Admission's
     * application_reference_no and Examination's admission_number.
     */
    private function generateInvoiceNo(): string
    {
        $year = date('Y');

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = sprintf('INV-%s-%05d', $year, random_int(10000, 99999));

            if (! $this->invoiceModel->existsByInvoiceNo($candidate)) {
                return $candidate;
            }
        }

        throw new BusinessRuleException('INVOICE_NO_GENERATION_FAILED', 'Could not generate a unique invoice number.');
    }
}
