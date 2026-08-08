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

    /**
     * docs/ADR/ADR-022-reports-dashboard.md — Fee collection summary
     * (report area 1). Outstanding = total_amount minus SUCCESSful
     * payments received, for every invoice not fully PAID/CANCELLED,
     * broken down by the class the invoice's student currently belongs
     * to (via Student.section_id -> Section.class_id, the same
     * resolution InvoiceService::generateInvoice already performs).
     *
     * @return array<int, float> class_id => outstanding amount
     */
    public function sumOutstandingByClassForSession(int $academicSessionId): array
    {
        $rows = $this->db->query(
            'SELECT sec.class_id AS class_id, '
                . 'SUM(i.total_amount - COALESCE(p.paid_total, 0)) AS outstanding '
                . 'FROM invoices i '
                . 'JOIN students st ON st.student_id = i.student_id AND st.deleted_at IS NULL '
                . 'JOIN sections sec ON sec.section_id = st.section_id AND sec.deleted_at IS NULL '
                . 'LEFT JOIN (SELECT invoice_id, SUM(amount_paid) AS paid_total FROM payments '
                . 'WHERE status = ? AND deleted_at IS NULL GROUP BY invoice_id) p ON p.invoice_id = i.invoice_id '
                . "WHERE i.academic_session_id = ? AND i.status IN ('UNPAID', 'PARTIALLY_PAID', 'DEFAULTER') "
                . 'AND i.deleted_at IS NULL '
                . 'GROUP BY sec.class_id',
            ['SUCCESS', $academicSessionId],
        )->getResultArray();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['class_id']] = (float) $row['outstanding'];
        }

        return $result;
    }

    /**
     * Session-wide outstanding total — same definition as
     * sumOutstandingByClassForSession(), not scoped by class.
     */
    public function sumOutstandingBySession(int $academicSessionId): float
    {
        $row = $this->db->query(
            'SELECT SUM(i.total_amount - COALESCE(p.paid_total, 0)) AS outstanding '
                . 'FROM invoices i '
                . 'LEFT JOIN (SELECT invoice_id, SUM(amount_paid) AS paid_total FROM payments '
                . 'WHERE status = ? AND deleted_at IS NULL GROUP BY invoice_id) p ON p.invoice_id = i.invoice_id '
                . "WHERE i.academic_session_id = ? AND i.status IN ('UNPAID', 'PARTIALLY_PAID', 'DEFAULTER') "
                . 'AND i.deleted_at IS NULL',
            ['SUCCESS', $academicSessionId],
        )->getRowArray();

        return (float) ($row['outstanding'] ?? 0.0);
    }

    /**
     * BR-FEE-008's existing DEFAULTER flag, counted per session for the
     * Reports dashboard (report area 1) — no new flag/column, just a
     * count of the existing status value.
     */
    public function countDefaultersBySession(int $academicSessionId): int
    {
        return $this->where('academic_session_id', $academicSessionId)
            ->where('status', Invoice::STATUS_DEFAULTER)
            ->countAllResults();
    }
}
