<?php

declare(strict_types=1);

namespace App\Modules\Fees\Models;

use App\Core\BaseModel;
use App\Modules\Fees\Entities\Invoice;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
class InvoiceModel extends BaseModel
{
    protected $table          = 'invoices';
    protected $primaryKey     = 'invoice_id';
    protected $returnType     = Invoice::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'invoice_no',
        'student_id',
        'academic_session_id',
        'total_amount',
        'due_date',
        'status',
        'is_locked',
        'late_fee_applied',
        'created_by',
        'updated_by',
    ];

    public function existsByInvoiceNo(string $value): bool
    {
        return $this->where('invoice_no', $value)->countAllResults() > 0;
    }

    /**
     * @return list<Invoice>
     */
    public function findByStudentId(int $studentId): array
    {
        return $this->where('student_id', $studentId)->findAll();
    }

    /**
     * Input to InvoiceService::flagOverdueAsDefaulter (BR-FEE-008).
     *
     * @return list<Invoice>
     */
    public function findOverdueUnpaid(string $asOfDate): array
    {
        return $this->where('due_date <', $asOfDate)
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->findAll();
    }

    /**
     * ADR-014 §2: "fee closure" for BR-SIS-001 means no outstanding
     * balance for that student for the session being closed out of —
     * an UNPAID/PARTIALLY_PAID/DEFAULTER invoice is outstanding, a
     * PAID or CANCELLED one is not.
     */
    public function existsOutstandingByStudentIdAndSession(int $studentId, int $academicSessionId): bool
    {
        return $this->where('student_id', $studentId)
            ->where('academic_session_id', $academicSessionId)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_DEFAULTER])
            ->countAllResults() > 0;
    }

    /**
     * Input to InvoiceService::recalculateForRouteChange (BR-TRN-005) —
     * only untouched UNPAID invoices are safe to silently recompute;
     * anything partially paid, paid, defaulted, cancelled, or locked is
     * left alone.
     *
     * @return list<Invoice>
     */
    public function findRecalculableByStudentId(int $studentId): array
    {
        return $this->where('student_id', $studentId)
            ->where('status', Invoice::STATUS_UNPAID)
            ->where('is_locked', false)
            ->findAll();
    }
}
