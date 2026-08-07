<?php

declare(strict_types=1);

namespace App\Modules\Fees\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\ConfigurationService;
use App\Modules\Fees\DTOs\GenerateInvoiceRequest;
use App\Modules\Fees\DTOs\InvoiceResponse;
use App\Modules\Fees\Entities\Invoice;
use App\Modules\Fees\Models\InvoiceModel;
use App\Modules\Fees\Models\ScholarshipWaiverModel;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/fees/Phase-3-Service-Controller-Design.md
 */
class InvoiceService
{
    public function __construct(
        private readonly InvoiceModel $invoiceModel,
        private readonly ScholarshipWaiverModel $scholarshipWaiverModel,
        private readonly AuditService $auditService,
        private readonly ConfigurationService $configurationService,
    ) {
    }

    /**
     * ADR-007 §1/§2: total_amount is computed (sum of matching
     * FeeStructure rows minus matching ScholarshipWaivers), never
     * client-supplied; the student must have a section_id (resolves to
     * class_id) or generation is rejected.
     */
    public function generateInvoice(GenerateInvoiceRequest $request): InvoiceResponse
    {
        $student = AppServices::studentService()->getStudent($request->studentId);

        if ($student->sectionId === null) {
            throw new BusinessRuleException(
                'STUDENT_HAS_NO_SECTION',
                'This student has no section assigned; a class cannot be resolved for invoicing.',
            );
        }

        AppServices::academicSessionService()->getSession($request->academicSessionId);

        $section  = AppServices::sectionService()->getSection($student->sectionId);
        $feeStructureService = AppServices::feeStructureService();
        $structures = $feeStructureService->listByClassSessionCategory($section->classId, $request->academicSessionId, $student->category);

        $feeHeadIds = array_map(static fn ($structure) => $structure->feeHeadId, $structures);
        $waivers    = $this->scholarshipWaiverModel->findByStudentIdAndFeeHeadIds($request->studentId, $feeHeadIds);

        $waiverByFeeHead = [];

        foreach ($waivers as $waiver) {
            $waiverByFeeHead[$waiver->fee_head_id] = ($waiverByFeeHead[$waiver->fee_head_id] ?? 0.0) + $waiver->waiver_amount;
        }

        $totalAmount = 0.0;

        foreach ($structures as $structure) {
            $waiverAmount = $waiverByFeeHead[$structure->feeHeadId] ?? 0.0;
            $totalAmount += max(0.0, $structure->amount - $waiverAmount);
        }

        $id = $this->invoiceModel->insert([
            'invoice_no'          => $this->generateInvoiceNo(),
            'student_id'          => $request->studentId,
            'academic_session_id' => $request->academicSessionId,
            'total_amount'        => round($totalAmount, 2),
            'due_date'            => $request->dueDate,
            'status'              => Invoice::STATUS_UNPAID,
        ], true);

        $invoice = $this->invoiceModel->find($id);

        $this->auditService->record('Invoice', $id, AuditLog::ACTION_CREATE, null, $invoice->toRawArray());

        return new InvoiceResponse($invoice);
    }

    /**
     * BR-FEE-004 — explicit trigger, not a scheduled job (ADR-007 §4).
     */
    public function applyLateFee(int $id): InvoiceResponse
    {
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
        return new InvoiceResponse($this->requireInvoice($id));
    }

    /**
     * @return list<InvoiceResponse>
     */
    public function listByStudent(int $studentId): array
    {
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
