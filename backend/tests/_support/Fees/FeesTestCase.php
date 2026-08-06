<?php

declare(strict_types=1);

namespace Tests\Support\Fees;

use App\Modules\Fees\Models\FeeHeadModel;
use App\Modules\Fees\Models\FeeStructureModel;
use App\Modules\Fees\Models\InvoiceModel;
use App\Modules\Fees\Models\PaymentModel;
use App\Modules\Fees\Models\ScholarshipWaiverModel;
use CodeIgniter\I18n\Time;
use Tests\Support\Attendance\AttendanceTestCase;

/**
 * @internal
 */
abstract class FeesTestCase extends AttendanceTestCase
{
    protected function createFeeHeadFixture(?string $name = null, bool $isTaxable = false, ?float $gstRate = null): int
    {
        return (new FeeHeadModel())->insert([
            'fee_head_name' => $name ?? ('Fee ' . uniqid('', true)),
            'is_taxable'    => $isTaxable,
            'gst_rate'      => $gstRate,
        ], true);
    }

    protected function createFeeStructureFixture(
        ?int $classId = null,
        ?int $feeHeadId = null,
        ?int $academicSessionId = null,
        string $category = 'GENERAL',
        float $amount = 5000.0,
    ): int {
        return (new FeeStructureModel())->insert([
            'class_id'            => $classId ?? $this->createClassFixture(),
            'fee_head_id'         => $feeHeadId ?? $this->createFeeHeadFixture(),
            'academic_session_id' => $academicSessionId ?? $this->createAcademicSession(),
            'category'            => $category,
            'amount'              => $amount,
        ], true);
    }

    protected function createInvoiceFixture(
        ?int $studentId = null,
        ?int $academicSessionId = null,
        float $totalAmount = 5000.0,
        string $status = 'UNPAID',
        string $dueDate = '2026-12-31',
    ): int {
        return (new InvoiceModel())->insert([
            'invoice_no'          => 'INV-TEST-' . random_int(100000, 999999),
            'student_id'          => $studentId ?? $this->createStudentFixture(),
            'academic_session_id' => $academicSessionId ?? $this->createAcademicSession(),
            'total_amount'        => $totalAmount,
            'due_date'            => $dueDate,
            'status'              => $status,
        ], true);
    }

    protected function createPaymentFixture(
        ?int $invoiceId = null,
        float $amountPaid = 5000.0,
        string $status = 'SUCCESS',
        ?string $gatewayTransactionRef = null,
    ): int {
        return (new PaymentModel())->insert([
            'invoice_id'              => $invoiceId ?? $this->createInvoiceFixture(),
            'amount_paid'             => $amountPaid,
            'payment_mode'            => 'CASH',
            'gateway_transaction_ref' => $gatewayTransactionRef,
            'paid_at'                 => Time::now()->toDateTimeString(),
            'status'                  => $status,
        ], true);
    }

    protected function createScholarshipWaiverFixture(
        ?int $studentId = null,
        ?int $feeHeadId = null,
        string $waiverType = 'RTE',
        float $waiverAmount = 1000.0,
    ): int {
        return (new ScholarshipWaiverModel())->insert([
            'student_id'    => $studentId ?? $this->createStudentFixture(),
            'fee_head_id'   => $feeHeadId ?? $this->createFeeHeadFixture(),
            'waiver_type'   => $waiverType,
            'waiver_amount' => $waiverAmount,
        ], true);
    }
}
